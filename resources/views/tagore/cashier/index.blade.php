<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cashier Desk · Tagore ERP</title>
<link rel="icon" type="image/webp" href="{{ asset('uploads/tgi-logo.webp') }}">
<link rel="stylesheet" href="{{ asset('tagore-erp.css') . '?v=20261128-cashier-desk1' }}">
<style>
.cashier-filter{display:grid;grid-template-columns:repeat(4,minmax(0,1fr)) auto;gap:9px;align-items:end}
.cashier-filter label{display:grid;gap:5px;color:var(--tg-muted);font-size:12px}
.cashier-filter input,.cashier-filter select{width:100%;min-width:0}
.cashier-mode{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 14px;border:1px solid var(--tg-line);border-radius:12px;background:var(--tg-card)}
.cashier-mode strong{font-variant-numeric:tabular-nums}
.cashier-table{min-width:760px;width:100%}
.cashier-table td{vertical-align:middle}
.tg-main{min-width:0;max-width:100%}
.tg-table-wrap{display:block;width:100%;max-width:100%;min-width:0;overflow-x:auto;overscroll-behavior-x:contain;-webkit-overflow-scrolling:touch}
.tg-table-wrap table{margin-bottom:0}
.cashier-filter{min-width:0;max-width:100%}
.cashier-filter input,.cashier-filter select{max-width:100%;box-sizing:border-box}
.cashier-collect{display:grid;grid-template-columns:100px 120px minmax(130px,1fr) auto;gap:6px;min-width:390px}
.cashier-collect input,.cashier-collect select{min-width:0;width:100%}
.cashier-muted{font-size:11px;color:var(--tg-muted)}
@media(max-width:900px){.cashier-filter{grid-template-columns:repeat(2,minmax(0,1fr))}.cashier-filter .cashier-submit{grid-column:1/-1}}
@media(max-width:900px){.tg-main{min-width:0;width:100%;max-width:100%}.tg-table-wrap{overflow-x:auto}.cashier-table{min-width:760px}}
@media(max-width:560px){.cashier-filter{grid-template-columns:1fr}.cashier-filter .cashier-submit{grid-column:auto}.cashier-collect{grid-template-columns:1fr 1fr;min-width:0}.cashier-collect .wide{grid-column:1/-1}.cashier-filter input,.cashier-filter select{width:100%;max-width:100%}}
</style>
</head>
<body class="tg-app">
<div class="tg-shell">
<header class="tg-topbar">
  <a class="tg-brand" href="{{ route('tagore.dashboard') }}" style="text-decoration:none"><img class="tg-logo" src="{{ asset('uploads/tgi-logo.webp') }}" alt="Tagore Group of Institutions"><div><strong>Tagore ERP</strong><span>Cashier workspace</span></div></a>
  <div class="tg-top-actions"><div class="tg-user"><div class="tg-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'T',0,1)) }}</div><span>{{ auth()->user()->name ?? 'User' }}</span></div><a class="tg-btn" href="{{ route('tagore.profile.edit') }}">My profile</a><a class="tg-btn" href="{{ route('tagore.dashboard') }}">Dashboard</a><form method="POST" action="{{ route('logout') }}" style="margin:0">@csrf<button class="tg-btn" type="submit" aria-label="Log out of Tagore ERP">Logout</button></form></div>
</header>
@if(session('success'))<div class="tg-alert success" role="status">{{ session('success') }}</div>@endif
@if(session('warning'))<div class="tg-alert" role="status">{{ session('warning') }}</div>@endif
@if($errors->any())<div class="tg-alert danger" role="alert">{{ $errors->first() }}</div>@endif
<div class="tg-layout">
<aside class="tg-sidebar" aria-label="Cashier navigation">
  <div class="tg-side-label">Cashier workspace</div>
  <a class="tg-side-link active" href="{{ route('tagore.cashier.index') }}"><span class="tg-side-icon">⌂</span>Cashier desk</a>
  <a class="tg-side-link" href="{{ route('tagore.cashier.fees') }}"><span class="tg-side-icon">₹</span>Fee assignments</a>
  <a class="tg-side-link" href="{{ route('tagore.fees.accounts') }}"><span class="tg-side-icon">▤</span>Accounts & collections</a>
  <a class="tg-side-link" href="{{ route('tagore.fees.wallet') }}"><span class="tg-side-icon">◉</span>Fee wallets</a>
  <div class="tg-side-label">Your institution</div>
  @foreach($institutions as $institution)<span class="tg-side-link" style="cursor:default"><span class="tg-side-icon">▦</span>{{ $institution->display_name }}</span>@endforeach
</aside>
<main class="tg-main">
  <section class="tg-hero">
    <h1>Cashier desk</h1>
    <p>Daily collections, quick fee counter, family dues and receipts — limited to your assigned institution scope.</p>
    <div class="tg-hero-actions"><a class="tg-btn primary" href="#quick-collect">Collect a fee ↓</a><a class="tg-btn" href="#transactions">Find a receipt ↓</a></div>
  </section>
  <section class="tg-grid" aria-label="Cashier performance">
    <div class="tg-card tg-kpi"><div class="tg-kpi-top"><span class="tg-kpi-label">Collected today</span><span class="tg-kpi-icon">₹</span></div><div class="tg-kpi-value">₹{{ number_format($summary['today_total'],2) }}</div><div class="tg-kpi-meta">{{ number_format($summary['today_count']) }} successful collections</div></div>
    <div class="tg-card tg-kpi"><div class="tg-kpi-top"><span class="tg-kpi-label">Collected this month</span><span class="tg-kpi-icon">↗</span></div><div class="tg-kpi-value">₹{{ number_format($summary['month_total'],2) }}</div><div class="tg-kpi-meta">{{ number_format($summary['month_count']) }} successful collections</div></div>
    <div class="tg-card tg-kpi"><div class="tg-kpi-top"><span class="tg-kpi-label">Outstanding fees</span><span class="tg-kpi-icon">!</span></div><div class="tg-kpi-value">₹{{ number_format($summary['outstanding'],2) }}</div><div class="tg-kpi-meta">{{ number_format($summary['students_with_dues']) }} students with dues</div></div>
  </section>
  <section class="tg-section">
    <div class="tg-section-head"><div><h2>Today's collection mix</h2><p>Understand how money reached the counter today.</p></div></div>
    <div class="tg-grid">
    @forelse($modeTotals as $mode)
      <div class="cashier-mode"><span>{{ match($mode->payment_mode){'upi'=>'UPI / QR','card'=>'Card / POS','bank_transfer'=>'Bank transfer','cheque'=>'Cheque','cash'=>'Cash',default=>'Other'} }} <span class="cashier-muted">· {{ $mode->transactions }} payments</span></span><strong>₹{{ number_format($mode->amount,2) }}</strong></div>
    @empty
      <div class="tg-card tg-empty" style="grid-column:1/-1"><strong>No collections yet today</strong><span>Payments will be grouped here as they are recorded.</span></div>
    @endforelse
    </div>
  </section>
  <section class="tg-section" id="quick-collect">
    <div class="tg-section-head"><div><h2>Quick collect</h2><p>Search by student name, admission number or student ID. Record a full or partial payment and issue the receipt immediately.</p></div><span class="tg-status">{{ $dueStudents->count() }} matching students</span></div>
    <form class="tg-card cashier-filter" method="GET" action="{{ route('tagore.cashier.index') }}" style="padding:14px">
      <label>Student search<input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Name, admission no. or ID"></label>
      <label>From date<input type="date" name="from" value="{{ $filters['from'] }}"></label>
      <label>To date<input type="date" name="to" value="{{ $filters['to'] }}"></label>
      <label>Payment mode<select name="mode"><option value="">All modes</option>@foreach(['cash'=>'Cash','upi'=>'UPI / QR','card'=>'Card / POS','cheque'=>'Cheque','bank_transfer'=>'Bank transfer','other'=>'Other'] as $value=>$label)<option value="{{ $value }}" @selected($filters['mode']===$value)>{{ $label }}</option>@endforeach</select></label>
      <button class="tg-btn primary cashier-submit" type="submit">Apply filters</button>
    </form>
    <div class="tg-card" style="padding:14px;margin-top:12px">
      <div class="tg-table-wrap"><table class="table cashier-table"><thead><tr><th>Student</th><th>Institution</th><th>Outstanding</th><th>Record payment</th></tr></thead><tbody>
      @forelse($dueStudents as $student)
        <tr><td><strong>{{ $student->student_name }}</strong><div class="cashier-muted">{{ $student->registration_number ?: 'Student' }} · #{{ $student->student_id }}</div><form method="POST" action="{{ route('tagore.cashier.reminder',$student->student_id) }}" style="margin-top:6px">@csrf<button class="tg-btn" type="submit">Send parent reminder</button></form></td><td>{{ $student->institution }}</td><td><strong>₹{{ number_format($student->outstanding,2) }}</strong></td><td><form class="cashier-collect" method="POST" action="{{ route('tagore.fees.payment',$student->student_id) }}">@csrf<input type="number" name="amount" min="0.01" max="{{ $student->outstanding }}" step="0.01" placeholder="Amount" required aria-label="Amount"><select name="payment_mode" required aria-label="Payment mode"><option value="cash">Cash</option><option value="upi">UPI</option><option value="card">Card</option><option value="cheque">Cheque</option><option value="bank_transfer">Bank</option><option value="other">Other</option></select><input class="wide" name="reference_number" maxlength="160" placeholder="UPI / cheque / bank reference (optional)" aria-label="Payment reference"><button class="tg-btn primary wide" type="submit">Collect & issue receipt</button></form></td></tr>
      @empty
        <tr><td colspan="4"><div class="tg-empty"><strong>No matching dues</strong><span>Try another search or clear the filters.</span></div></td></tr>
      @endforelse
      </tbody></table></div>
    </div>
  </section>
  <section class="tg-section" id="transactions">
    <div class="tg-section-head"><div><h2>Collection register</h2><p>Search and review posted payments. Receipt records are immutable; corrections must go through an authorised finance process.</p></div><a class="tg-btn" href="{{ route('tagore.cashier.export',request()->query()) }}">Export CSV ↓</a></div>
    <div class="tg-card" style="padding:14px">
      <div class="tg-table-wrap"><table class="table cashier-table"><thead><tr><th>Receipt</th><th>Student</th><th>Paid at</th><th>Mode</th><th>Reference</th><th>Amount</th><th>Receipt copy</th></tr></thead><tbody>
      @forelse($transactions as $payment)
        <tr><td><strong>{{ $payment->receipt_no ?: '—' }}</strong></td><td>{{ $payment->student }}<div class="cashier-muted">{{ $payment->registration_number }}</div></td><td>{{ $payment->paid_at ? \Carbon\Carbon::parse($payment->paid_at)->format('d M Y, h:i A') : '—' }}</td><td>{{ match($payment->payment_mode){'upi'=>'UPI / QR','card'=>'Card / POS','bank_transfer'=>'Bank transfer','cheque'=>'Cheque','cash'=>'Cash',default=>'Other'} }}</td><td>{{ $payment->reference_number ?: '—' }}</td><td><strong>₹{{ number_format($payment->amount,2) }}</strong></td><td><a class="tg-btn" href="{{ route('tagore.fees.receipt',$payment->id) }}">Download ↗</a></td></tr>
      @empty
        <tr><td colspan="7"><div class="tg-empty"><strong>No payments in this date range</strong><span>Change the date range or record a collection.</span></div></td></tr>
      @endforelse
      </tbody></table></div>
      <div style="margin-top:14px">{{ $transactions->links() }}</div>
    </div>
  </section>
</main>
</div>
</div>
</body>
</html>
