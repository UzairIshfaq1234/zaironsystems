<?php

namespace App\Models\Concerns;

use App\Models\Client;
use App\Models\LineItem;
use App\Support\Billing;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Shared by Quote and Invoice: client, line items, totals, numbering (PREFIX-YYYYMMDDHHMMSSmmm)
 * and the unguessable token behind the client-facing view link.
 */
trait BillingDocument
{
    protected static function bootBillingDocument(): void
    {
        // Block bodies: a `creating` listener that returns a value stops the remaining listeners.
        static::creating(function (self $doc) {
            $doc->public_token ??= Str::random(48);
            $doc->number ??= static::nextNumber();
        });
    }

    /**
     * PREFIX-001, PREFIX-002, … Only three-digit sequence numbers count, so older documents
     * (e.g. Q-2026-0001 or timestamp numbers) never change the sequence.
     */
    public static function nextNumber(): string
    {
        $prefix = static::PREFIX.'-';
        $last = static::query()->where('number', 'like', $prefix.'%')->whereRaw('LENGTH(number) = ?', [strlen($prefix) + 3])->max('number');
        $next = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        while (static::where('number', $prefix.str_pad((string) $next, 3, '0', STR_PAD_LEFT))->exists()) {
            $next++;
        }

        return $prefix.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): MorphMany
    {
        return $this->morphMany(LineItem::class, 'document')->orderBy('sort');
    }

    /**
     * Fill from validated input, recalculate totals and replace the line items, atomically.
     *
     * @param  array{items: list<array{description: string, quantity: mixed, unit_price: mixed}>}  $data
     */
    public function saveWithItems(array $data): static
    {
        return DB::transaction(function () use ($data) {
            $items = array_values($data['items']);
            $totals = Billing::totals($items, Billing::toCents($data['discount'] ?? 0), (float) ($data['tax_rate'] ?? 0));

            $this->fill(collect($data)->except(['items', 'discount'])->all());
            $this->forceFill([
                'discount_cents' => $totals['discount'],
                'subtotal_cents' => $totals['subtotal'],
                'tax_cents' => $totals['tax'],
                'total_cents' => $totals['total'],
            ])->save();

            $this->items()->delete();
            foreach ($items as $i => $item) {
                $this->items()->create([
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_cents' => Billing::toCents($item['unit_price']),
                    'amount_cents' => $totals['lines'][$i],
                    'sort' => $i,
                ]);
            }

            return $this->load('items', 'client');
        });
    }

    public function publicUrl(): string
    {
        return Seo::url('/billing/'.static::KIND.'/'.$this->public_token);
    }

    public function money(int $cents): string
    {
        return Billing::format($cents, $this->currency);
    }

    /** Fields common to quotes and invoices for the admin API. */
    protected function documentArray(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'kind' => static::KIND,
            'status' => $this->displayStatus(),
            'client' => $this->relationLoaded('client') ? $this->client?->toSummaryArray() : null,
            'client_id' => $this->client_id,
            'issue_date' => $this->issue_date?->toDateString(),
            'currency' => $this->currency,
            'tax_rate' => (float) $this->tax_rate,
            'discount' => Billing::fromCents($this->discount_cents),
            'subtotal' => Billing::fromCents($this->subtotal_cents),
            'tax' => Billing::fromCents($this->tax_cents),
            'total' => Billing::fromCents($this->total_cents),
            'notes' => $this->notes,
            'terms' => $this->terms,
            'public_url' => $this->publicUrl(),
            // Read from the raw attributes: a just-created model has no pdf_path loaded (strict mode).
            'has_sent_pdf' => filled($this->getAttributes()['pdf_path'] ?? null),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'viewed_at' => $this->viewed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'items' => $this->relationLoaded('items') ? $this->items->map->toAdminArray()->values() : [],
        ];
    }
}
