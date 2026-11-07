<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/** Accounting settings, the financial year and currency conversion into the base currency. */
final class Accounting
{
    public const MONTHS = [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    public static function settings(): array
    {
        return [
            'base_currency' => config('accounting.base_currency'),
            'fiscal_year_start' => config('accounting.fiscal_year_start'),
            'lock_date' => null,
            ...Setting::get('accounting', []),
        ];
    }

    public static function baseCurrency(): string
    {
        return self::settings()['base_currency'];
    }

    public static function lockDate(): ?CarbonImmutable
    {
        $date = self::settings()['lock_date'];

        return $date ? CarbonImmutable::parse($date)->startOfDay() : null;
    }

    /** Throws a validation error if `$date` falls in a closed period. */
    public static function assertOpen(CarbonInterface|string $date, string $field = 'date'): void
    {
        $lock = self::lockDate();
        if ($lock && CarbonImmutable::parse($date)->startOfDay()->lte($lock)) {
            throw ValidationException::withMessages([
                $field => "The books are closed up to {$lock->format('j M Y')}. Reopen the period in Accounting settings to change anything dated on or before it.",
            ]);
        }
    }

    /**
     * The financial year containing `$date`: ['start' => date, 'end' => date, 'label' => "FY 2026–27"].
     */
    public static function fiscalYear(CarbonInterface|string|null $date = null): array
    {
        $date = CarbonImmutable::parse($date ?? 'today');
        $month = (int) self::settings()['fiscal_year_start'];
        $year = $date->month >= $month ? $date->year : $date->year - 1;
        $start = CarbonImmutable::create($year, $month, 1);
        $end = $start->addYear()->subDay();

        return [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'label' => $month === 1 ? "FY {$year}" : 'FY '.$year.'–'.substr((string) ($year + 1), -2),
        ];
    }

    /** Converts an amount in `$currency` to base-currency cents. Base-currency amounts are returned unchanged. */
    public static function toBase(int $cents, string $currency, string|float|null $rate): int
    {
        if ($currency === self::baseCurrency()) {
            return $cents;
        }
        if ($rate === null || (float) $rate <= 0) {
            throw ValidationException::withMessages([
                'exchange_rate' => "Enter the exchange rate (1 {$currency} = how many ".self::baseCurrency().').',
            ]);
        }

        return (int) round($cents * (float) $rate);
    }

    /** Validation for an exchange rate: positive, up to 8 decimals. */
    public const RATE_RULES = ['numeric', 'gt:0', 'max:99999999', 'regex:/^\d{1,8}(\.\d{1,8})?$/'];

    /** Keeps exchange_rate null for base-currency documents so stale rates can't linger. */
    public static function normaliseRate(string $currency, mixed $rate): ?string
    {
        return $currency === self::baseCurrency() || $rate === null || $rate === '' ? null : (string) $rate;
    }
}
