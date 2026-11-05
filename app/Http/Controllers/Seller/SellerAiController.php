<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\AiActionLog;
use App\Models\ProductVariant;
use App\Services\AI\Contracts\DemandForecastingInterface;
use App\Services\AI\Contracts\SellerAiAssistantInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SellerAiController extends Controller
{
    public function __construct(
        protected SellerAiAssistantInterface $sellerAiAssistant,
        protected DemandForecastingInterface $forecastingService
    ) {}

    /**
     * AI Catalogue Assistant: Generate title, SEO description, and bullet highlights.
     */
    public function generateCatalogue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'brand' => 'nullable|string|max:100',
            'key_features' => 'nullable|array',
            'key_features.*' => 'string|max:255',
            'tone' => 'nullable|string|in:professional,engaging,minimalist',
        ]);

        $seller = Auth::user()?->seller;
        if (!$seller) {
            return response()->json(['error' => 'Seller profile not found.'], 403);
        }

        $result = $this->sellerAiAssistant->generateCatalogueContent(
            seller: $seller,
            title: $validated['title'],
            category: $validated['category'],
            brand: $validated['brand'] ?? null,
            keyFeatures: $validated['key_features'] ?? [],
            tone: $validated['tone'] ?? 'professional'
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * AI Pricing Assistant: Benchmark against market and recommend optimal price.
     */
    public function suggestPricing(Request $request, ProductVariant $variant): JsonResponse
    {
        $seller = Auth::user()?->seller;
        if (!$seller || (int) $variant->product?->seller_id !== (int) $seller->id) {
            return response()->json(['error' => 'Unauthorized: This variant does not belong to your store.'], 403);
        }

        $result = $this->sellerAiAssistant->suggestPricing($seller, $variant);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * AI Inventory & Demand Forecasting: Days-to-stockout and reorder suggestions.
     */
    public function demandForecast(Request $request): JsonResponse
    {
        $seller = Auth::user()?->seller;
        if (!$seller) {
            return response()->json(['error' => 'Seller profile not found.'], 403);
        }

        $leadTimeDays = (int) $request->input('lead_time_days', 7);
        $forecasts = $this->forecastingService->generateForecastForSeller($seller, $leadTimeDays);

        return response()->json([
            'success' => true,
            'count' => $forecasts->count(),
            'data' => $forecasts->map(fn ($f) => [
                'id' => $f->id,
                'variant_id' => $f->variant_id,
                'variant_name' => $f->variant?->name,
                'product_title' => $f->variant?->product?->title,
                'sku' => $f->variant?->sku,
                'daily_sales_velocity' => (float) $f->daily_sales_velocity,
                'days_to_stockout' => (float) $f->days_to_stockout,
                'predicted_demand_30d' => (float) $f->predicted_demand_30d,
                'recommended_reorder_qty' => (int) $f->recommended_reorder_qty,
                'stock_status' => $f->stock_status,
                'metadata' => $f->forecast_metadata,
            ]),
        ]);
    }

    /**
     * AI Audit Logs: View actions performed for this merchant.
     */
    public function actionLogs(Request $request): JsonResponse
    {
        $seller = Auth::user()?->seller;
        if (!$seller) {
            return response()->json(['error' => 'Seller profile not found.'], 403);
        }

        $logs = AiActionLog::where('seller_id', $seller->id)
            ->latest('id')
            ->paginate(15);

        return response()->json($logs);
    }
}
