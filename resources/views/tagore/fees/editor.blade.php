<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Fee Editor · TagoreK12</title><link rel="stylesheet" href="{{ asset('tagore-erp.css') }}"></head>
<body><main class="tg-shell">
<header class="tg-topbar"><div class="tg-brand"><div class="tg-logo">T</div><div><strong>TagoreK12</strong><span>Fee administration</span></div></div><div class="tg-user">Controlled Editor</div></header>
<nav class="tg-nav"><a href="{{ route('tagore.dashboard') }}">ERP Home</a><a href="{{ route('tagore.fees.accounts') }}">Accounts</a><a href="{{ route('tagore.fees.manage') }}">Fee Management</a><a href="{{ route('tagore.fees.editor') }}">Fee Editor</a>@if(auth()->user())<a href="{{ route('tagore.fees.vault') }}">Private Vault</a>@endif</nav>
<section class="tg-hero"><h1>Fee Editor</h1><p>Make controlled fee adjustments without touching opening balances or the original archived financial record.</p></section>
@if(session('success'))<div class="tg-alert" style="margin-bottom:16px">{{ session('success') }}</div>@endif
<section class="card" style="padding:22px"><div class="tg-section-head"><h2>Bulk fee adjustment</h2><span class="tg-kpi-label">Audited · opening balances protected</span></div>
<form method="post" action="{{ route('tagore.fees.editor.bulk') }}" class="tg-form">@csrf
<div class="tg-field"><label>Institution</label><select name="institution_id" required><option value="">Select institution</option>@foreach($institutions as $i)<option value="{{ $i->id }}">{{ $i->display_name }}</option>@endforeach</select></div>
<div class="tg-field"><label>Academic year ID</label><input name="academic_year_id" type="number" min="1" placeholder="Optional"></div>
<div class="tg-field"><label>Fee structure ID</label><input name="fee_structure_id" type="number" min="1" placeholder="Optional"></div>
<div class="tg-field"><label>Student ID</label><input name="student_id" type="number" min="1" placeholder="Optional"></div>
<div class="tg-field"><label>Minimum adjustment %</label><input name="min_percent" type="number" step="0.01" min="-100" max="100" required placeholder="e.g. 30"></div>
<div class="tg-field"><label>Maximum adjustment %</label><input name="max_percent" type="number" step="0.01" min="-100" max="100" required placeholder="e.g. 40"></div>
<div style="grid-column:1/-1"><button type="submit">Apply controlled adjustment</button></div>
</form>
<div class="tg-alert warning" style="margin-top:16px"><strong>How the range works:</strong> if you enter 30–40%, every eligible fee record receives its own independently generated percentage inside that range. The exact percentage and before/after amounts are written to the audit trail. Opening-balance records are always excluded.</div>
</section>
<section class="card" style="padding:22px;margin-top:18px"><div class="tg-section-head"><h2>Close an academic year</h2><span class="tg-kpi-label">Owner only</span></div>
<form method="post" action="{{ route('tagore.fees.editor.close-year') }}" class="tg-form">@csrf
<div class="tg-field"><label>Institution ID</label><input name="institution_id" type="number" min="1" required></div>
<div class="tg-field"><label>Academic year ID</label><input name="academic_year_id" type="number" min="1" required></div>
<div style="align-self:end"><button type="submit">Archive original records</button></div>
</form>
<p class="tg-kpi-label" style="margin-top:13px">The archive is encrypted in private application storage, hashed for integrity, and separated from normal ERP editing. It is not shown to parents or ordinary staff.</p>
</section>
</main></body></html>