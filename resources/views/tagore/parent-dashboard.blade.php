<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Parent Home · Tagore ERP</title><link rel="stylesheet" href="{{ asset('tagore-erp.css') }}"></head>
<body class="tg-app"><div class="tg-shell">
<header class="tg-topbar"><a class="tg-brand" href="{{ route('tagore.parent.dashboard') }}" style="text-decoration:none"><div class="tg-logo">T</div><div><strong>Tagore ERP</strong><span>Parent workspace</span></div></a><div class="tg-top-actions"><div class="tg-user"><div class="tg-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'P',0,1)) }}</div><span>{{ auth()->user()->name ?? 'Parent' }}</span></div></div></header>
<main class="tg-main">
<section class="tg-hero"><h1>Your family's school at a glance.</h1><p>Attendance, results, fees and school communication are organised around your children — not around ERP modules.</p><div class="tg-hero-actions"><a class="tg-btn" href="{{ route('tagore.dashboard') }}">ERP home</a></div></section>
@if($cards->isEmpty())
<div class="tg-card tg-empty"><strong>No children linked yet</strong><span>Ask the school office to link this parent account with the student's school account.</span></div>
@else
<div class="tg-section-head"><div><h2>Your children</h2><p>Select a child to see the complete school picture.</p></div></div>
<div class="tg-grid">
@foreach($cards as $card)
<div class="tg-card">
<div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start"><div><h2 style="margin:0 0 4px;font-size:19px">{{ $card->name }}</h2><div class="tg-kpi-meta">{{ $card->institution ?: 'Tagore Institution' }}</div></div><span class="tg-status">{{ $card->relationship ?: 'Guardian' }}</span></div>
<div class="tg-three" style="margin-top:17px">
<div><div class="tg-kpi-label">Attendance</div><strong style="font-size:20px;display:block;margin-top:5px">{{ $card->attendance === null ? '—' : $card->attendance.'%' }}</strong><div class="tg-kpi-meta">{{ $card->attendance_total ? $card->present.' / '.$card->attendance_total.' sessions' : 'No records yet' }}</div></div>
<div><div class="tg-kpi-label">Fee due</div><strong style="font-size:20px;display:block;margin-top:5px">₹{{ number_format($card->outstanding,0) }}</strong><div class="tg-kpi-meta">{{ $card->outstanding > 0 ? 'Payment required' : 'No outstanding balance' }}</div></div>
<div><div class="tg-kpi-label">Latest result</div><strong style="font-size:20px;display:block;margin-top:5px">{{ $card->latest_result?->percentage === null ? '—' : $card->latest_result->percentage.'%' }}</strong><div class="tg-kpi-meta">{{ $card->latest_result?->exam_name ?: 'No published result' }}</div></div>
</div>
<div class="tg-actions" style="margin-top:17px"><a class="tg-btn primary" href="{{ route('tagore.child',$card->student_id) }}">Open child profile</a><a class="tg-btn" href="{{ route('tagore.fees.student',$card->student_id) }}">Fees</a></div>
</div>
@endforeach
</div>
@endif
</main>
<nav class="tg-mobile-nav"><a href="{{ route('tagore.parent.dashboard') }}"><b>⌂</b>Home</a><a href="{{ route('tagore.dashboard') }}"><b>⋮</b>ERP</a></nav>
</div></body></html>