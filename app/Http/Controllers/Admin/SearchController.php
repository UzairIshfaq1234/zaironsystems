<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Client;
use App\Models\Enquiry;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Billing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The top-bar search. Each group is searched only when the signed-in user holds its permission,
 * so nobody can find records they could not open. Up to 5 results per group.
 */
class SearchController extends Controller
{
    private const LIMIT = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $q = trim($request->validate(['q' => ['required', 'string', 'min:2', 'max:100']])['q']);
        $like = '%'.addcslashes($q, '%_\\').'%';
        $user = $request->user();
        $match = fn (Builder $query, array $columns) => $query->where(function (Builder $w) use ($columns, $like) {
            foreach ($columns as $column) {
                $w->orWhere($column, 'like', $like);
            }
        });

        $groups = [];

        if ($user->can('enquiries.view')) {
            $groups[] = $this->group('enquiries', 'Enquiries', $match(Enquiry::query(), ['name', 'email', 'company'])->latest('id')->limit(self::LIMIT)->get(),
                fn (Enquiry $e) => ['title' => $e->name, 'subtitle' => collect([$e->company, $e->email])->filter()->implode(' · '), 'badge' => str_replace('_', ' ', $e->status), 'url' => "/enquiries/{$e->id}"]);
        }

        if ($user->can('billing.view')) {
            $clients = fn (Builder $w) => $w->orWhereHas('client', fn (Builder $c) => $c->where('name', 'like', $like)->orWhere('company', 'like', $like));
            foreach ([['invoices', 'Invoices', Invoice::class], ['quotes', 'Quotes', Quote::class]] as [$key, $label, $model]) {
                $docs = $model::with('client')->where(fn (Builder $w) => $clients($w->where('number', 'like', $like)))->latest('id')->limit(self::LIMIT)->get();
                $groups[] = $this->group($key, $label, $docs, fn (Quote|Invoice $d) => [
                    'title' => $d->number, 'subtitle' => $d->client->displayName().' · '.$d->money($d->total_cents), 'badge' => $d->displayStatus(), 'url' => "/billing/{$key}/{$d->id}",
                ]);
            }
            $groups[] = $this->group('clients', 'Clients', $match(Client::query(), ['name', 'company', 'email'])->latest('id')->limit(self::LIMIT)->get(),
                fn (Client $c) => ['title' => $c->displayName(), 'subtitle' => $c->email, 'url' => "/billing/clients/{$c->id}"]);
        }

        if ($user->can('accounting.view')) {
            $expenses = Expense::with('vendor')->where(fn (Builder $w) => $w->where('number', 'like', $like)->orWhere('description', 'like', $like)->orWhere('reference', 'like', $like))
                ->latest('id')->limit(self::LIMIT)->get();
            $groups[] = $this->group('expenses', 'Expenses', $expenses, fn (Expense $e) => [
                'title' => $e->description, 'subtitle' => collect([$e->number, $e->vendor?->name, Billing::format($e->amount_cents, $e->currency)])->filter()->implode(' · '), 'badge' => $e->status(), 'url' => "/accounting/expenses?open={$e->id}",
            ]);
            $groups[] = $this->group('vendors', 'Vendors', $match(Vendor::query(), ['name', 'email'])->orderBy('name')->limit(self::LIMIT)->get(),
                fn (Vendor $v) => ['title' => $v->name, 'subtitle' => $v->email ?? 'Vendor', 'url' => '/accounting/vendors']);
            $groups[] = $this->group('accounts', 'Accounts', $match(Account::query(), ['code', 'name'])->orderBy('code')->limit(self::LIMIT)->get(),
                fn (Account $a) => ['title' => $a->name, 'subtitle' => "Account {$a->code} · ".ucfirst($a->type), 'url' => "/accounting/accounts/{$a->id}"]);
        }

        if ($user->can('users.view')) {
            $groups[] = $this->group('users', 'Team', $match(User::query(), ['name', 'email'])->orderBy('name')->limit(self::LIMIT)->get(),
                fn (User $u) => ['title' => $u->name, 'subtitle' => $u->email, 'url' => '/users']);
        }

        return response()->json(['groups' => array_values(array_filter($groups, fn ($g) => $g['items'] !== []))]);
    }

    /** @return array{key: string, label: string, items: list<array<string, string>>} */
    private function group(string $key, string $label, iterable $rows, callable $map): array
    {
        return ['key' => $key, 'label' => $label, 'items' => collect($rows)->map($map)->values()->all()];
    }
}
