<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Packing Slip - {{ $fo->fulfilment_order_number }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 12px;
            color: #1e293b;
            margin: 0;
            padding: 20px;
        }
        .header {
            border-bottom: 2px dashed #94a3b8;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }
        .title {
            font-size: 20px;
            font-weight: bold;
            color: #0f172a;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .table th, .table td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            text-align: left;
        }
        .table th {
            background-color: #f1f5f9;
            font-size: 11px;
            text-transform: uppercase;
        }
        .box {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 8px 12px;
            background-color: #f8fafc;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

    <table width="100%" class="header">
        <tr>
            <td>
                <div class="title">PACKING SLIP / PICK LIST</div>
                <div style="font-size: 11px; color: #64748b;">Package #: <strong>{{ $fo->fulfilment_order_number }}</strong></div>
                <div style="font-size: 11px; color: #64748b;">Parent Order #: <strong>{{ $fo->order?->order_number }}</strong></div>
            </td>
            <td style="text-align: right;">
                <div style="font-weight: bold; font-size: 14px;">{{ $fo->store?->name ?? $fo->seller?->business_name }}</div>
                <div style="font-size: 11px; color: #64748b;">Channel: <strong>{{ $fo->fulfilment_type->label() }}</strong></div>
                <div style="font-size: 11px; color: #64748b;">Date: {{ now()->format('d M Y, h:i A') }}</div>
            </td>
        </tr>
    </table>

    <table width="100%" style="margin-bottom: 15px;">
        <tr>
            <td width="50%" style="vertical-align: top; padding-right: 10px;">
                <div class="box">
                    <div style="font-size: 10px; font-weight: bold; text-transform: uppercase; color: #64748b; margin-bottom: 4px;">Fulfillment Source:</div>
                    <strong>{{ $fo->store?->name ?? 'Main Outlet' }}</strong><br>
                    Location: {{ $fo->inventoryLocation?->name ?? 'Shelf Area' }} ({{ $fo->inventoryLocation?->code ?? 'LOC-01' }})
                </div>
            </td>
            <td width="50%" style="vertical-align: top; padding-left: 10px;">
                <div class="box">
                    <div style="font-size: 10px; font-weight: bold; text-transform: uppercase; color: #64748b; margin-bottom: 4px;">Delivery Destination:</div>
                    <strong>{{ $fo->order?->address_snapshot['name'] ?? 'Customer' }}</strong><br>
                    {{ $fo->order?->address_snapshot['line1'] ?? '' }}, {{ $fo->order?->address_snapshot['city'] ?? '' }} - {{ $fo->order?->address_snapshot['pincode'] ?? '' }}<br>
                    Contact: {{ $fo->order?->address_snapshot['phone'] ?? 'N/A' }}
                </div>
            </td>
        </tr>
    </table>

    <table class="table">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="20%">SKU / Barcode</th>
                <th width="55%">Item Details</th>
                <th width="10%" style="text-align: center;">Picked [ ]</th>
                <th width="10%" style="text-align: right;">Qty</th>
            </tr>
        </thead>
        <tbody>
            @foreach($fo->items as $idx => $item)
            <tr>
                <td>{{ $idx + 1 }}</td>
                <td style="font-family: monospace; font-weight: bold;">{{ $item->sku }}</td>
                <td>
                    <strong>{{ $item->title }}</strong>
                    @if($item->variant_name && $item->variant_name !== 'Default')
                        <span style="color: #64748b; font-size: 10px;">- {{ $item->variant_name }}</span>
                    @endif
                </td>
                <td style="text-align: center; font-size: 16px;">[ &nbsp; ]</td>
                <td style="text-align: right; font-weight: bold; font-size: 13px;">{{ $item->qty }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div style="margin-top: 30px; border-top: 1px solid #cbd5e1; padding-top: 10px; font-size: 10px; color: #64748b; display: flex; justify-content: space-between;">
        <span>Picker Signature: _________________________</span>
        <span style="float: right;">Packer Signature: _________________________</span>
    </div>

</body>
</html>
