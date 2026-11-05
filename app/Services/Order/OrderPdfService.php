<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Models\FulfilmentOrder;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class OrderPdfService
{
    /**
     * Generate Tax Invoice PDF for an order.
     */
    public function generateInvoicePdf(Order $order): Response
    {
        $order->loadMissing([
            'customer.user',
            'payments',
            'fulfilmentOrders.items.variant',
            'fulfilmentOrders.store',
            'fulfilmentOrders.seller',
        ]);

        $pdf = Pdf::loadView('pdf.invoice', [
            'order' => $order,
        ])->setPaper('a4', 'portrait');

        return $pdf->download("Tax-Invoice-{$order->order_number}.pdf");
    }

    /**
     * Generate Packing Slip PDF for a fulfilment package.
     */
    public function generatePackingSlipPdf(FulfilmentOrder $fo): Response
    {
        $fo->loadMissing([
            'order.customer.user',
            'items.variant',
            'store',
            'seller',
            'inventoryLocation',
        ]);

        $pdf = Pdf::loadView('pdf.packing-slip', [
            'fo' => $fo,
        ])->setPaper('a4', 'portrait');

        return $pdf->download("Packing-Slip-{$fo->fulfilment_order_number}.pdf");
    }

    /**
     * Generate Shipping Label PDF for courier / rider dispatch.
     */
    public function generateShippingLabelPdf(FulfilmentOrder $fo): Response
    {
        $fo->loadMissing([
            'order.customer.user',
            'store',
            'seller',
        ]);

        $pdf = Pdf::loadView('pdf.shipping-label', [
            'fo' => $fo,
        ])->setPaper([0, 0, 288, 432], 'portrait'); // Standard 4x6 inch thermal shipping label

        return $pdf->download("Shipping-Label-{$fo->fulfilment_order_number}.pdf");
    }
}
