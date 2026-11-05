<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\QuickCommerce\QuickCommerceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuickCommerceOpsController extends Controller
{
    public function __construct(
        protected QuickCommerceService $quickService
    ) {}

    /**
     * Realtime Operations & Mission Control Dashboard (/admin/quick-commerce).
     */
    public function index(Request $request): View
    {
        $opsData = $this->quickService->getOpsDashboardData();

        return view('admin.quick-commerce.index', [
            'ops' => $opsData,
        ]);
    }

    /**
     * Polling JSON Feed for live Ops Control Room wall screen.
     */
    public function apiFeed(Request $request): JsonResponse
    {
        $opsData = $this->quickService->getOpsDashboardData();

        return response()->json([
            'status' => 'success',
            'data' => $opsData,
        ]);
    }
}
