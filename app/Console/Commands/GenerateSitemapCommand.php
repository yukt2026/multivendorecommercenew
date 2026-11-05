<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\Storefront\SitemapController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class GenerateSitemapCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sitemap:generate {--disk : Save directly to public directory}';

    /**
     * The console command description.
     */
    protected $description = 'Regenerate XML sitemaps for products, categories, brands, stores, and pages';

    /**
     * Execute the console command.
     */
    public function handle(SitemapController $controller): int
    {
        $this->info('Regenerating sitemaps...');

        $publicDir = public_path();
        if (!File::isDirectory($publicDir)) {
            File::makeDirectory($publicDir, 0755, true);
        }

        // 1. Root index
        $indexContent = $controller->index()->getContent();
        File::put($publicDir . '/sitemap.xml', $indexContent);
        $this->line('<info>[CREATED]</info> /sitemap.xml');

        // 2. Categories
        $catContent = $controller->categories()->getContent();
        File::put($publicDir . '/sitemap-categories.xml', $catContent);
        $this->line('<info>[CREATED]</info> /sitemap-categories.xml');

        // 3. Brands
        $brandContent = $controller->brands()->getContent();
        File::put($publicDir . '/sitemap-brands.xml', $brandContent);
        $this->line('<info>[CREATED]</info> /sitemap-brands.xml');

        // 4. Stores
        $storeContent = $controller->stores()->getContent();
        File::put($publicDir . '/sitemap-stores.xml', $storeContent);
        $this->line('<info>[CREATED]</info> /sitemap-stores.xml');

        // 5. Pages
        $pageContent = $controller->pages()->getContent();
        File::put($publicDir . '/sitemap-pages.xml', $pageContent);
        $this->line('<info>[CREATED]</info> /sitemap-pages.xml');

        // 6. Products (chunk 1)
        $req = new Request();
        $prodContent = $controller->products($req, 1)->getContent();
        File::put($publicDir . '/sitemap-products-1.xml', $prodContent);
        File::put($publicDir . '/sitemap-products.xml', $prodContent);
        $this->line('<info>[CREATED]</info> /sitemap-products.xml');

        // 7. Robots.txt
        $robotsContent = $controller->robots()->getContent();
        File::put($publicDir . '/robots.txt', $robotsContent);
        $this->line('<info>[CREATED]</info> /robots.txt');

        $this->info('Sitemaps regeneration completed successfully.');

        return Command::SUCCESS;
    }
}
