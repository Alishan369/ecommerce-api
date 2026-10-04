<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Support\Demo;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * robots.txt and sitemap.xml for the storefront. Served by Laravel so they can
 * use live data and the real domain (FRONTEND_URL); the web server routes
 * /robots.txt and /sitemap.xml here.
 */
class SeoController extends Controller
{
    /** Storefront pages that are worth indexing besides products and collections. */
    private const STATIC_PAGES = ['/', '/shop', '/offers', '/about', '/contact', '/track-order',
        '/policies/shipping', '/policies/returns', '/policies/privacy', '/policies/terms'];

    public function robots(): Response
    {
        $site = config('shop.frontend_url');

        // A public demo shouldn't compete with (or be mistaken for) a real store in search results.
        $body = Demo::enabled()
            ? "User-agent: *\nDisallow: /\n"
            : implode("\n", [
                'User-agent: *',
                'Disallow: /admin',
                'Disallow: /account',
                'Disallow: /cart',
                'Disallow: /checkout',
                'Disallow: /order/',
                'Disallow: /api/',
                '',
                "Sitemap: {$site}/sitemap.xml",
                '',
            ]);

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $xml = Cache::remember('seo:sitemap', now()->addHour(), function () {
            $site = config('shop.frontend_url');
            $urls = array_map(fn (string $path) => ['loc' => $site.$path, 'lastmod' => null], self::STATIC_PAGES);

            Category::query()->where('is_active', true)->orderBy('id')->get(['slug', 'updated_at'])
                ->each(function (Category $c) use (&$urls, $site) {
                    $urls[] = ['loc' => $site.'/collections/'.rawurlencode($c->slug), 'lastmod' => $c->updated_at];
                });

            Product::query()->where('is_active', true)->orderBy('id')->get(['slug', 'updated_at'])
                ->each(function (Product $p) use (&$urls, $site) {
                    $urls[] = ['loc' => $site.'/product/'.rawurlencode($p->slug), 'lastmod' => $p->updated_at];
                });

            $entries = array_map(fn (array $u) => '  <url><loc>'.e($u['loc']).'</loc>'
                .($u['lastmod'] ? '<lastmod>'.$u['lastmod']->toAtomString().'</lastmod>' : '')
                .'</url>', $urls);

            return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
                .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
                .implode("\n", $entries)."\n"
                .'</urlset>'."\n";
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
