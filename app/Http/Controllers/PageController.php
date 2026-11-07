<?php

namespace App\Http\Controllers;

use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Serves the React app shell for every page, with that page's meta tags rendered server-side.
 * Unknown paths still get the shell (React shows the 404 page) but with a real 404 status.
 */
class PageController extends Controller
{
    public function show(Request $request): Response
    {
        $path = '/'.ltrim($request->path(), '/');
        $seo = Seo::page($path);

        return response()->view('app', ['seo' => $seo ?? Seo::notFound($path)], $seo ? 200 : 404);
    }

    public function sitemap(): Response
    {
        $updated = date('Y-m-d', max(array_map('filemtime', glob(resource_path('content/*.json')))));

        return response()
            ->view('sitemap', ['urls' => array_map(Seo::url(...), Seo::paths()), 'updated' => $updated])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $lines = ['User-agent: *', 'Allow: /', 'Disallow: /api/', 'Disallow: /admin', 'Disallow: /portal', 'Disallow: /billing/', '', 'Sitemap: '.Seo::url('/sitemap.xml')];

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
