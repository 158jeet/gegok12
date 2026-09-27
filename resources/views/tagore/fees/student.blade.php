<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Fees — {{ $student->name }}</title>
    <style>body{font-family:system-ui,sans-serif;background:#f6f7f9;margin:0;color:#17202a}.wrap{max-width:1100px;margin:30px auto;padding:0 18px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px;margin-bottom:18px}.grid{display:grid;grid-template-columns:repeat(6,1fr);gap:10px}.metric{padding:14px;background:#f8fafc;border-radius:10px}.metric b{display:block;font-size:20px;margin-top:5px}.table{width:100%;border-collapse:collapse}.table th,.table td{text-align:left;padding:10px;border-bottom:1px solid #eee}.due{font-weight:700}.muted{color:#6b7280}.pay{display:flex;gap:12px;align-items:end;flex-wrap:wrap}.pay input{padding:11px;border:1px solid #d1d5db;border-radius:8px}.pay button{padding:11px 18px;border:0;border-radius:8px;cursor:pointer}.success{padding:12px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px;margin-bottom:18px}@media(max-width:800px){.grid{grid-template-columns:repeat(2,1fr)}.table{font-size:13px}}
    </style>
</head>
<body><div class="wrap">
    @if(session('success'))<div class="success">{{ session('success') }}</div>@endif
    <div class="card"><h1>Fee Statement</h1><div>{{ $student->name }}</div><div class="muted">{{ $student->institution }} · Student #{{ $student->id }}</div></div>
    <div class="grid">
        <div class="metric">Gross<b>₹{{ number_format($summary['gross'],2) }}</b></div>
        <div class="metric">Discount<b>₹{{ number_format($summary['discount'],2) }}</b></div>
        <div class="metric">Concession<b>₹{{ number_format($summary['concession'],2) }}</b></div>
        <div class="metric">Payable<b>₹{{ number_format($summary['payable'],2) }}</b></div>
        <div class="metric">Paid<b>₹{{ number_format($summary['paid'],2) }}</b></div>
        <div class="metric">Outstanding<b class="due">₹{{ number_format($summary['outstanding'],2) }}</b></div>
    </div>
    @if($summary['outstanding'] > 0)
    <div class="card"><h2>Pay online</h2><form class="pay" method="POST" action="{{ route('tagore.payments.initiate',['studentId'=>$student->id]) }}">@csrf<label>Amount<br><input type="number" name="amount" min="1" max="{{ $summary['outstanding'] }}" step="0.01" value="{{ number_format($summary['outstanding'],2,'.','') }}" required></label><button type="submit">Proceed to Payment</button></form><div class="muted" style="margin-top:10px">Payment is allocated automatically against the student's oldest outstanding fee.</div></div>
    @endif
    <div class="card"><h2>Fee obligations</h2><table class="table"><tr><th>Due date</th><th>Gross</th><th>Discount</th><th>Concession</th><th>Payable</th><th>Paid</th><th>Balance</th><th>Status</th></tr>@forelse($fees as $fee)<tr><td>{{ $fee->due_date ? \Carbon\Carbon::parse($fee->due_date)->format('d M Y') : '—' }}</td><td>₹{{ number_format($fee->gross_amount,2) }}</td><td>₹{{ number_format($fee->discount_amount,2) }}</td><td>₹{{ number_format($fee->concession_amount,2) }}</td><td>₹{{ number_format($fee->net_amount,2) }}</td><td>₹{{ number_format($fee->paid_amount,2) }}</td><td class="due">₹{{ number_format($fee->outstanding_amount,2) }}</td><td>{{ ucfirst($fee->status) }}</td></tr>@empty<tr><td colspan="8">No fee obligations recorded.</td></tr>@endforelse</table></div>
    <div class="card"><h2>Fee heads</h2><table class="table"><tr><th>Head</th><th>Gross</th><th>Discount</th><th>Concession</th><th>Payable</th><th>Paid</th><th>Balance</th></tr>@forelse($items as $item)<tr><td>{{ $item->fee_head }}</td><td>₹{{ number_format($item->gross_amount,2) }}</td><td>₹{{ number_format($item->discount_amount,2) }}</td><td>₹{{ number_format($item->concession_amount,2) }}</td><td>₹{{ number_format($item->net_amount,2) }}</td><td>₹{{ number_format($item->paid_amount,2) }}</td><td>₹{{ number_format($item->outstanding_amount,2) }}</td></tr>@empty<tr><td colspan="7">No fee-head details recorded.</td></tr>@endforelse</table></div>
    <div class="card"><h2>Payment history</h2><table class="table"><tr><th>Receipt</th><th>Date</th><th>Mode</th><th>Reference</th><th>Amount</th></tr>@forelse($payments as $payment)<tr><td>{{ $payment->receipt_no ?: '—' }}</td><td>{{ $payment->paid_at ? \Carbon\Carbon::parse($payment->paid_at)->format('d M Y H:i') : '—' }}</td><td>{{ ucfirst(str_replace('_',' ',$payment->payment_mode ?: $payment->gateway ?: '—')) }}</td><td>{{ $payment->reference_number ?: '—' }}</td><td>₹{{ number_format($payment->amount,2) }}</td></tr>@empty<tr><td colspan="5">No successful payments recorded.</td></tr>@endforelse</table></div>
</div></body></html>