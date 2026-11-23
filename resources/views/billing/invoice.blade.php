@php
    $inr = fn ($p) => '₹'.number_format($p / 100, 2);
    $credit = $invoice->type === 'credit_note';
    $tax = $invoice->taxPaise();
@endphp
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>{{ $credit ? 'Credit note' : 'Tax invoice' }} {{ $invoice->number }}</title>
<style>
body{font-family:Arial,Helvetica,sans-serif;color:#222;max-width:800px;margin:24px auto;padding:0 16px;font-size:14px}
table{width:100%;border-collapse:collapse}th,td{padding:8px;border-bottom:1px solid #ddd;text-align:left}td.r,th.r{text-align:right}
.head{display:flex;justify-content:space-between;gap:24px}.muted{color:#666}h1{font-size:22px;margin:0 0 4px}
@media print{.noprint{display:none}}
</style></head><body>
<p class="noprint"><button onclick="window.print()">Print or save as PDF</button></p>
<div class="head">
    <div><h1>{{ $credit ? 'Credit note' : 'Tax invoice' }}</h1><div>No. <strong>{{ $invoice->number }}</strong></div><div>Date: {{ $invoice->issued_at->timezone(config('marketplace.timezone'))->format('d M Y') }}</div>
        @if($credit)<div class="muted">Against payment {{ $payment->txn_id }}</div>@endif</div>
    <div style="text-align:right"><strong>{{ $company['name'] }}</strong><br>{!! nl2br(e($company['address'])) !!}@if($company['gstin'])<br>GSTIN: {{ $company['gstin'] }}@endif @if($company['email'])<br>{{ $company['email'] }}@endif</div>
</div>
<hr>
<p><strong>Billed to</strong><br>{{ $invoice->billing['name'] ?? '' }}<br>{{ $invoice->billing['state'] ?? '' }}@if(! empty($invoice->billing['gstin']))<br>GSTIN: {{ $invoice->billing['gstin'] }}@endif<br>{{ $invoice->billing['email'] ?? '' }}</p>
<table>
    <thead><tr><th>Description</th><th class="r">Amount</th></tr></thead>
    <tbody>
    @foreach($invoice->lines as $line)<tr><td>{{ $line['description'] }}</td><td class="r">{{ $inr($line['amount_paise']) }}</td></tr>@endforeach
    @if($invoice->discount_paise > 0)<tr><td>Discount</td><td class="r">− {{ $inr($invoice->discount_paise) }}</td></tr>@endif
    <tr><td>Taxable value</td><td class="r">{{ $inr($invoice->subtotal_paise - $invoice->discount_paise) }}</td></tr>
    @if($invoice->igst_paise > 0)<tr><td>IGST ({{ rtrim(rtrim(number_format($payment->gst_percent, 2), '0'), '.') }}%)</td><td class="r">{{ $inr($invoice->igst_paise) }}</td></tr>@endif
    @if($invoice->cgst_paise > 0)<tr><td>CGST ({{ rtrim(rtrim(number_format($payment->gst_percent / 2, 2), '0'), '.') }}%)</td><td class="r">{{ $inr($invoice->cgst_paise) }}</td></tr><tr><td>SGST ({{ rtrim(rtrim(number_format($payment->gst_percent / 2, 2), '0'), '.') }}%)</td><td class="r">{{ $inr($invoice->sgst_paise) }}</td></tr>@endif
    <tr><th>Total {{ $credit ? 'refunded' : '' }}</th><th class="r">{{ $inr($invoice->total_paise) }}</th></tr>
    </tbody>
</table>
<p class="muted">Payment reference: {{ $payment->gateway_payment_id }}. This is a computer-generated document.</p>
</body></html>
