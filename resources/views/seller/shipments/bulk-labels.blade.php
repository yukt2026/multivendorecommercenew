<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bulk Shipping Labels ({{ $shipments->count() }})</title>
    <style>
        @page {
            size: 4in 6in;
            margin: 0;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 0;
            background: #fff;
            color: #000;
        }
        .page {
            page-break-after: always;
            padding: 10px;
            box-sizing: border-box;
            height: 100vh;
        }
        .page:last-child {
            page-break-after: avoid;
        }
        .label-container {
            border: 2px solid #000;
            padding: 8px;
            height: calc(100% - 10px);
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #000;
            padding-bottom: 6px;
        }
        .logo { font-size: 16px; font-weight: 900; }
        .awb-box { text-align: center; margin: 10px 0; border-bottom: 2px solid #000; padding-bottom: 8px; }
        .barcode { font-family: monospace; font-size: 24px; letter-spacing: 5px; font-weight: bold; }
        .awb-text { font-size: 14px; font-weight: bold; margin-top: 4px; }
        .address-grid { border-bottom: 2px solid #000; padding-bottom: 8px; }
        .addr-title { font-weight: bold; font-size: 10px; text-transform: uppercase; }
        .details-grid { display: flex; justify-content: space-between; font-size: 10px; padding: 6px 0; border-bottom: 2px solid #000; }
        .cod-badge { font-size: 16px; font-weight: 900; text-align: center; padding: 6px; background: #000; color: #fff; margin-top: 4px; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="padding: 10px; background: #f4f4f5; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">
        <span style="font-weight: bold;">{{ $shipments->count() }} Shipping Labels Ready for Thermal Printing</span>
        <button onclick="window.print()" style="padding: 8px 16px; background: #000; color: #fff; font-weight: bold; border: none; border-radius: 4px; cursor: pointer;">Print All Labels</button>
    </div>

    @foreach($shipments as $shipment)
        @php
            $fo = $shipment->fulfilmentOrder;
            $order = $fo?->order;
            $dest = $order?->customerAddress;
            $snapshot = $order?->address_snapshot ?? [];
        @endphp
        <div class="page">
            <div class="label-container">
                <div class="header">
                    <div class="logo">ONESTALL CARGO</div>
                    <div style="text-align: right; font-size: 10px; font-weight: bold;">
                        {{ $shipment->courier }}<br>
                        <span style="text-transform: uppercase;">{{ $shipment->service ?? 'Surface' }}</span>
                    </div>
                </div>

                <div class="awb-box">
                    <div class="barcode">||| | ||||| |||| | |||</div>
                    <div class="awb-text">AWB: {{ $shipment->awb ?? $shipment->shipment_number }}</div>
                    <div style="font-size: 9px; color: #555;">Order #{{ $order?->order_number ?? 'N/A' }}</div>
                </div>

                <div class="address-grid">
                    <div class="addr-title">SHIP TO:</div>
                    <div style="font-size: 13px; font-weight: bold;">{{ $dest?->name ?? $snapshot['name'] ?? 'Customer' }}</div>
                    <div>{{ $dest?->line1 ?? $snapshot['line1'] ?? 'Address line' }}</div>
                    <div>{{ $dest?->city ?? $snapshot['city'] ?? 'City' }}, {{ $dest?->state ?? $snapshot['state'] ?? 'State' }}</div>
                    <div style="font-size: 14px; font-weight: 900; margin-top: 2px;">
                        PIN: {{ $dest?->pincode ?? $snapshot['pincode'] ?? '560001' }}
                    </div>
                    <div>Phone: {{ $dest?->phone ?? $snapshot['phone'] ?? '9876543210' }}</div>
                </div>

                <div class="details-grid">
                    <div>
                        <strong>Shipped By:</strong><br>
                        {{ $fo?->store?->name ?? $fo?->seller?->company_name ?? 'Seller' }}<br>
                        PIN: {{ $fo?->store?->pincode ?? '560038' }}
                    </div>
                    <div style="text-align: right;">
                        <strong>Weight:</strong> {{ $shipment->weight }} KG<br>
                        <strong>Items:</strong> {{ $fo?->items?->count() ?? 1 }}<br>
                        <strong>Date:</strong> {{ $shipment->created_at->format('d/m/Y') }}
                    </div>
                </div>

                @if($shipment->cod_amount > 0)
                    <div class="cod-badge">
                        COD AMOUNT: {{ format_currency($shipment->cod_amount) }}
                    </div>
                @else
                    <div style="text-align: center; font-weight: bold; padding: 4px; border: 1px dashed #000; margin-top: 4px;">
                        PREPAID - DO NOT COLLECT CASH
                    </div>
                @endif
            </div>
        </div>
    @endforeach
</body>
</html>
