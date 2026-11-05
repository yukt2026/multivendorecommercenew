<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Shipping Label - {{ $fo->fulfilment_order_number }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #0f172a;
            margin: 0;
            padding: 10px;
        }
        .label-container {
            border: 2px solid #000;
            padding: 12px;
            max-width: 400px;
            margin: 0 auto;
        }
        .header {
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }
        .barcode {
            font-family: monospace;
            font-size: 20px;
            letter-spacing: 4px;
            text-align: center;
            font-weight: bold;
            margin: 10px 0;
            border: 1px dashed #000;
            padding: 6px;
            background: #f8fafc;
        }
        .section {
            border-bottom: 1px solid #000;
            padding: 6px 0;
        }
    </style>
</head>
<body>

    <div class="label-container">
        <table width="100%" class="header">
            <tr>
                <td>
                    <div style="font-size: 16px; font-weight: 900; letter-spacing: -0.5px;">ONESTALL CARGO</div>
                    <div style="font-size: 9px; font-weight: bold;">SURFACE / HYPERLOCAL DISPATCH</div>
                </td>
                <td style="text-align: right;">
                    <div style="font-size: 12px; font-weight: bold; border: 1px solid #000; padding: 2px 6px; display: inline-block;">
                        {{ $fo->fulfilment_type === \App\Enums\FulfilmentType::QUICK ? '⚡ QUICK 30M' : 'STANDARD' }}
                    </div>
                </td>
            </tr>
        </table>

        <div class="barcode">
            *{{ $fo->fulfilment_order_number }}*
        </div>

        <div class="section">
            <div style="font-size: 9px; font-weight: bold; text-transform: uppercase;">SHIP TO:</div>
            <div style="font-size: 13px; font-weight: bold; margin: 2px 0;">
                {{ $fo->order?->address_snapshot['name'] ?? 'Recipient' }}
            </div>
            <div>{{ $fo->order?->address_snapshot['line1'] ?? '' }}</div>
            <div style="font-size: 13px; font-weight: 900; margin-top: 2px;">
                {{ $fo->order?->address_snapshot['city'] ?? '' }} - {{ $fo->order?->address_snapshot['pincode'] ?? '' }}
            </div>
            <div>Phone: {{ $fo->order?->address_snapshot['phone'] ?? 'N/A' }}</div>
        </div>

        <div class="section">
            <table width="100%">
                <tr>
                    <td width="50%">
                        <div style="font-size: 8px; color: #64748b;">PAYMENT MODE:</div>
                        <div style="font-size: 12px; font-weight: bold;">
                            {{ strtoupper($fo->order?->payment_method->value ?? 'ONLINE') }}
                        </div>
                    </td>
                    <td width="50%" style="text-align: right;">
                        <div style="font-size: 8px; color: #64748b;">COLLECT ON DELIVERY:</div>
                        <div style="font-size: 14px; font-weight: 900;">
                            {{ $fo->order?->payment_method === \App\Enums\PaymentMethod::COD ? format_currency((float) $fo->total) : '₹ 0.00 (PREPAID)' }}
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div style="padding-top: 6px; font-size: 9px;">
            <div style="font-weight: bold;">SHIPPER / RETURN TO:</div>
            <div>{{ $fo->store?->name ?? $fo->seller?->business_name }}</div>
            <div>Bengaluru, KA - OneStall Logistics Network</div>
        </div>
    </div>

</body>
</html>
