<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Accounts & fee collection · Tagore ERP</title>
<link rel="icon" type="image/webp" href="{{ asset('uploads/tgi-logo.webp') }}">
<link rel="stylesheet" href="{{ asset('tagore-erp.css') . '?v=20261029-accounts-ui1' }}">
<style>
.tg-accounts-tools{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0 0 12px}
.tg-accounts-tools input{max-width:360px;min-width:180px}
.tg-accounts-table{min-width:850px}
.tg-accounts-table td{vertical-align:middle}
.tg-accounts-number{font-variant-numeric:tabular-nums;white-space:nowrap}
.tg-accounts-subtle{color:var(--tg-muted);font-size:12px;line-height:1.5}
.tg-accounts-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px}
.tg-accounts-card-head h2{margin:0;font-size:16px}
.tg-accounts-card-head p{margin:5px 0 0;color:var(--tg-muted);font-size:12px;line-height:1.55}
.tg-accounts-pill{display:inline-flex;align-items:center;gap:5px;padding:5px 9px;border-radius:999px;font-size:10px;font-weight:800;white-space:nowrap;background:#263342;color:#c9d5e2}
.tg-accounts-pill.pending{background:#49391d;color:#fbd38d}
.tg-accounts-pill.matched{background:#123e31;color:#6ee7b7}
.tg-accounts-pill.mismatch{background:#482630;color:#ff9eac}
.tg-accounts-table .tg-btn{white-space:nowrap}
.tg-accounts-table details{min-width:210px}
.tg-accounts-table summary{cursor:pointer;color:#66e6b2;font-size:12px;font-weight:750;margin-top:7px}
.tg-accounts-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:10px;min-width:260px}
.tg-accounts-form .wide{grid-column:1/-1}
.tg-accounts-form button{justify-self:start}
.tg-accounts-empty{padding:26px 14px;text-align:center;color:var(--tg-muted);font-size:12px}
.tg-accounts-empty strong{display:block;color:var(--tg-ink);font-size:13px;margin-bottom:4px}
@media(max-width:760px){.tg-accounts-form{grid-template-columns:1fr;min-width:0}.tg-accounts-form .wide{grid-column:auto}.tg-accounts-tools input{max-width:none;width:100%}}
</style>
<style>
@media(max-width:760px){
  body.tg-accounts-page .tg-top-actions{display:flex;flex-wrap:wrap;align-items:stretch;width:100%;min-width:0;gap:8px}
  body.tg-accounts-page .tg-top-actions .tg-search{flex:1 1 100%;width:100%;min-width:0}
  body.tg-accounts-page .tg-top-actions .tg-user{flex:1 1 140px;max-width:none;min-width:0}
  body.tg-accounts-page .tg-top-actions>.tg-btn{flex:0 0 auto;white-space:nowrap}
}
</style>
</head>
<body class="tg-app tg-accounts-page">
<div class="tg-shell">
<header class="tg-topbar">
  <a class="tg-brand" href="{{ route('tagore.dashboard') }}" style="text-decoration:none">
    <img class="tg-logo" src="{{ asset('uploads/tgi-logo.webp') }}" alt="Tagore Group of Institutions">
    <div><strong>Tagore ERP</strong><span>Tagore Group of Institutions</span></div>
  </a>
  <div class="tg-top-actions">
    <label class="tg-search" for="tg-accounts-search" style="display:flex;align-items:center;gap:8px">⌕ <input id="tg-accounts-search" type="search" placeholder="Search this page…" aria-label="Search fee accounts and transactions" autocomplete="off" style="min-width:0;width:190px;border:0;outline:0;background:transparent;color:inherit;font:inherit"></label>
    <div class="tg-user"><div class="tg-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'T',0,1)) }}</div><span>{{ auth()->user()->name ?? 'User' }}</span></div>
    <a class="tg-btn" href="{{ route('tagore.dashboard') }}">← Dashboard</a>
  </div>
</header>

<div class="tg-layout">
<aside class="tg-sidebar" aria-label="Main navigation">
  <div class="tg-side-label">Workspace</div>
  <a class="tg-side-link" href="{{ route('tagore.dashboard') }}"><span class="tg-side-icon">⌂</span>Overview</a>
  @if($roles->intersect(['OWNER','PRINCIPAL','COORDINATOR'])->isNotEmpty())
    <a class="tg-side-link" href="{{ route('tagore.admissions.index') }}"><span class="tg-side-icon">↗</span>Admissions</a>
    <a class="tg-side-link" href="{{ route('tagore.tasks.index') }}"><span class="tg-side-icon">✓</span>Work management</a>
  @endif
  @if($roles->intersect(['OWNER','PRINCIPAL','ACCOUNTS'])->isNotEmpty())
    <a class="tg-side-link" href="{{ route('tagore.fees.manage') }}"><span class="tg-side-icon">₹</span>Fee management</a>
  @endif
  @if($roles->contains('FEE_EDITOR'))
    <a class="tg-side-link" href="{{ route('tagore.fees.editor') }}"><span class="tg-side-icon">₹</span>Fee editor</a>
  @endif
  <a class="tg-side-link active" href="{{ route('tagore.fees.accounts') }}"><span class="tg-side-icon">▤</span>Accounts</a>
  @if($roles->contains('OWNER'))
    <div class="tg-side-label">Administration</div>
    <a class="tg-side-link" href="{{ route('tagore.admin') }}"><span class="tg-side-icon">⚙</span>Administration</a>
    <a class="tg-side-link" href="{{ route('tagore.academic.structure') }}"><span class="tg-side-icon">▦</span>Academic setup</a>
  @endif
  <div class="tg-side-label">Platform</div>
  @if($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','COORDINATOR'])->isNotEmpty())
    <a class="tg-side-link" href="{{ route('tagore.communication.index') }}"><span class="tg-side-icon">✉</span>Communication</a>
  @endif
  @if($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','COORDINATOR','ACCOUNTS'])->isNotEmpty())
    <a class="tg-side-link" href="{{ route('tagore.reports.index') }}"><span class="tg-side-icon">▥</span>Reports</a>
  @endif
  <a class="tg-side-link" href="{{ route('tagore.operations.index') }}"><span class="tg-side-icon">⋮</span>All operations</a>
</aside>

<main class="tg-main">
  <section class="tg-hero">
    <h1>Accounts & fee collection</h1>
    <p>One operational view of fee balances, family-level dues, receipts and online settlement reconciliation for your permitted institutions.</p>
    <div class="tg-hero-actions">
      @if($roles->intersect(['OWNER','PRINCIPAL'])->isNotEmpty())
        <a class="tg-btn" href="{{ route('tagore.fees.import') }}">⇧ Import legacy fees</a>
      @endif
      @if($roles->intersect(['OWNER','PRINCIPAL','ACCOUNTS'])->isNotEmpty())
        <a class="tg-btn" href="{{ route('tagore.fees.manage') }}">⚙ Manage fee structures</a>
      @endif
      <a class="tg-btn" href="#transactions">View transactions ↓</a>
    </div>
  </section>

  <section class="tg-grid" aria-label="Fee summary">
    <div class="tg-card tg-kpi"><div class="tg-kpi-top"><span class="tg-kpi-label">Gross fees</span><span class="tg-kpi-icon">₹</span></div><div class="tg-kpi-value">₹{{ number_format($summary->gross ?? 0,0) }}</div><div class="tg-kpi-meta">Before reductions</div></div>
    <div class="tg-card tg-kpi"><div class="tg-kpi-top"><span class="tg-kpi-label">Discounts</span><span class="tg-kpi-icon">↘</span></div><div class="tg-kpi-value">₹{{ number_format($summary->discount ?? 0,0) }}</div><div class="tg-kpi-meta">Configured fee discounts</div></div>
    <div class="tg-card tg-kpi"><div class="tg-kpi-top"><span class="tg-kpi-label">Concessions</span><span class="tg-kpi-icon">◇</span></div><div class="tg-kpi-value">₹{{ number_format($summary->concession ?? 0,0) }}</div><div class="tg-kpi-meta">Approved concessions</div></div>
    <div class="tg-card tg-kpi"><div class="tg-kpi-top"><span class="tg-kpi-label">Net payable</span><span class="tg-kpi-icon">▤</span></div><div class="tg-kpi-value">₹{{ number_format($summary->payable ?? 0,0) }}</div><div class="tg-kpi-meta">After reductions</div></div>
    <div class="tg-card tg-kpi"><div class="tg-kpi-top"><span class="tg-kpi-label">Collected</span><span class="tg-kpi-icon">✓</span></div><div class="tg-kpi-value">₹{{ number_format($summary->paid ?? 0,0) }}</div><div class="tg-kpi-meta">Posted payments</div></div>
    <div class="tg-card tg-kpi"><div class="tg-kpi-top"><span class="tg-kpi-label">Outstanding</span><span class="tg-kpi-icon">!</span></div><div class="tg-kpi-value">₹{{ number_format($summary->outstanding ?? 0,0) }}</div><div class="tg-kpi-meta">Balances still due</div></div>
  </section>

  <section class="tg-section">
    <div class="tg-section-head"><div><h2>Institutions in your scope</h2><p>Only institutions you are authorised to view are included in the totals below.</p></div><span class="tg-status success">{{ $institutions->count() }} visible</span></div>
    <div class="tg-card" style="padding:16px">
      <div class="tg-actions" style="gap:8px">
      @forelse($institutions as $institution)
        <span class="tg-accounts-pill">{{ $institution->display_name }}</span>
      @empty
        <span class="tg-accounts-subtle">No permitted institutions are assigned to this account.</span>
      @endforelse
      </div>
    </div>
  </section>

  <section class="tg-section">
    <div class="tg-section-head"><div><h2>Family fee balances</h2><p>Children linked to the same guardian are consolidated into one family-level balance.</p></div></div>
    <div class="tg-card" style="padding:16px">
      <div class="tg-accounts-tools"><input type="search" class="tg-local-filter" data-table="family-table" placeholder="Filter families by parent, mobile or status…" aria-label="Filter family fee balances"><span class="tg-accounts-subtle">{{ $familyBalances->count() }} families · up to 500 shown</span></div>
      <div class="tg-table-wrap"><table class="table tg-accounts-table" id="family-table"><thead><tr><th>Parent / guardian</th><th>Contact</th><th>Children</th><th>Total payable</th><th>Total paid</th><th>Outstanding</th><th>Status</th></tr></thead><tbody>
      @forelse($familyBalances as $family)
        <tr><td><strong>{{ $family->parent_name }}</strong><div class="tg-accounts-subtle">Account #{{ $family->parent_user_id }}</div></td><td>{{ $family->mobile_no ?: '—' }}</td><td>{{ $family->children_count }}</td><td class="tg-accounts-number">₹{{ number_format($family->payable,2) }}</td><td class="tg-accounts-number">₹{{ number_format($family->paid,2) }}</td><td class="tg-accounts-number"><strong>₹{{ number_format($family->outstanding,2) }}</strong></td><td><span class="tg-accounts-pill {{ $family->outstanding > 0 ? 'pending' : 'matched' }}">{{ $family->outstanding > 0 ? 'Pending' : 'Clear' }}</span></td></tr>
      @empty
        <tr><td colspan="7"><div class="tg-accounts-empty"><strong>No family balances yet</strong>Linked parent accounts with fee records will appear here.</div></td></tr>
      @endforelse
      </tbody></table></div>
    </div>
  </section>

  <section class="tg-section">
    <div class="tg-section-head"><div><h2>Online settlement reconciliation</h2><p>Match successful online payments against the gateway settlement report. Mismatches are reviewed without rewriting the original fee ledger.</p></div><span class="tg-status {{ $pendingReconciliation ? 'warning' : 'success' }}">{{ $pendingReconciliation }} pending</span></div>
    <div class="tg-card" style="padding:16px">
      @if($pendingReconciliation)
        <div class="tg-list-item"><div><div class="tg-list-title">Payments awaiting settlement matching</div><div class="tg-list-meta">Open the relevant transaction below and record its settlement ID, amount and date.</div></div><span class="tg-status warning">{{ $pendingReconciliation }} to review</span></div>
      @else
        <div class="tg-list-item"><div><div class="tg-list-title">No pending online settlements</div><div class="tg-list-meta">There are currently no successful online payments awaiting reconciliation in your visible scope.</div></div><span class="tg-status success">Up to date</span></div>
      @endif
    </div>
  </section>

  <section class="tg-section" id="transactions">
    <div class="tg-section-head"><div><h2>Payment transactions</h2><p>Recent successful payments, receipts and gateway settlement references.</p></div><span class="tg-status">{{ $recent->count() }} recent records</span></div>
    <div class="tg-card" style="padding:16px">
      <div class="tg-accounts-tools"><input type="search" class="tg-local-filter" data-table="payments-table" placeholder="Filter by receipt, student, gateway or reference…" aria-label="Filter payment transactions"><span class="tg-accounts-subtle">Latest 100 successful payments</span></div>
      <div class="tg-table-wrap"><table class="table tg-accounts-table" id="payments-table"><thead><tr><th>Receipt</th><th>Student</th><th>Institution</th><th>Paid at</th><th>Mode / gateway</th><th>Gateway IDs</th><th>Reference</th><th>Settlement</th><th>Amount</th><th>Actions</th></tr></thead><tbody>
      @forelse($recent as $payment)
        <tr>
          <td><strong>{{ $payment->receipt_no ?: '—' }}</strong></td><td>{{ $payment->student }}</td><td>{{ $payment->institution }}</td><td>{{ $payment->paid_at ? \Carbon\Carbon::parse($payment->paid_at)->format('d M Y, H:i') : '—' }}</td><td>{{ ucfirst(str_replace('_',' ',$payment->payment_mode ?: ($payment->gateway ?: '—'))) }}</td><td><span class="tg-accounts-subtle">{{ $payment->gateway_order_id ?: '—' }}<br>{{ $payment->gateway_payment_id ?: '—' }}</span></td><td>{{ $payment->reference_number ?: '—' }}</td>
          <td><span class="tg-accounts-pill {{ ($payment->reconciliation_status ?: 'pending') === 'matched' ? 'matched' : ((($payment->reconciliation_status ?: 'pending') === 'mismatch') ? 'mismatch' : 'pending') }}">{{ ucfirst($payment->reconciliation_status ?: 'pending') }}</span>@if($payment->settlement_id)<div class="tg-accounts-subtle">{{ $payment->settlement_id }}<br>₹{{ number_format($payment->settlement_amount,2) }}</div>@endif</td>
          <td class="tg-accounts-number"><strong>₹{{ number_format($payment->amount,2) }}</strong></td>
          <td><a class="tg-btn" href="{{ route('tagore.fees.receipt',$payment->id) }}">Receipt ↗</a>
            @if(in_array($payment->gateway,['razorpay','online'],true))
              <details><summary>Reconcile settlement</summary>
                <form class="tg-accounts-form" method="POST" action="{{ route('tagore.fees.reconcile',$payment->id) }}">@csrf
                  <input name="settlement_id" placeholder="Settlement ID" aria-label="Settlement ID" value="{{ $payment->settlement_id }}">
                  <input name="settlement_amount" type="number" min="0" step="0.01" placeholder="Settlement amount" aria-label="Settlement amount" value="{{ $payment->settlement_amount ?? $payment->amount }}" required>
                  <input name="settlement_date" type="date" aria-label="Settlement date" value="{{ $payment->settlement_date ?? now()->toDateString() }}" required>
                  <input name="notes" placeholder="Notes" aria-label="Reconciliation notes">
                  <button class="tg-btn primary wide" type="submit">Save reconciliation</button>
                </form>
              </details>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="10"><div class="tg-accounts-empty"><strong>No successful payments recorded</strong>Payments will appear here after they are posted to the ledger.</div></td></tr>
      @endforelse
      </tbody></table></div>
    </div>
  </section>
</main>
</div>
</div>
<script>
(function(){
  function filterTable(input){
    const table=document.getElementById(input.dataset.table);
    if(!table)return;
    const query=(input.value||'').trim().toLocaleLowerCase();
    let visible=0;
    table.querySelectorAll('tbody tr').forEach(function(row){
      const match=!query||row.innerText.toLocaleLowerCase().includes(query);
      row.hidden=!match;
      if(match)visible++;
    });
    let status=input.parentElement.querySelector('[data-filter-status]');
    if(!status){status=document.createElement('span');status.dataset.filterStatus='1';status.className='tg-accounts-subtle';status.setAttribute('aria-live','polite');input.parentElement.appendChild(status);}
    status.textContent=query?visible+' matching rows':'';
  }
  document.querySelectorAll('.tg-local-filter').forEach(function(input){
    input.addEventListener('input',function(){filterTable(input);});
  });
  const global=document.getElementById('tg-accounts-search');
  if(global){
    global.addEventListener('input',function(){
      const q=global.value.trim().toLocaleLowerCase();
      document.querySelectorAll('.tg-accounts-table tbody tr').forEach(function(row){
        row.hidden=!!q&&!row.innerText.toLocaleLowerCase().includes(q);
      });
      document.querySelectorAll('.tg-accounts-table').forEach(function(table){
        table.querySelectorAll('tbody tr').forEach(function(row){
          if(!q)row.hidden=false;
        });
      });
      document.querySelectorAll('.tg-local-filter').forEach(function(input){
        if(q){input.value='';}
      });
    });
  }
})();
</script>
</body>
</html>
