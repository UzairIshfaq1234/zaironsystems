<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin;
use App\Mail\DocumentMail;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Notifications\PortalAlert;
use App\Support\Accounting;
use App\Support\Billing;
use App\Support\DocumentPdf;
use App\Support\Ledger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Invoices post to the ledger once they leave draft: every change that affects the books
 * (send, edit, void, payments) runs in a transaction with Ledger::syncInvoice(), so a closed
 * period or a missing exchange rate rolls the whole change back.
 */
class InvoiceController extends Controller
{
    use ManagesDocuments;

    public const STATUSES = ['draft', 'sent', 'partial', 'paid', 'void', 'overdue'];

    public function index(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(self::STATUSES)], 'client_id' => ['nullable', 'integer']]);

        $page = Invoice::with('client')
            ->when($request->input('q'), function ($query, string $q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($w) => $w->where('number', 'like', $like)->orWhereHas('client', fn ($c) => $c->where('name', 'like', $like)->orWhere('company', 'like', $like)));
            })
            ->when($request->input('status'), fn ($query, $status) => $status === 'overdue' ? $query->overdue() : $query->where('status', $status))
            ->when($request->input('client_id'), fn ($query, $id) => $query->where('client_id', $id))
            ->latest('id')
            ->paginate(20);

        return response()->json(Admin::page($page, fn (Invoice $i) => $i->toAdminArray()));
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return response()->json([
            'data' => $invoice->load('items', 'client', 'payments', 'quote')->toAdminArray(),
            'methods' => Billing::METHODS,
            'base_currency' => Accounting::baseCurrency(),
            'deposit_accounts' => Account::whereIn('subtype', Account::MONEY_SUBTYPES)->where('is_active', true)->orderBy('code')->get()
                ->map(fn (Account $a) => ['id' => $a->id, 'name' => $a->label()]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $invoice = (new Invoice(['status' => 'draft', 'created_by' => $request->user()->id]))->saveWithItems($this->validated($request));
        ActivityLog::record('invoice.created', "Created invoice {$invoice->number} for {$invoice->client->displayName()}", $invoice);

        return response()->json(['data' => $invoice->toAdminArray()], 201);
    }

    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        abort_unless($invoice->isEditable(), 422, 'Invoices with payments, paid or void invoices can no longer be edited.');
        $data = $this->validated($request);
        DB::transaction(function () use ($invoice, $data) {
            $invoice->saveWithItems($data);
            Ledger::syncInvoice($invoice);
        });
        ActivityLog::record('invoice.updated', "Updated invoice {$invoice->number}", $invoice);

        return response()->json(['data' => $invoice->toAdminArray()]);
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        abort_unless($invoice->status === 'draft', 422, 'Only draft invoices can be deleted. Void a sent invoice instead.');
        $invoice->items()->delete();
        $invoice->quote()->update(['status' => 'accepted', 'invoice_id' => null]);
        $invoice->delete();
        ActivityLog::record('invoice.deleted', "Deleted draft invoice {$invoice->number}");

        return response()->json(status: 204);
    }

    public function send(Request $request, Invoice $invoice): JsonResponse
    {
        abort_if(in_array($invoice->status, ['void', 'paid'], true), 422, 'Paid or void invoices cannot be sent.');
        // Check the books can take it before anything is emailed.
        Accounting::toBase($invoice->total_cents, $invoice->currency, $invoice->exchange_rate);
        Accounting::assertOpen($invoice->issue_date, 'issue_date');

        DB::transaction(function () use ($request, $invoice) {
            $this->sendDocument($request, $invoice);
            Ledger::syncInvoice($invoice);
        });

        return response()->json(['data' => $invoice->load('items', 'client', 'payments')->toAdminArray(), 'message' => 'Invoice sent.']);
    }

    public function pdf(Request $request, Invoice $invoice): Response
    {
        return $this->pdfResponse($request, $invoice);
    }

    public function remind(Invoice $invoice): JsonResponse
    {
        abort_unless(in_array($invoice->status, ['sent', 'partial'], true), 422, 'Only unpaid, sent invoices can get a reminder.');
        $invoice->load('client');
        Mail::to($invoice->client->email, $invoice->client->name)->queue(new DocumentMail($invoice, 'reminder', pdfPath: DocumentPdf::storeSentCopy($invoice)));
        $invoice->forceFill(['last_reminder_at' => now()])->save();
        ActivityLog::record('invoice.reminded', "Sent a payment reminder for {$invoice->number}", $invoice);

        return response()->json(['message' => "Reminder sent to {$invoice->client->email}."]);
    }

    public function void(Invoice $invoice): JsonResponse
    {
        abort_if($invoice->status === 'void', 422, 'This invoice is already void.');
        abort_if($invoice->paid_cents > 0, 422, 'Remove the recorded payments before voiding this invoice.');
        DB::transaction(function () use ($invoice) {
            $invoice->forceFill(['status' => 'void'])->save();
            Ledger::syncInvoice($invoice);
        });
        ActivityLog::record('invoice.voided', "Voided invoice {$invoice->number}", $invoice);

        return response()->json(['data' => $invoice->load('items', 'client', 'payments')->toAdminArray()]);
    }

    public function addPayment(Request $request, Invoice $invoice): JsonResponse
    {
        abort_if(in_array($invoice->status, ['void', 'draft'], true), 422, 'Send the invoice before recording payments.');
        $foreign = $invoice->currency !== Accounting::baseCurrency();
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::in(Billing::METHODS)],
            'reference' => ['nullable', 'string', 'max:120'],
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->whereIn('subtype', Account::MONEY_SUBTYPES)->where('is_active', true)],
            'exchange_rate' => [$foreign ? 'required' : 'nullable', ...Accounting::RATE_RULES],
        ], ['exchange_rate.required' => "Enter the rate you received: 1 {$invoice->currency} = how many ".Accounting::baseCurrency().'.']);
        $cents = Billing::toCents($data['amount']);
        abort_if($cents > $invoice->balanceCents(), 422, 'That is more than the balance due ('.$invoice->money($invoice->balanceCents()).').');

        DB::transaction(function () use ($invoice, $data, $cents, $foreign, $request) {
            $invoice->payments()->create([
                'amount_cents' => $cents,
                'exchange_rate' => $foreign ? $data['exchange_rate'] : null,
                'account_id' => $data['account_id'] ?? null,
                'paid_on' => $data['paid_on'],
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'recorded_by' => $request->user()->id,
            ]);
            $invoice->syncPayments();
            Ledger::syncInvoice($invoice);
        });
        ActivityLog::record('invoice.payment', "Recorded {$invoice->money($cents)} on {$invoice->number}", $invoice);
        PortalAlert::toTeam('billing.view', new PortalAlert(
            'payment',
            "{$invoice->money($cents)} received on {$invoice->number}",
            $invoice->client->displayName().' · '.($invoice->status === 'paid' ? 'paid in full' : $invoice->money($invoice->balanceCents()).' still due'),
            "/billing/invoices/{$invoice->id}",
        ), except: $request->user());

        return response()->json(['data' => $invoice->load('items', 'client', 'payments')->toAdminArray()], 201);
    }

    public function removePayment(Invoice $invoice, Payment $payment): JsonResponse
    {
        abort_unless($payment->invoice_id === $invoice->id, 404);
        DB::transaction(function () use ($invoice, $payment) {
            $payment->delete();
            $invoice->syncPayments();
            Ledger::syncInvoice($invoice);
        });
        ActivityLog::record('invoice.payment_removed', "Removed a {$invoice->money($payment->amount_cents)} payment from {$invoice->number}", $invoice);

        return response()->json(['data' => $invoice->load('items', 'client', 'payments')->toAdminArray()]);
    }

    private function validated(Request $request): array
    {
        $data = $this->validateDocument($request, Invoice::KIND, [
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'exchange_rate' => ['nullable', ...Accounting::RATE_RULES],
        ]);

        return [...$data, 'exchange_rate' => Accounting::normaliseRate($data['currency'], $data['exchange_rate'] ?? null)];
    }
}
