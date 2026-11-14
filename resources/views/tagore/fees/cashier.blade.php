<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cashier counter · Tagore ERP</title>
<link rel="icon" type="image/webp" href="{{ asset('uploads/tgi-logo.webp') }}">
<link rel="stylesheet" href="{{ asset('tagore-erp.css') . '?v=20261114-cashier1' }}">
<style>
.tg-cashier-tools{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.tg-cashier-tools input{min-width:220px;max-width:420px;width:100%}
.tg-cashier-table input,.tg-cashier-table select{max-width:150px;min-width:110px}
.tg-cashier-table form{display:flex;gap:7px;align-items:center;flex-wrap:wrap}
.tg-cashier-table .tg-btn{white-space:nowrap}
.tg-cashier-muted{color:#9eacbd;font-size:12px}
.tg-cashier-kpis{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:18px 0}
.tg-cashier-kpi{padding:18px;border:1px solid #344253;border-radius:14px;background:#19232e}
.tg-cashier-kpi span{display:block;color:#9eacbd;font-size:12px}
.tg-cashier-kpi strong{display:block;font-size:25px;margin-top:6px;color:#f4f7fb}
.tg-cashier-section{margin-top:22px}
@media(max-width:760px){.tg-cashier-tools input{max-width:none}.tg-cashier-table{min-width:880px}.tg-cashier-kpis{grid-template-columns:1fr 1fr}}
</style>
</head>
<body class="tg-app">
<div class="tg-shell">
<header class="tg-topbar">
<a class="tg-brand" href="{{ route('tagore.dashboard') }}" style="text-decoration:none"><img class="tg-logo" src="{{ asset('uploads/tgi-logo.webp') }}" alt="Tagore Group"><div><strong>Tagore ERP</strong><span>Front office · Cashier desk</span></div></a>
<div class="tg-top-actions"><div class="tg-user"><div class="tg-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'T',0,1)) }}</div><span>{{ auth()->user()->name ?? 'User' }}</span></div><a class="tg-btn" href="{{ route('tagore.fees.accounts') }}">Accounts overview</a><a class="tg-btn" href="{{ route('tagore.dashboard') }}">← Dashboard</a></div>
</header>
<div class="tg-layout">
<aside class="tg-sidebar">
<div class="tg-side-label">Daily work</div>
<a class="tg-side-link" href="{{ route('tagore.dashboard') }}">⌂ Overview</a>
<a class="tg-side-link active" href="{{ route('tagore.cashier.index') }}">▣ Cashier counter</a>
<a class="tg-side-link" href="{{ route('tagore.fees.accounts') }}">▤ Accounts overview</a>
@if($roles->intersect(['OWNER','PRINCIPAL'])->isNotEmpty())
<a class="tg-side-link" href="{{ route('tagore.fees.manage') }}">₹ Fee structures</a>
@endif
</aside>
<main class="tg-main">
<section class="tg-hero">
<h1>Cashier counter</h1>
<p>Find a student, confirm the outstanding balance, record a cash or offline payment, and issue the receipt. Payments are allocated to the oldest outstanding fee first and the receipt is emailed when a valid address is available.</p>
<div class="tg-hero-actions"><a class="tg-btn" href="{{ route('tagore.fees.accounts') }}">Open financial overview →</a></div>
</section>
@if(session('success'))<div class="tg-alert" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="tg-alert warning" role="alert"><strong>Payment was not recorded. Please review:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<section class="tg-cashier-kpis" aria-label="Today's collection">
<div class="tg-cashier-kpi"><span>Collected today</span><strong>₹{{ number_format((float)$todayCollection,2) }}</strong></div>
<div class="tg-cashier-kpi"><span>Receipts posted today</span><strong>{{ number_format((int)$todayCount) }}</strong></div>
</section>
<section class="tg-cashier-section">
<div class="tg-section-head"><div><h2>Collect a student payment</h2><p>Search by student name, registered mobile number or student ID. Only students with an outstanding balance are shown.</p></div><span class="tg-status">{{ $students->total() }} matching students</span></div>
<div class="tg-card" style="padding:16px">
<form method="GET" action="{{ route('tagore.cashier.index') }}" class="tg-cashier-tools">
<input type="search" name="q" value="{{ request('q') }}" placeholder="Student name, mobile or ID…" aria-label="Search students for fee collection">
<button type="submit" class="tg-btn primary">Search</button>
@if(request()->filled('q'))<a class="tg-btn" href="{{ route('tagore.cashier.index') }}">Clear</a>@endif
</form>
<div class="tg-table-wrap" style="margin-top:14px">
<table class="table tg-cashier-table">
<thead><tr><th>Student</th><th>Institution</th><th>Open items</th><th>Outstanding</th><th>Collect payment</th></tr></thead>
<tbody>
@forelse($students as $student)
<tr>
<td><strong>{{ $student->student_name }}</strong><div class="tg-cashier-muted">Student #{{ $student->student_id }} · {{ $student->mobile_no ?: 'No mobile on file' }}</div></td>
<td>{{ $student->institution }}</td>
<td>{{ $student->open_items }}</td>
<td><strong>₹{{ number_format((float)$student->outstanding,2) }}</strong></td>
<td><form method="POST" action="{{ route('tagore.fees.payment',$student->student_id) }}" class="tg-cashier-table">@csrf
<div class="tg-cashier-tools">
<input type="number" name="amount" min="0.01" max="{{ number_format((float)$student->outstanding,2,'.','') }}" step="0.01" value="{{ number_format((float)$student->outstanding,2,'.','') }}" required aria-label="Payment amount for student {{ $student->student_id }}">
<select name="payment_mode" required aria-label="Payment mode"><option value="cash">Cash</option><option value="cheque">Cheque</option><option value="bank_transfer">Bank transfer</option><option value="other">Other</option></select>
<input name="reference_number" maxlength="160" placeholder="Optional reference" aria-label="Payment reference">
<button type="submit" class="tg-btn primary" onclick="return confirm('Record this payment and post it to the fee ledger?');">Record & email receipt</button>
</div></form></td>
</tr>
@empty
<tr><td colspan="5"><div class="tg-empty"><strong>No outstanding students found</strong><span>Try a different name, mobile number or student ID.</span></div></td></tr>
@endforelse
</tbody>
</table>
</div>
<div style="margin-top:14px">{{ $students->links() }}</div>
</div>
</section>
<section class="tg-cashier-section">
<div class="tg-section-head"><div><h2>Latest receipts</h2><p>Most recent successful payments across your permitted institutions.</p></div></div>
<div class="tg-card" style="padding:16px"><div class="tg-table-wrap"><table class="table"><thead><tr><th>Receipt</th><th>Student</th><th>Date & time</th><th>Mode</th><th>Amount</th><th>Document</th></tr></thead><tbody>
@forelse($recent as $payment)
<tr><td><strong>{{ $payment->receipt_no ?: '—' }}</strong></td><td>{{ $payment->student_name }}</td><td>{{ $payment->paid_at ? \Carbon\Carbon::parse($payment->paid_at)->format('d M Y, H:i') : '—' }}</td><td>{{ ucfirst(str_replace('_',' ',$payment->payment_mode ?: '—')) }}</td><td>₹{{ number_format((float)$payment->amount,2) }}</td><td><a class="tg-btn" href="{{ route('tagore.fees.receipt',$payment->id) }}">Download receipt</a></td></tr>
@empty<tr><td colspan="6"><div class="tg-empty"><strong>No receipts yet</strong><span>Newly recorded payments will appear here.</span></div></td></tr>@endforelse
</tbody></table></div></div>
</section>
</main>
</div>
</div>
</body>
</html>
