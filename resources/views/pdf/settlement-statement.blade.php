<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settlement Statement - {{ $settlement->settlement_number }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
        }
        .header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .company-name {
            font-size: 22px;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: -0.5px;
        }
        .company-sub {
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .statement-title {
            font-size: 16px;
            font-weight: bold;
            color: #2563eb;
            text-align: right;
            text-transform: uppercase;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 20px;
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
            margin-bottom: 12px;
        }
        .box-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 6px;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .summary-table td {
            padding: 6px 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .summary-table td.label {
            color: #475569;
            font-weight: 500;
        }
        .summary-table td.amount {
            text-align: right;
            font-family: monospace;
            font-weight: bold;
        }
        .summary-table tr.total-row td {
            background-color: #f1f5f9;
            font-size: 13px;
            color: #0f172a;
            border-top: 2px solid #cbd5e1;
            border-bottom: 2px solid #cbd5e1;
            padding: 8px 12px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            background-color: #1e293b;
            color: #ffffff;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 6px 8px;
            text-align: left;
        }
        .items-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10px;
        }
        .items-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-paid { background-color: #dcfce7; color: #166534; }
        .badge-pending { background-color: #fef9c3; color: #854d0e; }
        .badge-processing { background-color: #e0e7ff; color: #3730a3; }
        .badge-on_hold { background-color: #fee2e2; color: #991b1b; }
        .footer {
            margin-top: 30px;
            border-top: 1px solid #e2e8f0;
            padding-top: 12px;
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>
    <table class="meta-table header">
        <tr>
            <td>
                <div class="company-name">{{ setting('name', 'OneStall') }} Marketplace</div>
                <div class="company-sub">Unified Merchant Commerce & Logistics Network</div>
            </td>
            <td>
                <div class="statement-title">Settlement Statement</div>
                <div style="text-align: right; font-size: 10px; color: #64748b; margin-top: 4px;">
                    Statement #: <strong>{{ $settlement->settlement_number }}</strong><br>
                    Generated: {{ now()->format('d M Y, h:i A') }}
                </div>
            </td>
        </tr>
    </table>

    <table class="meta-table">
        <tr>
            <td style="padding-right: 10px;">
                <div class="box">
                    <div class="box-title">Merchant Details</div>
                    <strong style="font-size: 12px;">{{ $settlement->seller?->store_name ?? 'N/A' }}</strong><br>
                    <span style="color: #64748b;">{{ $settlement->seller?->legal_name ?? $settlement->seller?->company_name }}</span><br>
                    Email: {{ $settlement->seller?->business_email ?? $settlement->seller?->user?->email }}<br>
                    Phone: {{ $settlement->seller?->business_phone ?? 'N/A' }}<br>
                    GSTIN: <strong>{{ $settlement->seller?->gstin ?? 'N/A' }}</strong> | PAN: <strong>{{ $settlement->seller?->pan ?? 'N/A' }}</strong>
                </div>
            </td>
            <td style="padding-left: 10px;">
                <div class="box">
                    <div class="box-title">Settlement Cycle &amp; Payout Target</div>
                    Period Start: <strong>{{ $settlement->period_start->format('d M Y') }}</strong><br>
                    Period End: <strong>{{ $settlement->period_end->format('d M Y') }}</strong><br>
                    Status: 
                    <span class="badge badge-{{ $settlement->status->value }}">{{ $settlement->status->label() }}</span><br>
                    @if($settlement->reference)
                        Bank UTR / Ref: <strong style="color: #166534; font-family: monospace;">{{ $settlement->reference }}</strong><br>
                        Settled At: {{ $settlement->paid_at ? $settlement->paid_at->format('d M Y, h:i A') : 'N/A' }}
                    @elseif($settlement->status->value === 'on_hold')
                        <span style="color: #991b1b;">Hold Reason: {{ $settlement->hold_reason }}</span>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <!-- Financial Accounting Summary -->
    <div class="box-title" style="margin-bottom: 6px;">Financial Accounting Summary</div>
    <table class="summary-table">
        <tr>
            <td class="label">Gross Sales (Delivered Orders)</td>
            <td class="amount">{{ format_currency((float) $settlement->gross) }}</td>
        </tr>
        <tr>
            <td class="label">Less: Marketplace Commission</td>
            <td class="amount" style="color: #dc2626;">- {{ format_currency((float) $settlement->commission) }}</td>
        </tr>
        <tr>
            <td class="label">Less: GST on Platform Commission ({{ setting('commission_gst_pct', 18) }}%)</td>
            <td class="amount" style="color: #dc2626;">- {{ format_currency((float) $settlement->tax_on_commission) }}</td>
        </tr>
        @if((float) $settlement->cod_collected > 0)
        <tr>
            <td class="label">Less: Cash on Delivery Collected at Doorstep</td>
            <td class="amount" style="color: #ea580c;">- {{ format_currency((float) $settlement->cod_collected) }}</td>
        </tr>
        @endif
        @if((float) $settlement->adjustments != 0)
        <tr>
            <td class="label">Manual / Policy Adjustments</td>
            <td class="amount" style="{{ (float) $settlement->adjustments >= 0 ? 'color: #16a34a;' : 'color: #dc2626;' }}">
                {{ (float) $settlement->adjustments >= 0 ? '+ ' : '' }}{{ format_currency((float) $settlement->adjustments) }}
            </td>
        </tr>
        @endif
        <tr class="total-row">
            <td><strong>Net Settlement Payout</strong></td>
            <td class="amount" style="color: #166534; font-size: 14px;"><strong>{{ format_currency((float) $settlement->net) }}</strong></td>
        </tr>
    </table>

    <!-- Items breakdown -->
    <div class="box-title" style="margin-bottom: 6px;">Itemized Settlement Ledger ({{ $settlement->items->count() }} items)</div>
    <table class="items-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Type</th>
                <th>Description</th>
                <th>Order / Package</th>
                <th style="text-align: right;">Gross</th>
                <th style="text-align: right;">Commission</th>
                <th style="text-align: right;">Tax (GST)</th>
                <th style="text-align: right;">Net Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($settlement->items as $idx => $item)
            <tr>
                <td>{{ $idx + 1 }}</td>
                <td><span style="font-size: 9px; text-transform: uppercase;">{{ str_replace('_', ' ', $item->type) }}</span></td>
                <td>{{ $item->description }}</td>
                <td>
                    @if($item->fulfilmentOrder)
                        #{{ $item->fulfilmentOrder->fulfilment_order_number }}
                    @else
                        -
                    @endif
                </td>
                <td style="text-align: right; font-family: monospace;">
                    {{ $item->commissionEntry ? format_currency((float) $item->commissionEntry->base_amount) : '-' }}
                </td>
                <td style="text-align: right; font-family: monospace; color: #dc2626;">
                    {{ $item->commissionEntry ? '- ' . format_currency((float) $item->commissionEntry->commission) : '-' }}
                </td>
                <td style="text-align: right; font-family: monospace; color: #dc2626;">
                    {{ $item->commissionEntry ? '- ' . format_currency((float) $item->commissionEntry->tax_on_commission) : '-' }}
                </td>
                <td style="text-align: right; font-family: monospace; font-weight: bold; {{ (float)$item->amount < 0 ? 'color: #ea580c;' : 'color: #166534;' }}">
                    {{ format_currency((float) $item->amount) }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        This is a computer generated settlement statement and does not require a physical signature.<br>
        For settlement inquiries or disputes, contact OneStall Finance Support at finance@onestall.com.
    </div>
</body>
</html>
