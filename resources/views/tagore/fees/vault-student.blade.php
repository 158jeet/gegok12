<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Fee Vault · {{ $student->name }}</title><link rel="stylesheet" href="{{ asset('tagore-erp.css') }}"></head>
<body><main class="tg-shell">
<header class="tg-topbar"><div class="tg-brand"><div class="tg-logo">T</div><div><strong>TagoreK12</strong><span>Original financial archive</span></div></div><div class="tg-user">Owner-only</div></header>
<nav class="tg-nav"><a href="{{ route('tagore.fees.vault') }}">← Vault search</a><a href="{{ route('tagore.dashboard') }}">ERP Home</a></nav>
<section class="tg-hero"><h1>{{ $student->name }}</h1><p>Student ID {{ $student->id }} · {{ $student->email ?: 'No email recorded' }}</p></section>
@forelse($archives as $archive)<section class="card" style="padding:22px;margin-bottom:18px"><div class="tg-section-head"><h2>Archive {{ $archive['closure']['closed_at'] }}</h2><span class="tg-kpi-label">Immutable source copy</span></div><div class="tg-alert">SHA-256: {{ $archive['closure']['archive_sha256'] }}</div>
<h3>Original fee records</h3><pre>{{ json_encode($archive['records'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
<h3>Original payments</h3><pre>{{ json_encode($archive['payments'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
<h3>Original transactions</h3><pre>{{ json_encode($archive['transactions'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
</section>@empty<section class="card" style="padding:22px"><div class="tg-kpi-label">No archived records found.</div></section>@endforelse
</main></body></html>