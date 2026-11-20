<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Staff Performance | Tagore ERP</title>
  <link rel="stylesheet" href="{{ asset('tagore-erp.css') }}">
  <style>
    .tg-performance-table-wrap{width:100%;overflow-x:auto;border-radius:12px}
    .tg-performance-table{width:100%;border-collapse:collapse;min-width:760px}
    .tg-performance-table th,.tg-performance-table td{padding:12px 14px;border-bottom:1px solid var(--tg-line);text-align:left;vertical-align:middle}
    .tg-performance-table th{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--tg-muted);white-space:nowrap}
    .tg-performance-table td{font-size:13px}
    .tg-performance-table .tg-number{font-variant-numeric:tabular-nums;font-weight:750}
    .tg-performance-table .tg-staff-name{font-weight:750;display:block}
    .tg-performance-table .tg-sub{display:block;color:var(--tg-muted);font-size:11px;margin-top:3px}
    .tg-periods{display:flex;gap:8px;flex-wrap:wrap}
    .tg-periods a{padding:8px 12px;border:1px solid var(--tg-line);border-radius:9px;text-decoration:none;font-weight:700;font-size:12px}
    .tg-periods a[aria-current="page"]{background:var(--tg-primary);border-color:var(--tg-primary);color:#fff!important}
    .tg-performance-note{font-size:12px;color:var(--tg-muted);line-height:1.5}
    @media(max-width:760px){.tg-shell{padding-left:14px;padding-right:14px}.tg-hero{padding:20px}.tg-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
  </style>
</head>
<body class="tg-app">
<div class="tg-shell">
  <header class="tg-hero">
    <div class="tg-eyebrow">PEOPLE & OPERATIONS</div>
    <h1>Staff performance</h1>
    <p>Task completion, active workload and overdue work for your institution scope. These are operational indicators, not an automatic employee rating.</p>
    <div class="tg-hero-actions"><a class="tg-btn" href="{{ route('tagore.dashboard') }}">Dashboard</a><a class="tg-btn" href="{{ route('tagore.tasks.index') }}">Work tasks</a></div>
  </header>
  <section class="tg-section">
    <div class="tg-section-head"><div><h2>Reporting window</h2><p>Tasks created within the selected period</p></div><nav class="tg-periods" aria-label="Reporting period">
      @foreach([7,30,90] as $period)<a href="{{ route('tagore.performance.index', ['days'=>$period]) }}" @if($days===$period) aria-current="page" @endif>{{ $period }} days</a>@endforeach
    </nav></div>
  </section>
  <section class="tg-section tg-grid" aria-label="Performance summary">
    @foreach([['Staff',$totals['staff']],['Tasks assigned',$totals['assigned']],['Active tasks',$totals['active']],['Completed',$totals['completed']],['Overdue',$totals['overdue']],['Completion rate',$totals['completion_rate'].'%']] as [$label,$value])
      <article class="tg-card tg-kpi"><div class="tg-kpi-label">{{ $label }}</div><div class="tg-kpi-value">{{ $value }}</div><div class="tg-kpi-meta">Last {{ $days }} days</div></article>
    @endforeach
  </section>
  <section class="tg-section tg-card">
    <div class="tg-section-head"><div><h2>Staff workload</h2><p>Only users assigned active roles within your permitted institution scope are included.</p></div></div>
    <div class="tg-performance-table-wrap"><table class="tg-performance-table"><thead><tr><th>Staff member</th><th>Assigned</th><th>Active</th><th>Completed</th><th>Overdue</th><th>Completion</th><th>Avg. progress</th></tr></thead><tbody>
    @forelse($employees as $employee)
      <tr><td><span class="tg-staff-name">{{ $employee->name }}</span><span class="tg-sub">{{ $employee->designation ?: 'Staff' }} · {{ $employee->department ?: 'No department' }}</span></td><td class="tg-number">{{ $employee->assigned }}</td><td class="tg-number">{{ $employee->active }}</td><td class="tg-number">{{ $employee->completed }}</td><td class="tg-number">{{ $employee->overdue }}</td><td class="tg-number">{{ $employee->assigned ? round(($employee->completed / $employee->assigned) * 100, 1) : 0 }}%</td><td class="tg-number">{{ $employee->average_progress }}%</td></tr>
    @empty<tr><td colspan="7">No staff or task activity found for this period.</td></tr>@endforelse
    </tbody></table></div>
    <p class="tg-performance-note">Use the task history and manager review workflow for context. Completion percentages should not be used alone to judge staff performance.</p>
  </section>
</div>
</body>
</html>