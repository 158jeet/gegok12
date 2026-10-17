<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Performance & Health | Tagore ERP</title>
<link rel="stylesheet" href="{{ asset('tagore-erp.css') }}">
<style>
.perf-shell{max-width:1240px;margin:0 auto;padding:28px 20px;color:var(--tg-text,#e8edf7)}
.perf-top{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:24px}
.perf-muted{color:var(--tg-muted,#94a3b8)}
.perf-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}
.perf-card{border:1px solid var(--tg-border,#273449);background:var(--tg-card,#111827);border-radius:16px;padding:20px;min-width:0}
.perf-label{font-size:13px;color:var(--tg-muted,#94a3b8);margin-bottom:10px}
.perf-value{font-size:26px;font-weight:750;line-height:1.25;overflow-wrap:anywhere}
.perf-note{font-size:12px;color:var(--tg-muted,#94a3b8);margin-top:8px}
.perf-status{display:inline-flex;padding:5px 9px;border-radius:999px;background:#123b2b;color:#86efac;font-size:12px;font-weight:700}
.perf-link{display:inline-block;margin-top:18px;color:#93c5fd;text-decoration:none}
@media(max-width:600px){.perf-shell{padding:18px 12px}.perf-value{font-size:22px}}
</style>
</head>
<body class="tg-app">
<main class="perf-shell">
<div class="perf-top">
<div><a class="perf-link" style="margin:0 0 12px" href="{{ route('tagore.dashboard') }}">← Back to dashboard</a>
<h1 style="font-size:28px;font-weight:800;margin:10px 0 6px">Performance & system health</h1>
<p class="perf-muted">Read-only diagnostics for the current application runtime. Last checked {{ $checkedAt->format('d M Y, h:i:s A') }}.</p></div>
<span class="perf-status">Database query successful</span>
</div>
<section class="perf-grid" aria-label="Runtime metrics">
<article class="perf-card"><div class="perf-label">Database round-trip</div><div class="perf-value">{{ number_format($databaseMs, 2) }} ms</div><div class="perf-note">Lightweight SELECT 1 check; not a load test.</div></article>
<article class="perf-card"><div class="perf-label">Peak PHP memory</div><div class="perf-value">{{ number_format($memoryMb, 1) }} MB</div><div class="perf-note">This request only · PHP limit {{ $memoryLimit }}</div></article>
<article class="perf-card"><div class="perf-label">PHP runtime</div><div class="perf-value">{{ $phpVersion }}</div><div class="perf-note">Laravel {{ $laravelVersion }}</div></article>
<article class="perf-card"><div class="perf-label">Application environment</div><div class="perf-value">{{ strtoupper($environment) }}</div><div class="perf-note">{{ $debugEnabled ? 'Debug mode is enabled' : 'Debug mode is disabled' }}</div></article>
<article class="perf-card"><div class="perf-label">Slow-request logging</div><div class="perf-value">{{ $slowRequestLogging ? 'Enabled' : 'Disabled' }}</div><div class="perf-note">Threshold: {{ $slowRequestThreshold }} ms</div></article>
<article class="perf-card"><div class="perf-label">Slow-request log markers</div><div class="perf-value">{{ is_null($slowRequestCount) ? 'Unavailable' : number_format($slowRequestCount) }}</div><div class="perf-note">Count in current Laravel log; rotation may reset it.</div></article>
</section>
@if($debugEnabled || !$slowRequestLogging)
<section class="perf-card" style="margin-top:16px"><h2 style="font-size:16px;font-weight:750;margin:0 0 8px">Configuration attention</h2>
@if($debugEnabled)<p class="perf-muted">APP_DEBUG is enabled. Ensure it is disabled in production.</p>@endif
@if(!$slowRequestLogging)<p class="perf-muted">Slow-request logging is disabled, so slow-request markers will not accumulate.</p>@endif
</section>
@endif
<p class="perf-muted" style="margin-top:20px;font-size:12px">This page does not change ERP records or run a load test. These point-in-time diagnostics are not an uptime or capacity guarantee.</p>
</main>
</body>
</html>
