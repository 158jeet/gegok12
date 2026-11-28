<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fee Assignment · Tagore ERP</title>
<link rel="stylesheet" href="{{ asset('tagore-erp.css') . '?v=20261128-cashier-fees1' }}">
<style>
.assign-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.assign-form{display:grid;gap:12px}
.assign-form label{display:grid;gap:6px;color:var(--tg-muted);font-size:12px}
.assign-form select,.assign-form input{width:100%;min-width:0}
.assign-table{min-width:680px}
.assign-note{padding:13px 15px;border:1px solid var(--tg-line);border-radius:12px;background:var(--tg-card);color:var(--tg-muted)}
.cashier-muted{font-size:11px;color:var(--tg-muted)}
@media(max-width:720px){.assign-grid{grid-template-columns:1fr}}
</style>
</head>
<body class="tg-app">
<div class="tg-shell">
<header class="tg-topbar">
  <a class="tg-brand" href="{{ route('tagore.dashboard') }}" style="text-decoration:none"><img class="tg-logo" src="{{ asset('uploads/tgi-logo.webp') }}" alt="Tagore Group of Institutions"><div><strong>Tagore ERP</strong><span>Cashier workspace</span></div></a>
  <div class="tg-top-actions"><div class="tg-user"><div class="tg-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'T',0,1)) }}</div><span>{{ auth()->user()->name ?? 'User' }}</span></div><a class="tg-btn" href="{{ route('tagore.profile.edit') }}">My profile</a><a class="tg-btn" href="{{ route('tagore.cashier.index') }}">Cashier desk</a><form method="POST" action="{{ route('logout') }}" style="margin:0">@csrf<button class="tg-btn" type="submit">Logout</button></form></div>
</header>
@if(session('success'))<div class="tg-alert success" role="status">{{ session('success') }}</div>@endif
@if(session('warning'))<div class="tg-alert" role="status">{{ session('warning') }}</div>@endif
@if($errors->any())<div class="tg-alert danger" role="alert">{{ $errors->first() }}</div>@endif
<div class="tg-layout">
<aside class="tg-sidebar">
  <div class="tg-side-label">Cashier workspace</div>
  <a class="tg-side-link" href="{{ route('tagore.cashier.index') }}"><span class="tg-side-icon">⌂</span>Cashier desk</a>
  <a class="tg-side-link active" href="{{ route('tagore.cashier.fees') }}"><span class="tg-side-icon">₹</span>Fee assignments</a>
  <a class="tg-side-link" href="{{ route('tagore.fees.accounts') }}"><span class="tg-side-icon">▤</span>Accounts & collections</a>
  <a class="tg-side-link" href="{{ route('tagore.admissions.index') }}"><span class="tg-side-icon">↗</span>Admissions</a>
  <div class="tg-side-label">Assigned institutions</div>
  @foreach($institutions as $institution)<span class="tg-side-link" style="cursor:default"><span class="tg-side-icon">▦</span>{{ $institution->display_name }}</span>@endforeach
</aside>
<main class="tg-main">
  <section class="tg-hero">
    <h1>Fee assignments</h1>
    <p>Apply an approved fee structure to a class and section in your assigned institution, then generate the student demands. Fee masters, amounts and concessions remain controlled by finance.</p>
    <div class="tg-hero-actions"><a class="tg-btn" href="{{ route('tagore.cashier.index') }}">← Cashier desk</a><a class="tg-btn" href="{{ route('tagore.fees.accounts') }}">Open collections</a></div>
  </section>
  <div class="assign-note">Cashier permission: assign an existing active fee structure and generate missing demands only. You cannot create fee heads, change amounts, approve concessions or edit an existing fee structure.</div>
  <section class="tg-section">
    <div class="tg-section-head"><div><h2>Assign an approved fee structure</h2><p>Choose a fee plan and the matching class/section.</p></div></div>
    <div class="tg-card" style="padding:18px">
      <form class="assign-form" method="POST" action="{{ route('tagore.cashier.fees.assign') }}">@csrf
        <div class="assign-grid">
          <label>Approved fee structure<select name="fee_structure_id" required><option value="">Select fee structure</option>@foreach($structures as $structure)<option value="{{ $structure->id }}">{{ $structure->institution }} · {{ $structure->name }} · {{ ucfirst($structure->frequency) }} · {{ $structure->academic_year ?: 'Current structure' }}</option>@endforeach</select></label>
          <label>Class and section<select name="standard_link_id" required><option value="">Select class / section</option>@foreach($classSections as $class)<option value="{{ $class->id }}">{{ $class->school }} · {{ $class->standard }} {{ $class->section }} · {{ $class->academic_year ?: 'Academic year not labelled' }}</option>@endforeach</select></label>
        </div>
        <div><button class="tg-btn primary" type="submit" @disabled($structures->isEmpty() || $classSections->isEmpty())>Assign fee structure</button></div>
      </form>
    </div>
  </section>
  <section class="tg-section">
    <div class="tg-section-head"><div><h2>Assigned fee plans</h2><p>Generate only missing student demands. Existing demands are skipped to prevent duplicate billing.</p></div><span class="tg-status">{{ $assignments->count() }} active assignments</span></div>
    <div class="tg-card" style="padding:14px">
      <div class="tg-table-wrap"><table class="table assign-table"><thead><tr><th>Institution / class</th><th>Fee structure</th><th>Academic year</th><th>Generate demands</th></tr></thead><tbody>
      @forelse($assignments as $assignment)
        <tr><td><strong>{{ $assignment->institution }}</strong><div class="cashier-muted">{{ $assignment->standard }} · {{ $assignment->section }}</div></td><td>{{ $assignment->fee_structure }}</td><td>{{ $assignment->academic_year ?: '—' }}</td><td>@php($preview = session('cashier_bulk_preview_'.$assignment->id)) @if(is_array($preview) && ($preview['expires_at'] ?? 0) >= now()->timestamp)<div class="cashier-muted">{{ $preview['eligible_count'] }} eligible · {{ $preview['new_count'] }} new · {{ $preview['existing_count'] }} already billed</div>@if($preview['new_count'] > 0)<form method="POST" action="{{ route('tagore.cashier.fees.generate',$assignment->id) }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:7px">@csrf<input type="date" name="due_date" value="{{ now()->addDays(30)->toDateString() }}" min="{{ today()->toDateString() }}" required aria-label="Fee due date"><button class="tg-btn primary" type="submit">Confirm {{ $preview['new_count'] }} demands</button></form>@else<span class="cashier-muted">No new demands to create</span>@endif @else<form method="POST" action="{{ route('tagore.cashier.fees.preview',$assignment->id) }}">@csrf<button class="tg-btn" type="submit">Preview demand creation</button></form>@endif</td></tr>
      @empty
        <tr><td colspan="4"><div class="tg-empty"><strong>No fee plans assigned yet</strong><span>Assign an approved fee structure to a class/section above.</span></div></td></tr>
      @endforelse
      </tbody></table></div>
    </div>
  </section>
</main>
</div>
</div>
</body>
</html>
