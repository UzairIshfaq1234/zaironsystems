<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Quote;
use App\Support\Billing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuoteController extends Controller
{
    use ManagesDocuments;

    public function index(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in([...Quote::STATUSES, 'expired'])]]);

        $page = Quote::with('client')
            ->when($request->input('q'), function ($query, string $q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($w) => $w->where('number', 'like', $like)->orWhereHas('client', fn ($c) => $c->where('name', 'like', $like)->orWhere('company', 'like', $like)));
            })
            ->when($request->input('status'), fn ($query, $status) => match ($status) {
                'expired' => $query->where('status', 'sent')->whereDate('valid_until', '<', now()->toDateString()),
                'sent' => $query->where('status', 'sent')->where(fn ($w) => $w->whereNull('valid_until')->orWhereDate('valid_until', '>=', now()->toDateString())),
                default => $query->where('status', $status),
            })
            ->latest('id')
            ->paginate(20);

        return response()->json(Admin::page($page, fn (Quote $q) => $q->toAdminArray()));
    }

    public function show(Quote $quote): JsonResponse
    {
        return response()->json(['data' => $quote->load('items', 'client')->toAdminArray()]);
    }

    public function pdf(Request $request, Quote $quote): Response
    {
        return $this->pdfResponse($request, $quote);
    }

    public function store(Request $request): JsonResponse
    {
        $quote = (new Quote(['status' => 'draft', 'created_by' => $request->user()->id]))->saveWithItems($this->validated($request));
        ActivityLog::record('quote.created', "Created quote {$quote->number} for {$quote->client->displayName()}", $quote);

        return response()->json(['data' => $quote->toAdminArray()], 201);
    }

    public function update(Request $request, Quote $quote): JsonResponse
    {
        abort_unless($quote->isEditable(), 422, 'Accepted, declined or invoiced quotes can no longer be edited.');
        $quote->saveWithItems($this->validated($request));
        ActivityLog::record('quote.updated', "Updated quote {$quote->number}", $quote);

        return response()->json(['data' => $quote->toAdminArray()]);
    }

    public function destroy(Quote $quote): JsonResponse
    {
        abort_unless($quote->status === 'draft', 422, 'Only draft quotes can be deleted.');
        $quote->items()->delete();
        $quote->delete();
        ActivityLog::record('quote.deleted', "Deleted draft quote {$quote->number}");

        return response()->json(status: 204);
    }

    public function send(Request $request, Quote $quote): JsonResponse
    {
        abort_unless($quote->isEditable(), 422, 'This quote has already been answered.');
        $this->sendDocument($request, $quote);

        return response()->json(['data' => $quote->load('items', 'client')->toAdminArray(), 'message' => 'Quote sent.']);
    }

    /** Record the client's answer manually (e.g. they replied by email). */
    public function respond(Request $request, Quote $quote): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['accepted', 'declined'])]]);
        abort_unless($quote->isEditable(), 422, 'This quote has already been answered.');

        $quote->forceFill(['status' => $data['status'], 'responded_at' => now()])->save();
        ActivityLog::record("quote.{$data['status']}", "Marked quote {$quote->number} as {$data['status']}", $quote);

        return response()->json(['data' => $quote->load('items', 'client')->toAdminArray()]);
    }

    /** Copies the quote (client, items, tax, discount) into a new draft invoice. */
    public function convert(Request $request, Quote $quote): JsonResponse
    {
        abort_if($quote->invoice_id, 422, 'This quote has already been invoiced.');
        abort_if($quote->status === 'declined', 422, 'Declined quotes cannot be invoiced.');
        $quote->load('items');
        $settings = Billing::settings();

        $invoice = DB::transaction(function () use ($quote, $request, $settings) {
            $invoice = (new Invoice(['status' => 'draft', 'quote_id' => $quote->id, 'created_by' => $request->user()->id]))->saveWithItems([
                'client_id' => $quote->client_id,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays($settings['due_days'])->toDateString(),
                'currency' => $quote->currency,
                'tax_rate' => $quote->tax_rate,
                'discount' => Billing::fromCents($quote->discount_cents),
                'notes' => $quote->notes,
                'terms' => Billing::termsFor(Invoice::KIND),
                'items' => $quote->items->map(fn ($i) => ['description' => $i->description, 'quantity' => $i->quantity, 'unit_price' => Billing::fromCents($i->unit_cents)])->all(),
            ]);
            $quote->forceFill(['status' => 'invoiced', 'invoice_id' => $invoice->id, 'responded_at' => $quote->responded_at ?? now()])->save();

            return $invoice;
        });

        ActivityLog::record('quote.invoiced', "Converted quote {$quote->number} into invoice {$invoice->number}", $invoice);

        return response()->json(['data' => $invoice->toAdminArray()], 201);
    }

    private function validated(Request $request): array
    {
        return $this->validateDocument($request, Quote::KIND, ['valid_until' => ['nullable', 'date', 'after_or_equal:issue_date']]);
    }
}
