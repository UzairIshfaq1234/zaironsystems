<?php

namespace App\Support;

/**
 * Server-rendered meta tags and JSON-LD for each SPA route, so crawlers and link previews
 * (which don't run JavaScript) see the right title, description and structured data.
 * Mirrors resources/js/lib/seo.js.
 */
final class Seo
{
    /**
     * @return array{title: string, description: string, noindex: bool, canonical: string, image: ?string, schema: list<array<mixed>>}|null
     *                                                                                                                                      null when the path is not a page of the site
     */
    public static function page(string $path): ?array
    {
        $path = $path === '/' ? '/' : '/'.trim($path, '/');
        $pages = Content::get('pages');

        if ($path !== '404' && isset($pages[$path])) {
            $page = $pages[$path];

            return self::build($path, $page, self::schemaFor($path, $page), self::pageBody($path, $page));
        }

        if (preg_match('#^/services/([a-z0-9-]+)$#', $path, $m) && ($service = self::serviceDetail($m[1]))) {
            return self::build($path, $service['seo'], self::serviceSchema($path, $service), self::serviceBody($service));
        }

        return null;
    }

    public static function notFound(string $path): array
    {
        $page = Content::get('pages')['404'];

        return self::build($path, $page, [], self::pageBody($path, $page));
    }

    /** @return list<string> Every indexable path, for the sitemap. */
    public static function paths(): array
    {
        $pages = Content::get('pages');
        // PHP turns the "404" key into an int, so compare as strings. Noindex pages stay out of the sitemap.
        $static = array_filter(array_keys($pages), fn ($p) => (string) $p !== '404' && ! ($pages[$p]['noindex'] ?? false));
        $services = array_map(fn ($slug) => "/services/{$slug}", array_keys(Content::get('services')['details']));

        return array_values([...$static, ...$services]);
    }

    public static function url(string $path = '/'): string
    {
        return rtrim((string) config('app.url'), '/').($path === '/' ? '/' : $path);
    }

    private static function build(string $path, array $page, array $schema, string $body): array
    {
        $image = config('zairon.og_image');
        $noindex = (bool) ($page['noindex'] ?? false);

        return [
            'title' => $page['title'],
            'description' => $page['description'],
            'noindex' => $noindex,
            'canonical' => self::url($path),
            'image' => $image && is_file(public_path($image)) ? self::url('/'.$image) : null,
            'schema' => $noindex ? [] : [self::organization(), ...$schema],
            'body' => $body,
        ];
    }

    /**
     * Real text for crawlers and no-JavaScript visitors, rendered inside #root. React replaces it on load.
     */
    private static function pageBody(string $path, array $page): string
    {
        $heading = explode(' · ', $page['title'])[0];
        $html = '<h1>'.e($heading).'</h1><p>'.e($page['description']).'</p>';

        if ($path === '/') {
            $html .= self::servicesList().self::faqList('home');
        }

        if ($path === '/services') {
            $html .= self::servicesList();
        }

        if ($path === '/contact') {
            $html .= self::faqList('contact');
        }

        return $html.self::siteChrome();
    }

    private static function serviceBody(array $service): string
    {
        return '<h1>'.e($service['title']).'</h1>'
            .'<p>'.e($service['lead'] ?? $service['text']).'</p>'
            .self::faqList($service['slug'])
            .self::siteChrome();
    }

    private static function servicesList(): string
    {
        $items = array_map(
            fn ($s) => '<li><a href="'.e(self::url('/services/'.$s['slug'])).'">'.e($s['title']).'</a>: '.e($s['text']).'</li>',
            Content::get('services')['items'],
        );

        return '<h2>Our services</h2><ul>'.implode('', $items).'</ul>';
    }

    private static function faqList(string $key): string
    {
        $items = Content::get('faqs')[$key] ?? [];

        if ($items === []) {
            return '';
        }

        $html = '<h2>Frequently asked questions</h2><dl>';

        foreach ($items as $item) {
            $html .= '<dt>'.e($item['q']).'</dt><dd>'.e($item['a']).'</dd>';
        }

        return $html.'</dl>';
    }

    private static function siteChrome(): string
    {
        $site = Content::get('site');
        $links = array_map(fn ($item) => '<li><a href="'.e(self::url($item['to'])).'">'.e($item['label']).'</a></li>', $site['nav']);

        return '<nav aria-label="Main"><ul>'.implode('', $links).'</ul></nav>'
            .'<footer><p>'.e($site['name']).' · <a href="mailto:'.e($site['email']).'">'.e($site['email']).'</a></p></footer>';
    }

    private static function schemaFor(string $path, array $page): array
    {
        $schema = [];

        foreach ($page['schema'] ?? [] as $type) {
            $schema[] = match (true) {
                $type === 'website' => ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => self::site('name'), 'alternateName' => 'ZAIRON', 'url' => self::url()],
                $type === 'services' => self::serviceList(),
                $type === 'about' => ['@context' => 'https://schema.org', '@type' => 'AboutPage', 'name' => $page['title'], 'url' => self::url($path)],
                str_starts_with($type, 'faq:') => self::faq(substr($type, 4)),
            };
        }

        if (isset($page['crumb'])) {
            $schema[] = self::breadcrumb([[$page['crumb'], $path]]);
        }

        return array_values(array_filter($schema));
    }

    private static function serviceSchema(string $path, array $service): array
    {
        return array_values(array_filter([
            [
                '@context' => 'https://schema.org',
                '@type' => 'Service',
                'name' => $service['seo']['title'],
                'serviceType' => $service['seo']['serviceType'],
                'description' => $service['seo']['schemaDescription'],
                'provider' => ['@id' => self::url().'#org'],
                'areaServed' => 'Worldwide',
            ],
            self::faq($service['slug']),
            self::breadcrumb([['Services', '/services'], [$service['title'], $path]]),
        ]));
    }

    private static function organization(): array
    {
        $site = Content::get('site');

        return [
            '@context' => 'https://schema.org',
            '@type' => ['Organization', 'ProfessionalService'],
            '@id' => self::url().'#org',
            'name' => $site['name'],
            'alternateName' => 'ZAIRON',
            'slogan' => $site['slogan'],
            'url' => self::url(),
            'logo' => self::url('/logo.svg'),
            'email' => $site['email'],
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'contactType' => 'sales',
                'email' => $site['email'],
                'availableLanguage' => ['English'],
            ],
            'founder' => array_map(fn ($f) => ['@type' => 'Person', 'name' => $f['name']], $site['founders']),
            'knowsAbout' => array_column(Content::get('services')['items'], 'title'),
            'sameAs' => array_values(array_filter(array_column($site['social'], 'url'))),
        ];
    }

    private static function serviceList(): array
    {
        $items = Content::get('services')['items'];

        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => self::site('name').' services',
            'itemListElement' => array_map(fn ($s, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'item' => ['@type' => 'Service', 'name' => $s['title'], 'description' => $s['text'], 'provider' => ['@id' => self::url().'#org']],
            ], $items, array_keys($items)),
        ];
    }

    private static function faq(string $key): ?array
    {
        $items = Content::get('faqs')[$key] ?? null;

        return $items ? [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn ($f) => [
                '@type' => 'Question',
                'name' => $f['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
            ], $items),
        ] : null;
    }

    /** @param list<array{0: string, 1: string}> $trail [label, path] pairs after "Home" */
    private static function breadcrumb(array $trail): array
    {
        $trail = [['Home', '/'], ...$trail];

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn ($crumb, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb[0],
                'item' => self::url($crumb[1]),
            ], $trail, array_keys($trail)),
        ];
    }

    private static function serviceDetail(string $slug): ?array
    {
        $services = Content::get('services');
        $item = collect($services['items'])->firstWhere('slug', $slug);

        return $item && isset($services['details'][$slug]) ? [...$services['details'][$slug], ...$item] : null;
    }

    private static function site(string $key): mixed
    {
        return Content::get('site')[$key];
    }
}
