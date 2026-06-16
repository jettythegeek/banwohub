@php
    $org = $invoice->organization;
    $brandName = 'Banwolaw Hub';
    $firmName = $org?->legal_name ?? $org?->name ?? $brandName;

    $statusColors = [
        'paid' => ['bg' => '#ecfdf5', 'fg' => '#047857', 'border' => '#a7f3d0'],
        'sent' => ['bg' => '#fffbeb', 'fg' => '#b45309', 'border' => '#fde68a'],
        'partial' => ['bg' => '#e8f3f6', 'fg' => '#0a4f5e', 'border' => '#8ec9d4'],
        'overdue' => ['bg' => '#fef2f2', 'fg' => '#b91c1c', 'border' => '#fecaca'],
        'draft' => ['bg' => '#f3f4f6', 'fg' => '#6b7280', 'border' => '#e6e9ec'],
        'cancelled' => ['bg' => '#f3f4f6', 'fg' => '#6b7280', 'border' => '#e6e9ec'],
    ];
    $statusStyle = $statusColors[$invoice->status] ?? $statusColors['draft'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }} — {{ $brandName }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            margin: 0;
            padding: 0;
            color: #1f2937;
            font-size: 11px;
            line-height: 1.55;
            background: #ffffff;
        }

        /* Brand header */
        .header-wrap { width: 100%; }
        .header-main {
            background: #053742;
            color: #ffffff;
            padding: 28px 40px 22px;
        }
        .header-accent { height: 4px; background: #b1915a; width: 100%; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: middle; padding: 0; border: none; }
        .brand-mark {
            width: 44px;
            height: 44px;
            background: #b1915a;
            color: #ffffff;
            text-align: center;
            font-size: 15px;
            font-weight: bold;
            line-height: 44px;
            border-radius: 8px;
        }
        .brand-name {
            font-size: 22px;
            font-weight: bold;
            letter-spacing: -0.02em;
            margin: 0;
            color: #ffffff;
        }
        .brand-tagline {
            font-size: 10px;
            color: rgba(255, 255, 255, 0.65);
            margin: 2px 0 0;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }
        .firm-name {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.85);
            margin: 6px 0 0;
        }
        .invoice-badge {
            text-align: right;
        }
        .invoice-badge-label {
            font-size: 10px;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #fad28c;
            margin: 0 0 4px;
        }
        .invoice-badge-number {
            font-size: 18px;
            font-weight: bold;
            color: #ffffff;
            margin: 0;
        }

        /* Body */
        .content { padding: 28px 40px 32px; }

        /* Info cards */
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .info-table > tbody > tr > td {
            width: 50%;
            vertical-align: top;
            padding: 0;
            border: none;
        }
        .info-table > tbody > tr > td:first-child { padding-right: 10px; }
        .info-table > tbody > tr > td:last-child { padding-left: 10px; }
        .info-card {
            background: #f8f9fb;
            border: 1px solid #e6e9ec;
            border-radius: 8px;
            padding: 16px 18px;
            min-height: 108px;
        }
        .info-card-accent {
            border-top: 3px solid #0a4f5e;
        }
        .info-card-gold {
            border-top: 3px solid #b1915a;
        }
        .info-label {
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #0a4f5e;
            margin: 0 0 10px;
        }
        .info-label-gold { color: #8a7348; }
        .info-name {
            font-size: 13px;
            font-weight: bold;
            color: #1f2937;
            margin: 0 0 4px;
        }
        .info-detail { color: #6b7280; margin: 0 0 3px; }
        .meta-row { margin: 0 0 6px; }
        .meta-row strong { color: #374151; }
        .status-pill {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            background: {{ $statusStyle['bg'] }};
            color: {{ $statusStyle['fg'] }};
            border: 1px solid {{ $statusStyle['border'] }};
        }

        /* Line items */
        .section-title {
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #0a4f5e;
            margin: 0 0 10px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table thead th {
            background: #053742;
            color: #ffffff;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            padding: 10px 12px;
            text-align: left;
            border: none;
        }
        .items-table thead th.num { text-align: right; }
        .items-table tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid #e6e9ec;
            color: #374151;
        }
        .items-table tbody tr:nth-child(even) td { background: #f8f9fb; }
        .items-table tbody tr:last-child td { border-bottom: 2px solid #c5e3ea; }
        .num { text-align: right; white-space: nowrap; }
        .item-desc { color: #1f2937; }

        /* Totals */
        .totals-wrap { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .totals-wrap td { border: none; padding: 0; vertical-align: top; }
        .totals-table {
            width: 260px;
            border-collapse: collapse;
            margin-left: auto;
        }
        .totals-table td {
            padding: 5px 0;
            border: none;
            color: #4b5563;
        }
        .totals-table td.num { color: #1f2937; font-weight: 600; }
        .totals-table tr.subtotal td { padding-top: 8px; }
        .totals-table tr.grand td {
            padding: 10px 14px;
            background: #053742;
            color: #ffffff;
            font-size: 12px;
            font-weight: bold;
        }
        .totals-table tr.grand td.num { color: #fad28c; }
        .totals-table tr.balance td {
            padding-top: 8px;
            font-weight: bold;
            color: #b45309;
        }
        .totals-table tr.balance td.num { color: #b45309; }

        /* Notes */
        .notes-box {
            margin-top: 24px;
            padding: 16px 18px;
            background: #fefbf5;
            border: 1px solid #fce5b8;
            border-left: 4px solid #b1915a;
            border-radius: 0 8px 8px 0;
        }
        .notes-title {
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #8a7348;
            margin: 0 0 8px;
        }
        .notes-body { color: #4b5563; margin: 0; }

        /* Footer */
        .footer-wrap { margin-top: 36px; }
        .footer-accent { height: 3px; background: #b1915a; width: 100%; }
        .footer-main {
            background: #053742;
            color: rgba(255, 255, 255, 0.75);
            padding: 14px 40px;
            font-size: 9px;
            text-align: center;
        }
        .footer-main strong { color: #fad28c; font-weight: bold; }
        .footer-contact { color: rgba(255, 255, 255, 0.55); margin-top: 4px; }
    </style>
</head>
<body>
    <div class="header-wrap">
        <div class="header-main">
            <table class="header-table">
                <tr>
                    <td style="width: 56px;">
                        <div class="brand-mark">BH</div>
                    </td>
                    <td style="padding-left: 14px;">
                        <p class="brand-name">{{ $brandName }}</p>
                        <p class="brand-tagline">Legal practice platform</p>
                        @if($firmName !== $brandName)
                            <p class="firm-name">{{ $firmName }}</p>
                        @endif
                    </td>
                    <td class="invoice-badge" style="width: 200px;">
                        <p class="invoice-badge-label">Invoice</p>
                        <p class="invoice-badge-number">{{ $invoice->invoice_number }}</p>
                    </td>
                </tr>
            </table>
        </div>
        <div class="header-accent"></div>
    </div>

    <div class="content">
        <table class="info-table">
            <tr>
                <td>
                    <div class="info-card info-card-accent">
                        <p class="info-label">Bill to</p>
                        <p class="info-name">{{ $invoice->client?->name ?? '—' }}</p>
                        @if($invoice->client?->email)
                            <p class="info-detail">{{ $invoice->client->email }}</p>
                        @endif
                        @if($invoice->legalMatter)
                            <p class="info-detail" style="margin-top: 10px;">
                                <strong style="color: #0a4f5e;">Matter:</strong>
                                {{ $invoice->legalMatter->title }}
                                @if($invoice->legalMatter->matter_number)
                                    <span style="color: #9ca3af;">({{ $invoice->legalMatter->matter_number }})</span>
                                @endif
                            </p>
                        @endif
                    </div>
                </td>
                <td>
                    <div class="info-card info-card-gold">
                        <p class="info-label info-label-gold">Invoice details</p>
                        <p class="meta-row">
                            <strong>Issue date:</strong>
                            {{ $invoice->issue_date?->format('d M Y') ?? '—' }}
                        </p>
                        @if($invoice->due_date)
                            <p class="meta-row">
                                <strong>Due date:</strong>
                                {{ $invoice->due_date->format('d M Y') }}
                            </p>
                        @endif
                        <p class="meta-row">
                            <strong>Status:</strong>
                            <span class="status-pill">{{ ucfirst($invoice->status) }}</span>
                        </p>
                        @if($org?->email || $org?->phone)
                            <p class="info-detail" style="margin-top: 10px;">
                                @if($org?->email){{ $org->email }}@endif
                                @if($org?->email && $org?->phone) · @endif
                                @if($org?->phone){{ $org->phone }}@endif
                            </p>
                        @endif
                    </div>
                </td>
            </tr>
        </table>

        <p class="section-title">Line items</p>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 52%;">Description</th>
                    <th class="num" style="width: 12%;">Qty</th>
                    <th class="num" style="width: 18%;">Rate</th>
                    <th class="num" style="width: 18%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoice->lineItems as $item)
                    <tr>
                        <td class="item-desc">{{ $item->description }}</td>
                        <td class="num">{{ number_format((float) $item->quantity, 2) }}</td>
                        <td class="num">{{ \App\Support\Currency::format($item->unit_price, $invoice->currency) }}</td>
                        <td class="num">{{ \App\Support\Currency::format($item->amount, $invoice->currency) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align: center; color: #9ca3af; padding: 20px;">No line items</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <table class="totals-wrap">
            <tr>
                <td></td>
                <td style="width: 260px;">
                    <table class="totals-table">
                        <tr class="subtotal">
                            <td>Subtotal</td>
                            <td class="num">{{ \App\Support\Currency::format($invoice->subtotal, $invoice->currency) }}</td>
                        </tr>
                        @if((float) $invoice->tax_amount > 0)
                            <tr>
                                <td>Tax ({{ number_format((float) $invoice->tax_rate, 2) }}%)</td>
                                <td class="num">{{ \App\Support\Currency::format($invoice->tax_amount, $invoice->currency) }}</td>
                            </tr>
                        @endif
                        @if((float) $invoice->discount_amount > 0)
                            <tr>
                                <td>Discount</td>
                                <td class="num">− {{ \App\Support\Currency::format($invoice->discount_amount, $invoice->currency) }}</td>
                            </tr>
                        @endif
                        <tr class="grand">
                            <td>Total due</td>
                            <td class="num">{{ \App\Support\Currency::format($invoice->total_amount, $invoice->currency) }}</td>
                        </tr>
                        @if((float) $invoice->amount_paid > 0)
                            <tr>
                                <td>Amount paid</td>
                                <td class="num">{{ \App\Support\Currency::format($invoice->amount_paid, $invoice->currency) }}</td>
                            </tr>
                            <tr class="balance">
                                <td>Balance due</td>
                                <td class="num">{{ \App\Support\Currency::format($invoice->balance_due, $invoice->currency) }}</td>
                            </tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>

        @if($invoice->notes)
            <div class="notes-box">
                <p class="notes-title">Notes</p>
                <p class="notes-body">{{ $invoice->notes }}</p>
            </div>
        @endif

        <div class="footer-wrap">
            <div class="footer-accent"></div>
            <div class="footer-main">
                Thank you for your business — <strong>{{ $brandName }}</strong>
                @if($org?->address)
                    <div class="footer-contact">{{ $org->address }}</div>
                @endif
                @if($org?->email || $org?->phone)
                    <div class="footer-contact">
                        @if($org?->email){{ $org->email }}@endif
                        @if($org?->email && $org?->phone) · @endif
                        @if($org?->phone){{ $org->phone }}@endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
