<?php

namespace App\Models\Concerns;

/** Gives a model a `number` like PREFIX-2026-0001 when it is created. The model defines `PREFIX`. */
trait NumberedPerYear
{
    protected static function bootNumberedPerYear(): void
    {
        static::creating(function (self $model) {
            $model->number ??= static::nextNumber();
        });
    }

    public static function nextNumber(): string
    {
        $prefix = static::PREFIX.'-'.now()->year.'-';
        // Longest-then-highest, so 10000 sorts after 9999.
        $last = static::where('number', 'like', $prefix.'%')->orderByRaw('LENGTH(number) DESC')->orderByDesc('number')->value('number');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
