<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Read-only access to the JSON files in resources/content.
 * The React app imports the same files, so copy lives in exactly one place.
 */
final class Content
{
    /** @var array<string, array<mixed>> */
    private static array $cache = [];

    /** @return array<mixed> */
    public static function get(string $name): array
    {
        return self::$cache[$name] ??= json_decode(
            File::get(resource_path("content/{$name}.json")),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /** @return list<string> Allowed values for the contact form "What do you need?" field. */
    public static function serviceOptions(): array
    {
        return array_column(self::get('services')['formOptions'], 'value');
    }

    /** @return list<string> */
    public static function budgets(): array
    {
        return self::get('services')['budgets'];
    }

    /** @return list<string> Slugs of every service (forms on service pages send one). */
    public static function serviceSlugs(): array
    {
        return array_column(self::get('services')['items'], 'slug');
    }

    /** @return list<string> "How would you like to start?" plan ids. */
    public static function planIds(): array
    {
        return array_column(self::get('engagement')['plans'], 'id');
    }
}
