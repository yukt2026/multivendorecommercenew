<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\Product;
use App\Models\Store;
use App\Services\Seo\SeoService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public const CHUNK_SIZE = 5000;

    public function __construct(
        protected SeoService $seoService
    ) {}

    /**
     * Main Sitemap Index (/sitemap.xml).
     */
    public function index(): Response
    {
        $productCount = Product::where('status', ProductStatus::ACTIVE)
            ->where('noindex', false)
            ->count();

        $chunks = max(1, (int) ceil($productCount / self::CHUNK_SIZE));

        $sitemaps = [];

        for ($i = 1; $i <= $chunks; $i++) {
            $sitemaps[] = [
                'loc' => url("/sitemap-products-{$i}.xml"),
                'lastmod' => now()->toAtomString(),
            ];
        }

        $sitemaps[] = [
            'loc' => url('/sitemap-categories.xml'),
            'lastmod' => now()->toAtomString(),
        ];
        $sitemaps[] = [
            'loc' => url('/sitemap-brands.xml'),
            'lastmod' => now()->toAtomString(),
        ];
        $sitemaps[] = [
            'loc' => url('/sitemap-stores.xml'),
            'lastmod' => now()->toAtomString(),
        ];
        $sitemaps[] = [
            'loc' => url('/sitemap-pages.xml'),
            'lastmod' => now()->toAtomString(),
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($sitemaps as $sm) {
            $xml .= "  <sitemap>\n";
            $xml .= "    <loc>" . htmlspecialchars($sm['loc'], ENT_XML1) . "</loc>\n";
            $xml .= "    <lastmod>{$sm['lastmod']}</lastmod>\n";
            $xml .= "  </sitemap>\n";
        }

        $xml .= '</sitemapindex>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    /**
     * Chunked Products Sub-Sitemap (/sitemap-products-{chunk}.xml or /sitemap-products.xml).
     */
    public function products(Request $request, int $chunk = 1): Response
    {
        $chunk = max(1, $chunk);
        $offset = ($chunk - 1) * self::CHUNK_SIZE;

        $products = Product::where('status', ProductStatus::ACTIVE)
            ->where('noindex', false)
            ->orderBy('id', 'asc')
            ->offset($offset)
            ->limit(self::CHUNK_SIZE)
            ->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        foreach ($products as $p) {
            $loc = route('product.show', $p->slug);
            $lastmod = ($p->updated_at ?? now())->toAtomString();
            $image = $p->featured_image_url ?: ($p->images[0] ?? null);

            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($loc, ENT_XML1) . "</loc>\n";
            $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
            $xml .= "    <changefreq>daily</changefreq>\n";
            $xml .= "    <priority>0.8</priority>\n";
            if ($image) {
                $xml .= "    <image:image>\n";
                $xml .= "      <image:loc>" . htmlspecialchars((string) $image, ENT_XML1) . "</image:loc>\n";
                $xml .= "      <image:title>" . htmlspecialchars((string) $p->title, ENT_XML1) . "</image:title>\n";
                $xml .= "    </image:image>\n";
            }
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    /**
     * Categories Sub-Sitemap (/sitemap-categories.xml).
     */
    public function categories(): Response
    {
        $categories = Category::where('is_active', true)->orderBy('id')->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($categories as $c) {
            $loc = route('category.show', $c->slug);
            $lastmod = ($c->updated_at ?? now())->toAtomString();

            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($loc, ENT_XML1) . "</loc>\n";
            $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
            $xml .= "    <changefreq>weekly</changefreq>\n";
            $xml .= "    <priority>0.7</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    /**
     * Brands Sub-Sitemap (/sitemap-brands.xml).
     */
    public function brands(): Response
    {
        $brands = Brand::orderBy('id')->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($brands as $b) {
            $loc = route('brand.show', $b->slug);
            $lastmod = ($b->updated_at ?? now())->toAtomString();

            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($loc, ENT_XML1) . "</loc>\n";
            $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
            $xml .= "    <changefreq>weekly</changefreq>\n";
            $xml .= "    <priority>0.6</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    /**
     * Stores Sub-Sitemap (/sitemap-stores.xml).
     */
    public function stores(): Response
    {
        $stores = Store::where('status', 'active')->orderBy('id')->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($stores as $s) {
            $loc = route('store.show', $s->slug);
            $lastmod = ($s->updated_at ?? now())->toAtomString();

            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($loc, ENT_XML1) . "</loc>\n";
            $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
            $xml .= "    <changefreq>daily</changefreq>\n";
            $xml .= "    <priority>0.7</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    /**
     * Static & CMS Pages Sub-Sitemap (/sitemap-pages.xml).
     */
    public function pages(): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Static core routes
        $core = [
            ['url' => url('/'), 'priority' => '1.0', 'changefreq' => 'daily'],
            ['url' => route('quick.index'), 'priority' => '0.9', 'changefreq' => 'daily'],
            ['url' => route('stores.index'), 'priority' => '0.8', 'changefreq' => 'weekly'],
        ];

        foreach ($core as $c) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($c['url'], ENT_XML1) . "</loc>\n";
            $xml .= "    <lastmod>" . now()->toAtomString() . "</lastmod>\n";
            $xml .= "    <changefreq>{$c['changefreq']}</changefreq>\n";
            $xml .= "    <priority>{$c['priority']}</priority>\n";
            $xml .= "  </url>\n";
        }

        // Active CMS pages
        $pages = CmsPage::where('is_active', true)->get();
        foreach ($pages as $p) {
            $loc = route('pages.show', $p->slug);
            $lastmod = ($p->updated_at ?? now())->toAtomString();

            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($loc, ENT_XML1) . "</loc>\n";
            $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
            $xml .= "    <changefreq>monthly</changefreq>\n";
            $xml .= "    <priority>0.5</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    /**
     * Dynamic robots.txt (/robots.txt).
     */
    public function robots(): Response
    {
        $content = $this->seoService->getRobotsTxt();

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}
