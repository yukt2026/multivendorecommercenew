<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SeoService
{
    /**
     * Resolve complete SEO metadata payload for any page/entity.
     */
    public function resolveMetadata(
        string $type,
        mixed $model = null,
        array $overrides = [],
        ?Request $request = null
    ): array {
        $request = $request ?? request();
        $siteName = (string) setting('name', 'OneStall');
        $defaultTitle = (string) setting('meta_title', "{$siteName} - Smart Omnichannel & Quick Commerce Marketplace");
        $defaultDesc = (string) setting('meta_description', "Shop groceries, electronics, and daily essentials with instant 10-30 min quick delivery or nationwide cargo shipping.");
        $defaultOgImage = (string) setting('default_og_image', asset('images/og-default.jpg'));

        $title = $overrides['title'] ?? null;
        $description = $overrides['description'] ?? null;
        $ogImage = $overrides['og_image'] ?? null;
        $canonical = $overrides['canonical'] ?? null;
        $ogType = $overrides['og_type'] ?? 'website';
        $jsonLdSchemas = [];

        // Always include Organization schema
        $jsonLdSchemas[] = $this->organizationSchema();

        switch ($type) {
            case 'product':
                if ($model instanceof Product) {
                    $title = $title ?? ($model->meta_title ?: "{$model->title} - Buy Online at Best Price in India | {$siteName}");
                    $description = $description ?? ($model->meta_description ?: Str::limit(strip_tags((string) $model->description), 160));
                    $ogImage = $ogImage ?? ($model->og_image ?: ($model->featured_image_url ?: $defaultOgImage));
                    $canonical = $canonical ?? ($model->canonical_url ?: route('product.show', $model->slug));
                    $ogType = 'product';

                    // Breadcrumb schema
                    $crumbs = [['name' => 'Home', 'url' => url('/')]];
                    if ($model->category) {
                        $crumbs[] = ['name' => $model->category->name, 'url' => route('category.show', $model->category->slug)];
                    }
                    $crumbs[] = ['name' => $model->title, 'url' => route('product.show', $model->slug)];
                    $jsonLdSchemas[] = $this->breadcrumbSchema($crumbs);

                    // Product schema
                    $jsonLdSchemas[] = $this->productSchema($model);
                }
                break;

            case 'category':
                if ($model instanceof Category) {
                    $title = $title ?? ($model->meta_title ?: "{$model->name} - Buy {$model->name} Online at Best Prices | {$siteName}");
                    $description = $description ?? ($model->meta_description ?: "Explore wide collection of genuine {$model->name} with superfast delivery and great offers on {$siteName}.");
                    $ogImage = $ogImage ?? ($model->og_image ?: ($model->image ?: $defaultOgImage));
                    $canonical = $canonical ?? ($model->canonical_url ?: route('category.show', $model->slug));

                    $crumbs = [['name' => 'Home', 'url' => url('/')]];
                    if ($model->parent) {
                        $crumbs[] = ['name' => $model->parent->name, 'url' => route('category.show', $model->parent->slug)];
                    }
                    $crumbs[] = ['name' => $model->name, 'url' => route('category.show', $model->slug)];
                    $jsonLdSchemas[] = $this->breadcrumbSchema($crumbs);
                }
                break;

            case 'brand':
                if ($model instanceof Brand) {
                    $title = $title ?? ($model->meta_title ?: "{$model->name} Products - Genuine {$model->name} Collection Online | {$siteName}");
                    $description = $description ?? ($model->meta_description ?: "Shop authentic {$model->name} products on {$siteName}. Best offers, express shipping, and manufacturer warranty.");
                    $ogImage = $ogImage ?? ($model->og_image ?: ($model->logo ?: $defaultOgImage));
                    $canonical = $canonical ?? ($model->canonical_url ?: route('brand.show', $model->slug));

                    $jsonLdSchemas[] = $this->breadcrumbSchema([
                        ['name' => 'Home', 'url' => url('/')],
                        ['name' => 'Brands', 'url' => route('brand.show', $model->slug)],
                        ['name' => $model->name, 'url' => route('brand.show', $model->slug)],
                    ]);
                }
                break;

            case 'store':
                if ($model instanceof Store) {
                    $title = $title ?? ($model->meta_title ?: "{$model->name} - Storefront & Local Deals | {$siteName}");
                    $description = $description ?? ($model->meta_description ?: ($model->description ? Str::limit(strip_tags($model->description), 160) : "Shop genuine items from {$model->name} on {$siteName}. Fast quick delivery and pickup available."));
                    $ogImage = $ogImage ?? ($model->og_image ?: ($model->banner ?: ($model->logo ?: $defaultOgImage)));
                    $canonical = $canonical ?? ($model->canonical_url ?: route('store.show', $model->slug));
                    $ogType = 'profile';

                    $jsonLdSchemas[] = $this->breadcrumbSchema([
                        ['name' => 'Home', 'url' => url('/')],
                        ['name' => 'Stores', 'url' => route('stores.index')],
                        ['name' => $model->name, 'url' => route('store.show', $model->slug)],
                    ]);
                }
                break;

            case 'page':
                if ($model instanceof CmsPage) {
                    $title = $title ?? ($model->meta_title ?: "{$model->title} | {$siteName}");
                    $description = $description ?? ($model->meta_description ?: Str::limit(strip_tags((string) $model->content), 160));
                    $canonical = $canonical ?? route('pages.show', $model->slug);

                    $jsonLdSchemas[] = $this->breadcrumbSchema([
                        ['name' => 'Home', 'url' => url('/')],
                        ['name' => $model->title, 'url' => route('pages.show', $model->slug)],
                    ]);
                }
                break;

            default:
                $title = $title ?? $defaultTitle;
                $description = $description ?? $defaultDesc;
                $ogImage = $ogImage ?? $defaultOgImage;
                $canonical = $canonical ?? $request->url();
                break;
        }

        // Detect if page is filtered, sorted, searched, or paginated beyond clean page
        $isFilteredOrSearched = $this->isDuplicateOrFiltered($request);
        $explicitNoindex = ($model instanceof Product && $model->noindex) || ($overrides['noindex'] ?? false);
        $noindex = $explicitNoindex || $isFilteredOrSearched;

        // Clean canonical: strip marketing tags like utm_*, ref, gclid, fbclid
        $cleanCanonical = $canonical ?? $this->stripTrackingParams($request->fullUrl());

        return [
            'title' => $title ?? $defaultTitle,
            'description' => $description ?? $defaultDesc,
            'canonical' => $cleanCanonical,
            'noindex' => $noindex,
            'robots' => $noindex ? 'noindex, follow' : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
            'og' => [
                'title' => $title ?? $defaultTitle,
                'description' => $description ?? $defaultDesc,
                'image' => $ogImage ?? $defaultOgImage,
                'url' => $cleanCanonical,
                'type' => $ogType,
                'site_name' => $siteName,
            ],
            'twitter' => [
                'card' => 'summary_large_image',
                'title' => $title ?? $defaultTitle,
                'description' => $description ?? $defaultDesc,
                'image' => $ogImage ?? $defaultOgImage,
            ],
            'jsonLd' => array_values(array_filter($jsonLdSchemas)),
            'google_verification' => setting('google_site_verification'),
            'bing_verification' => setting('bing_site_verification'),
        ];
    }

    /**
     * Determine if current request should be noindexed (filtered, searched, sorted).
     */
    public function isDuplicateOrFiltered(Request $request): bool
    {
        // Faceted navigation, price filtering, attribute filtering, search terms
        $filteredParams = ['q', 'sort', 'order', 'min_price', 'max_price', 'rating', 'filter', 'in_stock'];
        foreach ($filteredParams as $param) {
            if ($request->filled($param)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clean tracking parameters from URL to produce pure canonical URL.
     */
    public function stripTrackingParams(string $url): string
    {
        $parsed = parse_url($url);
        if (empty($parsed['query'])) {
            return $url;
        }

        parse_str($parsed['query'], $query);
        $trackingParams = [
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
            'ref', 'gclid', 'fbclid', 'msclkid', 'yclid', 'source',
        ];

        foreach ($trackingParams as $p) {
            unset($query[$p]);
        }

        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = $parsed['path'] ?? '';
        $newQuery = !empty($query) ? '?' . http_build_query($query) : '';

        return "{$scheme}{$host}{$port}{$path}{$newQuery}";
    }

    /**
     * Generate Schema.org Organization structured data.
     */
    public function organizationSchema(): array
    {
        $siteName = (string) setting('name', 'OneStall');
        $siteUrl = url('/');
        $logo = (string) setting('logo', asset('images/logo.png'));
        $phone = (string) setting('support_phone', '+91-1800-123-4567');
        $email = (string) setting('support_email', 'support@onestall.com');

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $siteName,
            'url' => $siteUrl,
            'logo' => $logo,
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'telephone' => $phone,
                'email' => $email,
                'contactType' => 'Customer Support',
                'areaServed' => 'IN',
                'availableLanguage' => ['English', 'Hindi'],
            ],
            'sameAs' => array_values(array_filter([
                setting('social_facebook'),
                setting('social_instagram'),
                setting('social_twitter'),
                setting('social_linkedin'),
            ])),
        ];
    }

    /**
     * Generate Schema.org Product structured data.
     */
    public function productSchema(Product $product): array
    {
        $images = [];
        if ($product->featured_image_url) {
            $images[] = $product->featured_image_url;
        }
        if (is_array($product->images)) {
            foreach ($product->images as $img) {
                if (is_string($img) && !in_array($img, $images, true)) {
                    $images[] = $img;
                }
            }
        }
        if (empty($images)) {
            $images[] = asset('images/product-placeholder.png');
        }

        $price = '0.00';
        $sku = 'OS-PRD-' . $product->id;
        $inStock = true;

        if ($product->relationLoaded('variants') && $product->variants->isNotEmpty()) {
            $firstVariant = $product->variants->first();
            $price = number_format((float) ($firstVariant->selling_price ?? ($firstVariant->price ?? 0)), 2, '.', '');
            $sku = (string) ($firstVariant->sku ?? $sku);
        }

        $sellerName = $product->seller?->business_name ?? setting('name', 'OneStall');

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->title,
            'image' => $images,
            'description' => Str::limit(strip_tags((string) $product->description), 300),
            'sku' => $sku,
            'offers' => [
                '@type' => 'Offer',
                'url' => route('product.show', $product->slug),
                'priceCurrency' => 'INR',
                'price' => $price,
                'itemCondition' => 'https://schema.org/NewCondition',
                'availability' => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'seller' => [
                    '@type' => 'Organization',
                    'name' => $sellerName,
                ],
            ],
        ];

        if ($product->brand) {
            $schema['brand'] = [
                '@type' => 'Brand',
                'name' => $product->brand->name,
            ];
        }

        return $schema;
    }

    /**
     * Generate Schema.org BreadcrumbList structured data.
     */
    public function breadcrumbSchema(array $crumbs): array
    {
        $items = [];
        $pos = 1;

        foreach ($crumbs as $crumb) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $pos++,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * Generate Schema.org FAQPage structured data.
     */
    public function faqSchema(array $faqs): array
    {
        $entities = [];
        foreach ($faqs as $faq) {
            $entities[] = [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer'],
                ],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }

    /**
     * Generate robots.txt content dynamically with admin setting override.
     */
    public function getRobotsTxt(): string
    {
        $custom = setting('robots_txt');
        if (!empty($custom)) {
            return trim($custom);
        }

        $sitemapUrl = url('/sitemap.xml');

        return <<<TXT
User-agent: *
Disallow: /admin/
Disallow: /seller/
Disallow: /cart
Disallow: /checkout
Disallow: /account
Disallow: /login
Disallow: /register
Disallow: /api/
Disallow: /*?*q=
Disallow: /*?*sort=
Disallow: /*?*order=
Disallow: /*?*filter=
Disallow: /*?*min_price=
Disallow: /*?*max_price=

Allow: /
Allow: /p/
Allow: /c/
Allow: /b/
Allow: /store/
Allow: /page/
Allow: /stores
Allow: /quick

Sitemap: {$sitemapUrl}
TXT;
    }
}
