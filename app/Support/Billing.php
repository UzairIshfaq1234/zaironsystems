<?php

namespace App\Support;

use App\Models\Setting;
use NumberFormatter;

/** Billing settings, money formatting and the one place totals are calculated. */
final class Billing
{
    /** Every ISO 4217 currency, code => name (config/currencies.php). */
    public static function currencies(): array
    {
        return config('currencies');
    }

    public const DEFAULT_QUOTE_NOTES = 'Thank you for considering ZAIRON Systems. This proposal covers the scope we discussed; we are happy to walk you through it on a call. To go ahead, accept the quote online or simply reply to our email, and we will schedule the kick-off.';

    public const DEFAULT_QUOTE_TERMS = "1. This quotation is valid for 30 days from the issue date.\n"
        ."2. Payment: 50% advance to start the project, 50% on delivery before go-live.\n"
        ."3. The scope covers the items listed above; changes or new features are quoted separately.\n"
        ."4. Timelines start once the advance and all required content, access and approvals are received.\n"
        ."5. Includes 30 days of free bug fixes after launch; ongoing maintenance is available as a monthly plan.\n"
        ."6. Third-party costs (hosting, domains, licences, paid APIs) are not included unless listed.\n"
        ."7. Source code and intellectual property transfer to the client on full payment.\n"
        .'8. Prices exclude applicable taxes unless stated.';

    public const DEFAULT_INVOICE_TERMS = "Payment is due by the date shown. Please quote the invoice number as the payment reference.\n"
        .'Work may be paused on overdue invoices. Source code and intellectual property transfer on full payment.';

    /** The standard terms printed on every quote or invoice. */
    public static function termsFor(string $kind): ?string
    {
        return self::settings()[$kind === 'quote' ? 'quote_terms' : 'default_terms'];
    }

    public const METHODS = ['Bank transfer', 'Card', 'Cash', 'Cheque', 'PayPal', 'Stripe', 'Other'];

    public static function settings(): array
    {
        $site = Content::get('site');

        return [
            'company_name' => $site['name'],
            'company_email' => $site['email'],
            'company_phone' => null,
            'company_address' => null,
            'tax_number' => null,
            'currency' => 'USD',
            'tax_rate' => 0,
            'due_days' => 14,
            'quote_valid_days' => 30,
            'payment_instructions' => null,
            // Terms come from here for every document (they are not typed per quote/invoice).
            'quote_terms' => self::DEFAULT_QUOTE_TERMS,
            'default_terms' => self::DEFAULT_INVOICE_TERMS,
            'quote_notes' => self::DEFAULT_QUOTE_NOTES,
            'reminders' => true,
            ...Setting::get('billing', []),
        ];
    }

    /** "1,250.50" / 1250.5 → 125050. Accepts at most 2 decimals (validated before this is called). */
    public static function toCents(string|int|float|null $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function fromCents(int $cents): float
    {
        return round($cents / 100, 2);
    }

    /** ISO 4217 currencies without minor units; all others show 2 decimals (amounts are stored in hundredths). */
    public const ZERO_DECIMAL = ['BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

    public static function format(int $cents, string $currency): string
    {
        $formatter = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
        $digits = in_array($currency, self::ZERO_DECIMAL, true) ? 0 : 2;
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $digits);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $digits);
        $formatter->setAttribute(NumberFormatter::ROUNDING_MODE, NumberFormatter::ROUND_HALFUP); // same as the browser's Intl

        return $formatter->formatCurrency($cents / 100, $currency);
    }

    /**
     * Line amounts, subtotal, discount (before tax), tax and total, all in cents.
     *
     * @param  list<array{quantity: string|float, unit_price: string|float}>  $items
     * @return array{lines: list<int>, subtotal: int, discount: int, tax: int, total: int}
     */
    public static function totals(array $items, int $discountCents, float $taxRate): array
    {
        $lines = array_map(fn ($item) => (int) round(((float) $item['quantity']) * self::toCents($item['unit_price'])), $items);
        $subtotal = array_sum($lines);
        $discount = min(max($discountCents, 0), $subtotal);
        $tax = (int) round(($subtotal - $discount) * $taxRate / 100);

        return ['lines' => $lines, 'subtotal' => $subtotal, 'discount' => $discount, 'tax' => $tax, 'total' => $subtotal - $discount + $tax];
    }
}
