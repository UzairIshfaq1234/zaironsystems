<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin;
use App\Models\ActivityLog;
use App\Models\Enquiry;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Subscriber;
use App\Models\User;
use App\Support\Billing;
use App\Support\Content;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class OverviewController extends Controller
{
    private const DAYS = 30;

    public function __invoke(Request $request): JsonResponse
    {
        $since = now()->startOfDay()->subDays(self::DAYS - 1);
        $weekAgo = now()->subDays(7);
        $twoWeeksAgo = now()->subDays(14);

        // Grouped in PHP so it works the same on SQLite, MySQL and PostgreSQL.
        $perDay = Enquiry::where('created_at', '>=', $since)->pluck('created_at')
            ->countBy(fn (Carbon $date) => $date->toDateString());

        $series = collect(range(0, self::DAYS - 1))->map(function (int $i) use ($since, $perDay) {
            $day = $since->copy()->addDays($i)->toDateString();

            return ['date' => $day, 'count' => $perDay[$day] ?? 0];
        });

        $titles = collect(Content::get('services')['items'])->pluck('title', 'slug');
        $byService = Enquiry::whereNotNull('service')->selectRaw('service, count(*) as total')->groupBy('service')
            ->orderByDesc('total')->limit(6)->get()
            ->map(fn ($row) => ['label' => $titles[$row->service] ?? $row->service, 'count' => (int) $row->total]);

        $byStatus = Enquiry::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return response()->json([
            'stats' => [
                'enquiries' => Enquiry::count(),
                'enquiries_new' => (int) ($byStatus['new'] ?? 0),
                'enquiries_week' => Enquiry::where('created_at', '>=', $weekAgo)->count(),
                'enquiries_prev_week' => Enquiry::whereBetween('created_at', [$twoWeeksAgo, $weekAgo])->count(),
                'won' => (int) ($byStatus['won'] ?? 0),
                'subscribers' => Subscriber::count(),
                'subscribers_month' => Subscriber::where('created_at', '>=', now()->subDays(30))->count(),
                'users' => User::where('is_active', true)->count(),
            ],
            'series' => $series,
            'by_service' => $byService,
            'by_status' => collect(Enquiry::STATUSES)->map(fn ($s) => ['status' => $s, 'count' => (int) ($byStatus[$s] ?? 0)]),
            'recent_enquiries' => $request->user()->hasPermission('enquiries.view')
                ? Enquiry::latest()->limit(5)->get()->map(fn ($e) => Admin::enquiry($e))
                : [],
            'billing' => $request->user()->hasPermission('billing.view') ? $this->billing() : null,
            'recent_activity' => $request->user()->hasPermission('activity.view')
                ? ActivityLog::with('user')->latest('id')->limit(6)->get()->map(fn ($a) => Admin::activity($a))
                : [],
        ]);
    }

    /** Money figures per currency (never summed across currencies), primary currency first. */
    private function billing(): array
    {
        $primary = Billing::settings()['currency'];
        $monthStart = now()->startOfMonth();

        $outstanding = Invoice::whereIn('status', ['sent', 'partial'])->selectRaw('currency, sum(total_cents - paid_cents) as cents, count(*) as n')->groupBy('currency')->get();
        $overdue = Invoice::overdue()->selectRaw('currency, sum(total_cents - paid_cents) as cents, count(*) as n')->groupBy('currency')->get();
        $paid = Payment::where('paid_on', '>=', $monthStart->toDateString())
            ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->selectRaw('invoices.currency as currency, sum(payments.amount_cents) as cents, count(*) as n')->groupBy('invoices.currency')->get();

        $shape = fn ($rows) => $rows->sortBy(fn ($r) => $r->currency === $primary ? 0 : 1)->values()
            ->map(fn ($r) => ['currency' => $r->currency, 'amount' => Billing::fromCents((int) $r->cents), 'count' => (int) $r->n]);

        return [
            'currency' => $primary,
            'outstanding' => $shape($outstanding),
            'overdue' => $shape($overdue),
            'paid_this_month' => $shape($paid),
            'open_quotes' => Quote::where('status', 'sent')->count(),
        ];
    }
}
