<?php

namespace Tests\Feature;

use App\Support\Content;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.url' => 'https://zairon.test']);
    }

    public static function pages(): array
    {
        return [
            'home' => ['/', 'Software Development &amp; AI Company · ZAIRON Systems'],
            'services' => ['/services', 'Software Development Services · ZAIRON Systems'],
            'service detail' => ['/services/ai-computer-vision', 'AI &amp; Computer Vision Development · ZAIRON Systems'],
            'work' => ['/work', 'Software Projects &amp; Case Studies · ZAIRON Systems'],
            'about' => ['/about', 'About ZAIRON Systems · Ideas, To Reality'],
            'careers' => ['/careers', 'Careers at ZAIRON Systems · Engineering Jobs'],
            'contact' => ['/contact', 'Contact ZAIRON Systems · Start Your Project'],
            'privacy' => ['/privacy', 'Privacy Policy · ZAIRON Systems'],
            'terms' => ['/terms', 'Terms of Service · ZAIRON Systems'],
        ];
    }

    public function test_every_service_has_its_own_page_with_service_structured_data(): void
    {
        foreach (Content::serviceSlugs() as $slug) {
            $html = $this->get("/services/{$slug}")->assertOk()->getContent();
            $this->assertStringContainsString('"@type":"Service"', $html, $slug);
            $this->assertStringContainsString('"@type":"BreadcrumbList"', $html, $slug);
            $this->assertStringContainsString('"@type":"FAQPage"', $html, $slug);
        }
        $this->assertCount(11, Content::serviceSlugs());
    }

    #[DataProvider('pages')]
    public function test_each_page_renders_the_app_shell_with_its_own_meta(string $path, string $title): void
    {
        $this->get($path)
            ->assertOk()
            ->assertSee('<div id="root"><h1>', false)
            ->assertSee("<title>{$title}</title>", false)
            ->assertSee('<link rel="canonical" href="https://zairon.test'.($path === '/' ? '/' : $path).'">', false)
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('<meta name="csrf-token"', false);
    }

    public function test_unknown_pages_return_a_real_404_with_noindex(): void
    {
        $this->get('/does-not-exist')
            ->assertNotFound()
            ->assertSee('<div id="root"><h1>Page not found</h1>', false)
            ->assertSee('<title>Page not found · ZAIRON Systems</title>', false)
            ->assertSee('<meta name="robots" content="noindex, follow">', false);

        $this->get('/services/not-a-service')->assertNotFound();
    }

    public function test_structured_data_is_rendered_and_cannot_break_out_of_the_script_tag(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('"@type":"FAQPage"', $html);
        $this->assertStringContainsString('"@type":"WebSite"', $html);
        preg_match_all('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $blocks);
        $this->assertNotEmpty($blocks[1]);
        foreach ($blocks[1] as $json) {
            $this->assertStringNotContainsString('<', $json);
            $this->assertIsArray(json_decode($json, true, flags: JSON_THROW_ON_ERROR));
        }
    }

    public function test_api_paths_are_never_served_the_spa(): void
    {
        $this->get('/api/contact')->assertStatus(405);
        $this->getJson('/api/nothing-here')->assertNotFound()->assertHeader('Content-Type', 'application/json');
    }

    public function test_sitemap_lists_every_page_with_the_configured_domain(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        foreach (Seo::paths() as $path) {
            $response->assertSee('<loc>'.Seo::url($path).'</loc>', false);
        }
        // 8 indexable static pages + 11 service pages; Insights (noindex until it has articles) and the 404 page are never listed.
        $this->assertCount(19, Seo::paths());
        $response->assertDontSee('/404')->assertDontSee('/insights');
    }

    public function test_insights_stays_out_of_search_until_it_has_articles(): void
    {
        $this->get('/insights')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_robots_points_to_the_sitemap(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /api/')
            ->assertSee('Sitemap: https://zairon.test/sitemap.xml');
    }

    public function test_health_check_still_works(): void
    {
        $this->get('/up')->assertOk();
    }
}
