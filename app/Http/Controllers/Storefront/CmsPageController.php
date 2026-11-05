<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use Illuminate\Contracts\View\View;

class CmsPageController extends Controller
{
    /**
     * Display static CMS / policy page.
     */
    public function show(string $slug): View
    {
        $page = CmsPage::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return view('storefront.cms-page', [
            'page' => $page,
            'seoType' => 'page',
            'seoModel' => $page,
        ]);
    }
}
