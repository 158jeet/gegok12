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
<title>Dashboard · Tagore ERP</title><link rel="icon" type="image/webp" href="{{ asset('uploads/tgi-logo.webp') }}"><link rel="stylesheet" href="{{ asset('tagore-erp.css') . '?v=20261029-ui10' }}">
</head>
<body class="tg-app">
<div class="tg-shell">
<header class="tg-topbar">
  <a class="tg-brand" href="{{ route('tagore.dashboard') }}" style="text-decoration:none">
    <img class="tg-logo" src="{{ asset('uploads/tgi-logo.webp') }}" alt="Tagore Group of Institutions"><div><strong>Tagore ERP</strong><span>Tagore Group of Institutions</span></div>
  </a>
  <div class="tg-top-actions">
    <label class="tg-search" for="tg-dashboard-search" style="display:flex;align-items:center;gap:8px">⌕ <input id="tg-dashboard-search" type="search" placeholder="Search dashboard modules and items…" aria-label="Search dashboard modules and visible items" autocomplete="off" style="min-width:0;width:220px;border:0;outline:0;background:transparent;color:inherit;font:inherit"></label>
    <div class="tg-user"><div class="tg-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'T',0,1)) }}</div><span>{{ auth()->user()->name ?? 'User' }}</span></div>
  </div>
</header>

<p id="tg-search-status" class="tg-search-status" role="status" aria-live="polite" hidden></p>
<div class="tg-layout">
<aside class="tg-sidebar">
  <div class="tg-side-label">Workspace</div>
  <a class="tg-side-link active" href="{{ route('tagore.dashboard') }}"><span class="tg-side-icon">⌂</span>Overview</a>
  @if($roles->intersect(['OWNER','PRINCIPAL','COORDINATOR'])->isNotEmpty())
    <a class="tg-side-link" href="{{ route('tagore.admissions.index') }}"><span class="tg-side-icon">↗</span>Admissions</a>
    <a class="tg-side-link" href="{{ route('tagore.tasks.index') }}"><span class="tg-side-icon">✓</span>Work management</a>
    <a class="tg-side-link" href="{{ route('tagore.staff.leave') }}"><span class="tg-side-icon">◷</span>People & leave</a>
  @endif
  @if($roles->intersect(['OWNER','PRINCIPAL','ACCOUNTS'])->isNotEmpty())
    <a class="tg-side-link" href="{{ route('tagore.fees.manage') }}"><span class="tg-side-icon">₹</span>Fee management</a>
  @endif
  @if($roles->contains('FEE_EDITOR'))
    <a class="tg-side-link" href="{{ route('tagore.fees.editor') }}"><span class="tg-side-icon">₹</span>Fee editor</a>
  @endif
  @if($roles->intersect(['OWNER','PRINCIPAL','COORDINATOR','ACCOUNTS'])->isNotEmpty())
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
  @if($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','COORDINATOR','ACCOUNTS','HR','TRANSPORT','HOSTEL','TEACHER'])->isNotEmpty())
  <div class="tg-side-label">Platform</div>
  @if($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','COORDINATOR'])->isNotEmpty())
    <a class="tg-side-link" href="{{ route('tagore.communication.index') }}"><span class="tg-side-icon">✉</span>Communication</a>
  @endif
  @if($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','COORDINATOR','ACCOUNTS'])->isNotEmpty())
    <a class="tg-side-link" href="{{ route('tagore.reports.index') }}"><span class="tg-side-icon">▥</span>Reports</a>
  @endif
  @if($roles->intersect(['OWNER','PRINCIPAL','COORDINATOR','ACCOUNTS','HR','TRANSPORT','HOSTEL','TEACHER'])->isNotEmpty())
    <a class="tg-side-link" href="{{ route('tagore.operations.index') }}"><span class="tg-side-icon">⋮</span>All operations</a>
  @endif
  @endif
</aside>

<main class="tg-main">
  <section class="tg-hero">
    <h1>{{ $greeting }}, {{ auth()->user()->name ?? 'there' }}.</h1>
    <p>{{ $roleLabel ?: 'Tagore ERP user' }} · @if($roles->contains('PARENT'))Your family, fees and school updates in one place.@elseif($roles->contains('STUDENT'))Your learning, attendance and school updates in one place.@else Your school operations, people, academics and finance in one workspace.@endif</p>
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
      @if($roles->intersect(['OWNER','PRINCIPAL','COORDINATOR','TEACHER'])->isNotEmpty() || $roles->contains('PARENT'))
        <a class="tg-btn" href="{{ route('tagore.diary.index') }}">Daily diary</a>
      @endif
    </div>
  </section>

  @if($roles->contains('PARENT'))
  <section class="tg-grid" aria-label="Family dashboard summary">
    <div class="tg-card tg-kpi"><div class="tg-kpi-top"><span class="tg-kpi-label">My children</span><span class="tg-kpi-icon">●</span></div><div class="tg-kpi-value">{{ number_format($stats['children']) }}</div><div class="tg-kpi-meta">Linked student accounts</div></div>
    <div class="tg-card tg-kpi"><div class="tg-kpi-top"><span class="tg-kpi-label">Fee items due</span><span class="tg-kpi-icon">₹</span></div><div class="tg-kpi-value">{{ number_format($stats['pending_fees']) }}</div><div class="tg-kpi-meta">Pending, partial or overdue items</div></div>
    <div class="tg-card tg-kpi"><div class="tg-kpi-top"><span class="tg-kpi-label">Open feedback</span><span class="tg-kpi-icon">✉</span></div><div class="tg-kpi-value">{{ number_format($stats['open_feedback']) }}</div><div class="tg-kpi-meta">Your requests awaiting review</div></div>
    <div class="tg-card tg-kpi"><div class="tg-kpi-top"><span class="tg-kpi-label">Schools</span><span class="tg-kpi-icon">⌂</span></div><div class="tg-kpi-value">{{ number_format($stats['institutions']) }}</div><div class="tg-kpi-meta">Institutions linked to your family</div></div>
  </section>
  @elseif(!$roles->contains('STUDENT'))
  <section class="tg-grid" aria-label="Institution dashboard summary">
    <div class="tg-card tg-kpi"><div class="tg-kpi-top"><span class="tg-kpi-label">Students</span><span class="tg-kpi-icon">●</span></div><div class="tg-kpi-value">{{ number_format($erp['students']) }}</div><div class="tg-kpi-meta">Across your visible institutions</div></div>
    <div class="tg-card tg-kpi"><div class="tg-kpi-top"><span class="tg-kpi-label">Staff</span><span class="tg-kpi-icon">♙</span></div><div class="tg-kpi-value">{{ number_format($erp['staff']) }}</div><div class="tg-kpi-meta">{{ $erp['departments'] }} active departments</div></div>
    <div class="tg-card tg-kpi"><div class="tg-kpi-top"><span class="tg-kpi-label">Fee outstanding</span><span class="tg-kpi-icon">₹</span></div><div class="tg-kpi-value">₹{{ number_format($erp['fee_outstanding'],0) }}</div><div class="tg-kpi-meta">Pending, partial & overdue</div></div>
    <div class="tg-card tg-kpi"><div class="tg-kpi-top"><span class="tg-kpi-label">Active work</span><span class="tg-kpi-icon">✓</span></div><div class="tg-kpi-value">{{ number_format($erp['active_tasks']) }}</div><div class="tg-kpi-meta">{{ $erp['pending_leave'] }} leave requests pending</div></div>
  </section>
  @else
  <section class="tg-card" style="padding:20px;margin-bottom:18px">
    <div class="tg-section-head"><div><h2>Your student workspace</h2><p>Your account is signed in. Use the student portal for classes, assignments, homework and results.</p></div><a class="tg-btn primary" href="{{ url('/student/dashboard') }}">Open student portal →</a></div>
  </section>
  @endif

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

  @if($isManager && $managerCommand['followups']->isNotEmpty())
  <section class="tg-section">
    <div class="tg-section-head">
      <div><h2>Pending manager follow-ups</h2><p>People and work items that need a management decision.</p></div>
      <span class="tg-status warning">{{ $managerCommand['followups']->count() }} open</span>
    </div>
    <div class="tg-card" style="padding:18px">
      <div class="tg-list">
      @foreach($managerCommand['followups']->take(6) as $followup)
        <div class="tg-list-item">
          <div>
            <div class="tg-list-title">{{ $followup->task_title ?: 'Manager follow-up' }}</div>
            <div class="tg-list-meta">{{ $followup->employee_name }} · {{ $followup->notes ?: ucfirst(str_replace('_',' ', $followup->outcome)) }}</div>
          </div>
          <span class="tg-status {{ $followup->state === 'overdue' ? 'danger' : 'warning' }}">{{ ucfirst($followup->state) }}</span>
        </div>
      @endforeach
      </div>
    </div>
  </section>
  @endif

  @if(!$roles->contains('STUDENT'))
  <section class="tg-section">
    <div class="tg-section-head"><div><h2>{{ $roles->contains('PARENT') ? 'Family shortcuts' : 'Go to work' }}</h2><p>{{ $roles->contains('PARENT') ? 'Quick access to your children’s school information.' : 'Common actions arranged around how your team actually works.' }}</p></div></div>
    <div class="tg-module-grid">
      @if($roles->contains('PARENT'))
      <a class="tg-module" href="{{ route('tagore.parent.dashboard') }}"><div class="tg-module-icon">●</div><b>My children</b><span>Statements, school details and family links</span></a>
      <a class="tg-module" href="{{ route('tagore.fees.wallet') }}"><div class="tg-module-icon">₹</div><b>Fee wallet</b><span>Review balances and available payment options</span></a>
      <a class="tg-module" href="{{ route('tagore.diary.index') }}"><div class="tg-module-icon">▤</div><b>Daily diary</b><span>School notices and day-to-day updates</span></a>
      @endif
      @if($roles->intersect(['OWNER','PRINCIPAL','COORDINATOR'])->isNotEmpty())
      <a class="tg-module" href="{{ route('tagore.admissions.index') }}"><div class="tg-module-icon">↗</div><b>Admissions CRM</b><span>{{ $erp['admission_leads'] }} leads · {{ $erp['pending_leads'] }} need follow-up</span></a>
      @endif
      @if($roles->intersect(['OWNER','PRINCIPAL','ACCOUNTS'])->isNotEmpty())
      <a class="tg-module" href="{{ route('tagore.fees.manage') }}"><div class="tg-module-icon">₹</div><b>Fee management</b><span>Fee structures, demands and assignments</span></a>
      @endif
      @if($roles->contains('FEE_EDITOR'))
      <a class="tg-module" href="{{ route('tagore.fees.editor') }}"><div class="tg-module-icon">₹</div><b>Fee editor</b><span>Adjust fee records and manage fee-editing tasks</span></a>
      @endif
      @if($roles->intersect(['OWNER','PRINCIPAL','COORDINATOR','ACCOUNTS'])->isNotEmpty())
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
      @if($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','COORDINATOR'])->isNotEmpty())
      <a class="tg-module" href="{{ route('tagore.communication.index') }}"><div class="tg-module-icon">✉</div><b>Communication</b><span>Announcements, campaigns and parent communication</span></a>
      @endif
      @if($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','COORDINATOR','ACCOUNTS'])->isNotEmpty())
      <a class="tg-module" href="{{ route('tagore.reports.index') }}"><div class="tg-module-icon">▥</div><b>Reports & analytics</b><span>Turn operational data into decisions</span></a>
      @endif
      @if($roles->intersect(['OWNER','PRINCIPAL','COORDINATOR','ACCOUNTS','HR','TRANSPORT','HOSTEL','TEACHER'])->isNotEmpty())
      <a class="tg-module" href="{{ route('tagore.operations.index') }}"><div class="tg-module-icon">⋮</div><b>All operations</b><span>Transport, inventory, payroll, security, hostel and more</span></a>
      @endif
    </div>
  </section>
  @endif

  @if(!$roles->contains('STUDENT'))
  <section class="tg-section tg-two">
    <div class="tg-card" style="padding:18px">
      <div class="tg-section-head"><div><h2>Institutions</h2><p>Your Tagore Group operating units</p></div></div>
      @if($institutions->isEmpty())
        <div class="tg-empty"><strong>No institutions linked</strong><span>Run the initial setup to connect your school data.</span></div>
      @else
        <div class="tg-list">@foreach($institutions->take(6) as $institution)<div class="tg-list-item"><div><div class="tg-list-title">{{ $institution->display_name }}</div><div class="tg-list-meta">{{ $institution->code }} · {{ $institution->school_name }}</div></div><span class="tg-status success">Active</span></div>@endforeach</div>
      @endif
    </div>
    @if(!$roles->contains('STUDENT'))
    <div class="tg-card" style="padding:18px">
      <div class="tg-section-head"><div><h2>Quick snapshot</h2><p>{{ $roles->contains('PARENT') ? 'Your family activity' : 'Operational signals' }}</p></div></div>
      <div class="tg-list">
        @if($roles->contains('PARENT'))
          <div class="tg-list-item"><div><div class="tg-list-title">Fee items</div><div class="tg-list-meta">{{ $stats['pending_fees'] }} items need payment or review</div></div><span class="tg-status {{ $stats['pending_fees'] ? 'warning' : 'success' }}">{{ $stats['pending_fees'] ? 'Review' : 'Clear' }}</span></div>
          <div class="tg-list-item"><div><div class="tg-list-title">Feedback</div><div class="tg-list-meta">{{ $stats['open_feedback'] }} open requests</div></div><span class="tg-status {{ $stats['open_feedback'] ? 'warning' : 'success' }}">{{ $stats['open_feedback'] ? 'Waiting' : 'Clear' }}</span></div>
          <div class="tg-list-item"><div><div class="tg-list-title">Family access</div><div class="tg-list-meta">{{ $stats['children'] }} linked children across {{ $stats['institutions'] }} schools</div></div><span class="tg-status success">Connected</span></div>
        @else
          <div class="tg-list-item"><div><div class="tg-list-title">Admissions pipeline</div><div class="tg-list-meta">{{ $erp['pending_leads'] }} leads need follow-up</div></div><span class="tg-status {{ $erp['pending_leads'] ? 'warning' : 'success' }}">{{ $erp['pending_leads'] ? 'Action' : 'Clear' }}</span></div>
          <div class="tg-list-item"><div><div class="tg-list-title">Fee collection</div><div class="tg-list-meta">₹{{ number_format($erp['fee_collected'],0) }} collected</div></div><span class="tg-status success">Tracked</span></div>
          <div class="tg-list-item"><div><div class="tg-list-title">Staff requests</div><div class="tg-list-meta">{{ $erp['pending_leave'] }} pending leave requests</div></div><span class="tg-status {{ $erp['pending_leave'] ? 'warning' : 'success' }}">{{ $erp['pending_leave'] ? 'Review' : 'Clear' }}</span></div>
        @endif
      </div>
    </div>
    @endif
  </section>
  @endif
</main>
</div>
<nav class="tg-mobile-nav">
<a href="{{ route('tagore.dashboard') }}"><b>⌂</b>Home</a>
@if($roles->contains('PARENT'))
<a href="{{ route('tagore.parent.dashboard') }}"><b>●</b>Children</a>
<a href="{{ route('tagore.fees.wallet') }}"><b>₹</b>Fees</a>
<a href="{{ route('tagore.diary.index') }}"><b>▤</b>Diary</a>
@elseif($roles->contains('STUDENT'))
<a href="{{ url('/student/dashboard') }}"><b>▦</b>Portal</a>
@endif
@if($roles->contains('FEE_EDITOR'))<a href="{{ route('tagore.fees.editor') }}"><b>₹</b>Fee editor</a>@endif
@if($roles->intersect(['OWNER','PRINCIPAL','COORDINATOR'])->isNotEmpty())<a href="{{ route('tagore.admissions.index') }}"><b>↗</b>Admissions</a>@endif
@if($roles->intersect(['OWNER','PRINCIPAL','COORDINATOR','TEACHER','ACCOUNTS'])->isNotEmpty())<a href="{{ route('tagore.tasks.index') }}"><b>✓</b>Tasks</a>@endif
@if($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','COORDINATOR'])->isNotEmpty())<a href="{{ route('tagore.communication.index') }}"><b>✉</b>Messages</a>@endif
@if($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','COORDINATOR','ACCOUNTS'])->isNotEmpty())<a href="{{ route('tagore.reports.index') }}"><b>▥</b>Reports</a>@endif
@if($roles->intersect(['OWNER','PRINCIPAL','COORDINATOR','ACCOUNTS','HR','TRANSPORT','HOSTEL','TEACHER'])->isNotEmpty())<a href="{{ route('tagore.operations.index') }}"><b>⋮</b>Operations</a>@endif
</nav>
</div>
<script>
(() => {
  const input = document.getElementById('tg-dashboard-search');
  const status = document.getElementById('tg-search-status');
  if (!input) return;

  const searchable = Array.from(document.querySelectorAll(
    '.tg-module, .tg-list-item, .tg-kpi, .tg-side-link'
  ));
  const groups = Array.from(document.querySelectorAll('.tg-list, .tg-module-grid, .tg-grid, .tg-sidebar'));
  const update = () => {
    const query = input.value.trim().toLocaleLowerCase();
    let matches = 0;

    searchable.forEach(item => {
      const matched = !query || (item.textContent || '').toLocaleLowerCase().includes(query);
      item.hidden = !matched;
      if (matched && query) matches++;
    });

    groups.forEach(group => {
      const children = Array.from(group.children).filter(child => searchable.includes(child));
      if (children.length) group.dataset.searchEmpty = String(query.length > 0 && children.every(child => child.hidden));
    });

    if (status) {
      status.hidden = !query;
      status.textContent = !query
        ? ''
        : matches
          ? matches + (matches === 1 ? ' matching item' : ' matching items') + ' · press Esc to clear'
          : 'No matching modules or items. Try a different search.';
      status.classList.toggle('is-empty', !!query && matches === 0);
    }
  };

  input.addEventListener('input', update);
  input.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      input.value = '';
      update();
      input.blur();
    }
  });
  document.addEventListener('keydown', event => {
    if ((event.ctrlKey || event.metaKey) && event.key === '/') {
      event.preventDefault();
      input.focus();
      input.select();
    }
  });
  update();
})();
</script>
</body></html>