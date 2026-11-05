<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tax Invoice - {{ $order->order_number }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
        }
        .header {
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #1e1b4b;
        }
        .invoice-title {
            font-size: 18px;
            font-weight: bold;
            color: #4f46e5;
            text-align: right;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 25px;
        }
        .meta-table td {
            vertical-align: top;
            width: 50%;
        }
        .box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 10px;
        }
        .box-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 6px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            background-color: #4f46e5;
            color: #ffffff;
            font-weight: bold;
            text-align: left;
            padding: 8px 10px;
            font-size: 11px;
            text-transform: uppercase;
        }
        .items-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11px;
        }
        .items-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .totals-table {
            width: 40%;
            margin-left: auto;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 6px 10px;
            font-size: 11px;
        }
        .totals-table tr.grand-total td {
            border-top: 2px solid #4f46e5;
            border-bottom: 2px solid #4f46e5;
            font-weight: bold;
            font-size: 13px;
            color: #1e1b4b;
        }
        .footer {
            margin-top: 40px;
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
            text-align: center;
            font-size: 10px;
            color: #94a3b8;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            background-color: #e0e7ff;
            color: #3730a3;
        }
    </style>
</head>
<body>

    <table class="header" width="100%">
        <tr>
            <td>
                <div class="company-name">{{ setting('name', 'OneStall') }}</div>
                <div style="color: #64748b; font-size: 11px;">Smart Omnichannel Commerce Platform</div>
                <div style="font-size: 10px; color: #64748b;">GSTIN: {{ setting('platform_gstin', '29AAAAA0000A1Z5') }}</div>
            </td>
            <td style="text-align: right;">
                <div class="invoice-title">TAX INVOICE</div>
                <div style="font-size: 11px; font-weight: bold;">Invoice #: INV-{{ $order->order_number }}</div>
                <div style="font-size: 11px; color: #64748b;">Date: {{ $order->created_at->format('d M Y, h:i A') }}</div>
                <div style="margin-top: 4px;">
                    <span class="badge">{{ strtoupper($order->payment_method->value ?? 'ONLINE') }}</span>
                    <span class="badge" style="background-color: #dcfce7; color: #166534;">{{ strtoupper($order->payment_status->value ?? 'PAID') }}</span>
                </div>
            </td>
        </tr>
    </table>

    <table class="meta-table">
        <tr>
            <td style="padding-right: 15px;">
                <div class="box">
                    <div class="box-title">Billed &amp; Shipped To:</div>
                    <strong>{{ $order->address_snapshot['name'] ?? $order->customer?->user?->name ?? 'Valued Customer' }}</strong><br>
                    {{ $order->address_snapshot['line1'] ?? '' }}<br>
                    {{ $order->address_snapshot['city'] ?? '' }}, {{ $order->address_snapshot['state'] ?? '' }} - {{ $order->address_snapshot['pincode'] ?? '' }}<br>
                    Phone: {{ $order->address_snapshot['phone'] ?? $order->customer?->user?->phone ?? 'N/A' }}
                </div>
            </td>
            <td style="padding-left: 15px;">
                <div class="box">
                    <div class="box-title">Order Information:</div>
                    <strong>Order Reference:</strong> {{ $order->order_number }}<br>
                    <strong>Total Packages:</strong> {{ $order->fulfilmentOrders->count() }} split package(s)<br>
                    <strong>Payment Gateway:</strong> {{ strtoupper($order->payments->first()?->gateway ?? 'Online') }}<br>
                    <strong>Order Status:</strong> {{ $order->status->label() }}
                </div>
            </td>
        </tr>
    </table>

    <!-- Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="45%">Item Description</th>
                <th width="15%">Fulfilled By</th>
                <th width="10%" style="text-align: center;">Qty</th>
                <th width="12%" style="text-align: right;">Unit Price</th>
                <th width="13%" style="text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            @php $itemIndex = 1; @endphp
            @foreach($order->fulfilmentOrders as $fo)
                @foreach($fo->items as $item)
                    <tr>
                        <td>{{ $itemIndex++ }}</td>
                        <td>
                            <strong>{{ $item->title }}</strong>
                            @if($item->variant_name && $item->variant_name !== 'Default')
                                <div style="color: #64748b; font-size: 10px;">Variant: {{ $item->variant_name }}</div>
                            @endif
                            <div style="color: #94a3b8; font-size: 9px;">SKU: {{ $item->sku }}</div>
                        </td>
                        <td>
                            <div style="font-weight: 600;">{{ $fo->store?->name ?? $fo->seller?->business_name ?? 'OneStall Warehouse' }}</div>
                            <div style="font-size: 9px; color: #4f46e5;">{{ $fo->fulfilment_type->label() }}</div>
                        </td>
                        <td style="text-align: center;">{{ $item->qty }}</td>
                        <td style="text-align: right;">{{ format_currency((float) $item->price) }}</td>
                        <td style="text-align: right; font-weight: 600;">{{ format_currency((float) $item->total) }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    <!-- Totals Table -->
    <table class="totals-table">
        <tr>
            <td style="color: #64748b;">Subtotal:</td>
            <td style="text-align: right; font-weight: 600;">{{ format_currency((float) $order->subtotal) }}</td>
        </tr>
        <tr>
            <td style="color: #64748b;">Delivery Fee:</td>
            <td style="text-align: right;">{{ (float) $order->delivery_fee == 0 ? 'FREE' : format_currency((float) $order->delivery_fee) }}</td>
        </tr>
        @if((float) $order->discount > 0)
        <tr>
            <td style="color: #16a34a;">Coupon Savings:</td>
            <td style="text-align: right; color: #16a34a;">-{{ format_currency((float) $order->discount) }}</td>
        </tr>
        @endif
        @if((float) $order->tax > 0)
        <tr>
            <td style="color: #64748b;">GST &amp; Taxes:</td>
            <td style="text-align: right;">{{ format_currency((float) $order->tax) }}</td>
        </tr>
        @endif
        <tr class="grand-total">
            <td>Total Payable:</td>
            <td style="text-align: right; color: #4f46e5;">{{ format_currency((float) $order->total) }}</td>
        </tr>
    </table>

    <div class="footer">
        This is a computer-generated invoice and requires no physical signature.<br>
        For inquiries, contact support at <strong>support@onestall.in</strong> | Thank you for shopping with {{ setting('name', 'OneStall') }}!
    </div>

</body>
</html>
