<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Olympiad result Â· {{ $child->name }} | Tagore ERP</title><link rel="stylesheet" href="{{ asset('tagore-erp.css') }}"></head>
<body class="tg-app"><div class="tg-shell">
<header class="tg-topbar"><a class="tg-brand" href="{{ route('tagore.parent.dashboard') }}" style="text-decoration:none"><img class="tg-logo" src="/uploads/tgi-logo.webp" alt="Tagore Group of Institutions"><div><strong>Tagore ERP</strong><span>Parent workspace</span></div></a></header>
<main class="tg-main"><section class="tg-hero"><div class="tg-eyebrow">OLYMPIAD RESULT</div><h1>{{ $event->title }}</h1><p>Submitted test for {{ $child->name }}.</p></section>
<section class="tg-card" style="margin-top:16px"><div class="tg-grid">
<div><div class="tg-kpi-label">Score</div><div class="tg-kpi-value">{{ number_format((float)$registration->score, 2) }} / {{ number_format((float)$registration->total_marks, 2) }}</div></div>
<div><div class="tg-kpi-label">Status</div><div class="tg-kpi-value">{{ $registration->status === 'submitted_late' ? 'Submitted late' : 'Submitted' }}</div></div>
<div><div class="tg-kpi-label">Registration</div><div class="tg-kpi-value">{{ $registration->registration_no }}</div></div>
</div><div class="tg-actions" style="margin-top:18px"><a class="tg-btn primary" href="{{ route('tagore.parent.olympiad.index', ['studentId'=>$studentId]) }}">Back to tests</a><a class="tg-btn" href="{{ route('tagore.parent.dashboard') }}">Family home</a></div></section></main>
<nav class="tg-mobile-nav"><a href="{{ route('tagore.parent.dashboard') }}"><b>âŒ‚</b>Home</a><a href="{{ route('tagore.dashboard') }}"><b>â‹®</b>ERP</a></nav></div></body></html>
