<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }
        .container {
            padding: 40px;
        }
        .header {
            display: table;
            width: 100%;
            margin-bottom: 40px;
        }
        .header-left, .header-right {
            display: table-cell;
            vertical-align: top;
            width: 50%;
        }
        .header-right {
            text-align: right;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #1f2937;
        }
        .invoice-title {
            font-size: 28px;
            font-weight: bold;
            color: #4f46e5;
            margin-bottom: 10px;
        }
        .invoice-meta {
            color: #6b7280;
        }
        .invoice-meta p {
            margin: 4px 0;
        }
        .addresses {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }
        .address-block {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .address-label {
            font-weight: bold;
            color: #6b7280;
            text-transform: uppercase;
            font-size: 10px;
            margin-bottom: 8px;
        }
        .address-content {
            color: #1f2937;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table th {
            background-color: #f3f4f6;
            padding: 12px 8px;
            text-align: left;
            font-weight: bold;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
        }
        .items-table th.text-right,
        .items-table td.text-right {
            text-align: right;
        }
        .items-table td {
            padding: 12px 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        .totals {
            width: 300px;
            margin-left: auto;
        }
        .totals-row {
            display: table;
            width: 100%;
            padding: 8px 0;
        }
        .totals-label, .totals-value {
            display: table-cell;
        }
        .totals-label {
            color: #6b7280;
        }
        .totals-value {
            text-align: right;
            font-weight: bold;
        }
        .totals-row.grand-total {
            border-top: 2px solid #e5e7eb;
            margin-top: 8px;
            padding-top: 12px;
        }
        .totals-row.grand-total .totals-label,
        .totals-row.grand-total .totals-value {
            font-size: 16px;
            color: #1f2937;
        }
        .notes {
            margin-top: 40px;
            padding: 20px;
            background-color: #f9fafb;
            border-radius: 4px;
        }
        .notes-label {
            font-weight: bold;
            color: #6b7280;
            margin-bottom: 8px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-draft { background-color: #e5e7eb; color: #374151; }
        .status-sent { background-color: #dbeafe; color: #1d4ed8; }
        .status-paid { background-color: #d1fae5; color: #059669; }
        .status-partial { background-color: #fef3c7; color: #d97706; }
        .status-overdue { background-color: #fee2e2; color: #dc2626; }
        .status-void { background-color: #f3f4f6; color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-left">
                <div class="company-name">LaraCRM</div>
            </div>
            <div class="header-right">
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-meta">
                    <p><strong>Invoice #:</strong> {{ $invoice->invoice_number }}</p>
                    <p><strong>Date:</strong> {{ $invoice->invoice_date->format('M d, Y') }}</p>
                    <p><strong>Due Date:</strong> {{ $invoice->due_date->format('M d, Y') }}</p>
                    <p><span class="status-badge status-{{ strtolower($invoice->status->value) }}">{{ $invoice->status->getLabel() }}</span></p>
                </div>
            </div>
        </div>

        <div class="addresses">
            <div class="address-block">
                <div class="address-label">Bill To</div>
                <div class="address-content">
                    <strong>{{ $invoice->customer->display_name }}</strong><br>
                    @if($invoice->customer->billing_address)
                        @if(!empty($invoice->customer->billing_address['street']))
                            {{ $invoice->customer->billing_address['street'] }}<br>
                        @endif
                        @if(!empty($invoice->customer->billing_address['city']) || !empty($invoice->customer->billing_address['state']) || !empty($invoice->customer->billing_address['postcode']))
                            {{ $invoice->customer->billing_address['city'] ?? '' }}
                            {{ !empty($invoice->customer->billing_address['state']) ? ', ' . $invoice->customer->billing_address['state'] : '' }}
                            {{ $invoice->customer->billing_address['postcode'] ?? '' }}<br>
                        @endif
                        @if(!empty($invoice->customer->billing_address['country']))
                            {{ $invoice->customer->billing_address['country'] }}
                        @endif
                    @endif
                    @if($invoice->customer->email)
                        <br>{{ $invoice->customer->email }}
                    @endif
                </div>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 40%">Description</th>
                    <th class="text-right" style="width: 15%">Qty</th>
                    <th class="text-right" style="width: 15%">Unit Price</th>
                    <th class="text-right" style="width: 15%">Tax</th>
                    <th class="text-right" style="width: 15%">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                <tr>
                    <td>
                        @if($item->product)
                            <strong>{{ $item->product->name }}</strong><br>
                        @endif
                        {{ $item->description }}
                    </td>
                    <td class="text-right">{{ number_format($item->quantity, 2) }}</td>
                    <td class="text-right">${{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-right">{{ number_format($item->tax_rate, 0) }}%</td>
                    <td class="text-right">${{ number_format($item->total_amount, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div class="totals-row">
                <span class="totals-label">Subtotal</span>
                <span class="totals-value">${{ number_format($invoice->subtotal, 2) }}</span>
            </div>
            @if($invoice->discount_amount > 0)
            <div class="totals-row">
                <span class="totals-label">Discount</span>
                <span class="totals-value">-${{ number_format($invoice->discount_amount, 2) }}</span>
            </div>
            @endif
            @if($invoice->tax_amount > 0)
            <div class="totals-row">
                <span class="totals-label">Tax</span>
                <span class="totals-value">${{ number_format($invoice->tax_amount, 2) }}</span>
            </div>
            @endif
            <div class="totals-row grand-total">
                <span class="totals-label">Total</span>
                <span class="totals-value">${{ number_format($invoice->total_amount, 2) }}</span>
            </div>
            @if($invoice->paid_amount > 0)
            <div class="totals-row">
                <span class="totals-label">Paid</span>
                <span class="totals-value">-${{ number_format($invoice->paid_amount, 2) }}</span>
            </div>
            <div class="totals-row">
                <span class="totals-label">Balance Due</span>
                <span class="totals-value">${{ number_format($invoice->balance_due, 2) }}</span>
            </div>
            @endif
        </div>

        @if($invoice->notes || $invoice->terms)
        <div class="notes">
            @if($invoice->notes)
            <div class="notes-label">Notes</div>
            <p>{{ $invoice->notes }}</p>
            @endif
            @if($invoice->terms)
            <div class="notes-label" style="margin-top: 16px;">Terms & Conditions</div>
            <p>{{ $invoice->terms }}</p>
            @endif
        </div>
        @endif
    </div>
</body>
</html>
