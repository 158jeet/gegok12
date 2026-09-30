@php
    $isManager = $managerCommand['is_manager'];
    $roleLabel = $roles->map(fn($r) => ucwords(strtolower(str_replace('_',' ',$r))))->implode(' · ');
    $hour = (int) now()->format('H');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard · Tagore ERP</title><link rel="stylesheet" href="{{ asset('tagore-erp.css') }}">
</head>
<body class="tg-app">
<div class="tg-shell">
<header class="tg-topbar">
  <a class="tg-brand" href="{{ route('tagore.dashboard') }}" style="text-decoration:none">
    <div class="tg-logo">T</div><div><strong>Tagore ERP</strong><span>Tagore Group of Institutions</span></div>
  </a>
  <div class="tg-top-actions">
    <div class="tg-search">⌕&nbsp;&nbsp;Search students, staff, fees, tasks…</div>
    <div class="tg-user"><div class="tg-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'T',0,1)) }}</div><span>{{ auth()->user()->name ?? 'User' }}</span></div>
  </div>
</header>

<div class="tg-layout">
<aside class="tg-sidebar">
  <div class="tg-side-label">Workspace</div>
  <a class="tg-side-link active" href="{{ route('tagore.dashboard') }}"><span class="tg-side-icon">⌂</span>Overview</a>
  @if($roles->intersect(['OWNER','PRINCIPAL','COORDINATOR'])->isNotEmpty())
    <a class="tg-side-link" href="{{ route('tagore.admissions.index') }}"><span class="tg-side-icon">↗</span>Admissions</a>
    <a class="tg-side-link" href="{{ route('tagore.tasks.index') }}"><span class="tg-side-icon">✓</span>Work management</a>
    <a class="tg-side-link" href="{{ route('tagore.staff.leave') }}"><span class="tg-side-icon">◷</span>People & leave</a>
  @endif
  @if($roles->intersect(['OWNER','PRINCIPAL','COORDINATOR','ACCOUNTS'])->isNotEmpty())
    <a class="tg-side-link" href="{{ route('tagore.fees.manage') }}"><span class="tg-side-icon">₹</span>Fee management</a>
    <a class="tg-side-link" href="{{ route('tagore.fees.accounts') }}"><span class="tg-side-icon">▤</span>Accounts</a>
  @endif
  @if($roles->intersect(['OWNER','PRINCIPAL','COORDINATOR','TEACHER','ACCOUNTS'])->isNotEmpty())
    <a class="tg-side-link" href="{{ route('tagore.staff.self') }}"><span class="tg-side-icon">●</span>My workspace</a>
  @endif
  @if($roles->contains('OWNER'))
    <div class="tg-side-label">Administration</div>
    <a class="tg-side-link" href="{{ route('tagore.admin') }}"><span class="tg-side-icon">⚙</span>Administration</a>
    <a class="tg-side-link" href="{{ route('tagore.academic.structure') }}"><span class="tg-side-icon">▦</span>Academic setup</a>
  @endif
  <div class="tg-side-label">Platform</div>
  <a class="tg-side-link" href="{{ route('tagore.communication.index') }}"><span class="tg-side-icon">✉</span>Communication</a>
  <a class="tg-side-link" href="{{ route('tagore.reports.index') }}"><span class="tg-side-icon">▥</span>Reports</a>
  <a class="tg-side-link" href="{{ route('tagore.operations.index') }}"><span class="tg-side-icon">⋮</span>All operations</a>
</aside>

<main class="tg-main">
  <section class="tg-hero">
    <h1>{{ $greeting }}, {{ auth()->user()->name ?? 'there' }}.</h1>
    <p>{{ $roleLabel ?: 'Tagore ERP user' }} · Your school operations, people, academics and finance in one workspace.</p>
    <div class="tg-hero-actions">
      @if($roles->intersect(['OWNER','PRINCIPAL','COORDINATOR'])->isNotEmpty())
        <a class="tg-btn primary" href="{{ route('tagore.admissions.create') }}">＋ New admission</a>
        <a class="tg-btn" href="{{ route('tagore.tasks.index') }}">Create task</a>
      @endif
      @if($roles->intersect(['OWNER','PRINCIPAL','COORDINATOR','ACCOUNTS'])->isNotEmpty())
        <a class="tg-btn" href="{{ route('tagore.fees.accounts') }}">Open accounts</a>
      @endif
      @if($roles->contains('PARENT'))
        <a class="tg-btn primary" href="{{ route('tagore.parent.dashboard') }}">My children</a>
      @endif
    </div>
  </section>

  <section class="tg-grid">
    <div class="tg-card tg-kpi"><div class="tg-kpi-top"><span class="tg-kpi-label">Students</span><span class="tg-kpi-icon">●</span></div><div class="tg-kpi-value">{{ number_format($erp['students']) }}</div><div class="tg-kpi-meta">Across your visible institutions</div></div>
    <div class="tg-card tg-kpi"><div class="tg-kpi-top"><span class="tg-kpi-label">Staff</span><span class="tg-kpi-icon">♙</span></div><div class="tg-kpi-value">{{ number_format($erp['staff']) }}</div><div class="tg-kpi-meta">{{ $erp['departments'] }} active departments</div></div>
    <div class="tg-card tg-kpi"><div class="tg-kpi-top"><span class="tg-kpi-label">Fee outstanding</span><span class="tg-kpi-icon">₹</span></div><div class="tg-kpi-value">₹{{ number_format($erp['fee_outstanding'],0) }}</div><div class="tg-kpi-meta">Pending, partial & overdue</div></div>
    <div class="tg-card tg-kpi"><div class="tg-kpi-top"><span class="tg-kpi-label">Active work</span><span class="tg-kpi-icon">✓</span></div><div class="tg-kpi-value">{{ number_format($erp['active_tasks']) }}</div><div class="tg-kpi-meta">{{ $erp['pending_leave'] }} leave requests pending</div></div>
  </section>

  @if($isManager)
  <section class="tg-section">
    <div class="tg-section-head"><div><h2>Command center</h2><p>What needs attention today, without digging through modules.</p></div><div class="tg-actions"><a class="tg-btn" href="{{ route('tagore.tasks.index') }}">Open work board →</a></div></div>
    <div class="tg-two">
      <div class="tg-card" style="padding:18px">
        <div class="tg-section-head"><div><h2>Needs attention</h2><p>Priority work across your institutions</p></div><span class="tg-status {{ $stats['overdue_tasks'] ? 'danger' : 'success' }}">{{ $stats['overdue_tasks'] ? $stats['overdue_tasks'].' overdue' : 'On track' }}</span></div>
        <div class="tg-list">
        @forelse($managerCommand['action_items']->take(6) as $item)
          <a class="tg-list-item" style="text-decoration:none" href="{{ route('tagore.tasks.index') }}">
            <div><div class="tg-list-title">{{ $item->title }}</div><div class="tg-list-meta">{{ $item->department ?: 'General' }} · {{ $item->assignee ?: 'Unassigned' }}</div></div>
            <span class="tg-status {{ $item->status === 'blocked' ? 'danger' : (($item->due_at && strtotime($item->due_at) < time()) ? 'warning' : '') }}">{{ ucfirst(str_replace('_',' ',$item->status)) }}</span>
          </a>
        @empty
          <div class="tg-empty"><strong>Everything is clear</strong><span>No urgent work is waiting for attention.</span></div>
        @endforelse
        </div>
      </div>
      <div class="tg-card" style="padding:18px">
        <div class="tg-section-head"><div><h2>Workload</h2><p>Last {{ $analyticsDays }} days</p></div><span class="tg-status success">{{ $managerCommand['analytics']['completion_rate'] }}% completion</span></div>
        <div class="tg-chart">
        @php $maxTrend=max(1,(int)$managerCommand['workload_trend']->max('created')); @endphp
        @foreach($managerCommand['workload_trend']->take(14) as $day)
          <div class="tg-bar-wrap"><div class="tg-bar" title="{{ $day->created }} created / {{ $day->completed }} completed" style="height:{{ max(5,min(100,($day->created/$maxTrend)*100)) }}%"></div><span class="tg-bar-label">{{ date('d',strtotime($day->day)) }}</span></div>
        @endforeach
        </div>
        <div class="tg-three">
          <div><div class="tg-kpi-label">Created</div><strong>{{ $managerCommand['analytics']['created'] }}</strong></div>
          <div><div class="tg-kpi-label">Completed</div><strong>{{ $managerCommand['analytics']['completed'] }}</strong></div>
          <div><div class="tg-kpi-label">Net</div><strong>{{ $managerCommand['analytics']['net'] }}</strong></div>
        </div>
      </div>
    </div>
  </section>
  @endif

  <section class="tg-section">
    <div class="tg-section-head"><div><h2>Go to work</h2><p>Common actions arranged around how your team actually works.</p></div></div>
    <div class="tg-module-grid">
      @if($roles->intersect(['OWNER','PRINCIPAL','COORDINATOR'])->isNotEmpty())
      <a class="tg-module" href="{{ route('tagore.admissions.index') }}"><div class="tg-module-icon">↗</div><b>Admissions CRM</b><span>{{ $erp['admission_leads'] }} leads · {{ $erp['pending_leads'] }} need follow-up</span></a>
      @endif
      @if($roles->intersect(['OWNER','PRINCIPAL','COORDINATOR','ACCOUNTS'])->isNotEmpty())
      <a class="tg-module" href="{{ route('tagore.fees.manage') }}"><div class="tg-module-icon">₹</div><b>Fee management</b><span>Fee structures, demands and assignments</span></a>
      <a class="tg-module" href="{{ route('tagore.fees.accounts') }}"><div class="tg-module-icon">▤</div><b>Accounts</b><span>Collections, receipts and reconciliation</span></a>
      @endif
      @if($roles->intersect(['OWNER','PRINCIPAL','COORDINATOR','TEACHER','ACCOUNTS'])->isNotEmpty())
      <a class="tg-module" href="{{ route('tagore.tasks.index') }}"><div class="tg-module-icon">✓</div><b>Work management</b><span>Assignments, progress, reviews and recurring work</span></a>
      <a class="tg-module" href="{{ route('tagore.staff.self') }}"><div class="tg-module-icon">●</div><b>My workspace</b><span>Your leave, attendance and staff actions</span></a>
      @endif
      @if($roles->intersect(['OWNER','PRINCIPAL','COORDINATOR'])->isNotEmpty())
      <a class="tg-module" href="{{ route('tagore.staff.leave') }}"><div class="tg-module-icon">◷</div><b>People & leave</b><span>Review staff requests and workforce activity</span></a>
      @endif
      @if($roles->contains('OWNER'))
      <a class="tg-module" href="{{ route('tagore.admin') }}"><div class="tg-module-icon">⚙</div><b>Administration</b><span>Institutions, roles and departments</span></a>
      <a class="tg-module" href="{{ route('tagore.academic.structure') }}"><div class="tg-module-icon">▦</div><b>Academic setup</b><span>Streams, sections and academic years</span></a>
      @endif
      <a class="tg-module" href="{{ route('tagore.communication.index') }}"><div class="tg-module-icon">✉</div><b>Communication</b><span>Announcements, campaigns and parent communication</span></a>
      <a class="tg-module" href="{{ route('tagore.reports.index') }}"><div class="tg-module-icon">▥</div><b>Reports & analytics</b><span>Turn operational data into decisions</span></a>
      <a class="tg-module" href="{{ route('tagore.operations.index') }}"><div class="tg-module-icon">⋮</div><b>All operations</b><span>Transport, inventory, payroll, security, hostel and more</span></a>
    </div>
  </section>

  <section class="tg-section tg-two">
    <div class="tg-card" style="padding:18px">
      <div class="tg-section-head"><div><h2>Institutions</h2><p>Your Tagore Group operating units</p></div></div>
      @if($institutions->isEmpty())
        <div class="tg-empty"><strong>No institutions linked</strong><span>Run the initial setup to connect your school data.</span></div>
      @else
        <div class="tg-list">@foreach($institutions->take(6) as $institution)<div class="tg-list-item"><div><div class="tg-list-title">{{ $institution->display_name }}</div><div class="tg-list-meta">{{ $institution->code }} · {{ $institution->school_name }}</div></div><span class="tg-status success">Active</span></div>@endforeach</div>
      @endif
    </div>
    <div class="tg-card" style="padding:18px">
      <div class="tg-section-head"><div><h2>Quick snapshot</h2><p>Operational signals</p></div></div>
      <div class="tg-list">
        <div class="tg-list-item"><div><div class="tg-list-title">Admissions pipeline</div><div class="tg-list-meta">{{ $erp['pending_leads'] }} leads need follow-up</div></div><span class="tg-status {{ $erp['pending_leads'] ? 'warning' : 'success' }}">{{ $erp['pending_leads'] ? 'Action' : 'Clear' }}</span></div>
        <div class="tg-list-item"><div><div class="tg-list-title">Fee collection</div><div class="tg-list-meta">₹{{ number_format($erp['fee_collected'],0) }} collected</div></div><span class="tg-status success">Tracked</span></div>
        <div class="tg-list-item"><div><div class="tg-list-title">Staff requests</div><div class="tg-list-meta">{{ $erp['pending_leave'] }} pending leave requests</div></div><span class="tg-status {{ $erp['pending_leave'] ? 'warning' : 'success' }}">{{ $erp['pending_leave'] ? 'Review' : 'Clear' }}</span></div>
      </div>
    </div>
  </section>
</main>
</div>
<nav class="tg-mobile-nav">
<a href="{{ route('tagore.dashboard') }}"><b>⌂</b>Home</a>
@if($roles->intersect(['OWNER','PRINCIPAL','COORDINATOR'])->isNotEmpty())<a href="{{ route('tagore.admissions.index') }}"><b>↗</b>Admissions</a>@endif
@if($roles->intersect(['OWNER','PRINCIPAL','COORDINATOR','TEACHER','ACCOUNTS'])->isNotEmpty())<a href="{{ route('tagore.tasks.index') }}"><b>✓</b>Tasks</a>@endif
<a href="{{ route('tagore.communication.index') }}"><b>✉</b>Messages</a>
<a href="{{ route('tagore.reports.index') }}"><b>▥</b>Reports</a>
</nav>
</div>
</body></html>