@extends('layouts.app')
@section('base-content')
<link rel="stylesheet" href="{{ asset('tagore-erp.css') . '?v=20261012-ui5' }}">
<div class="tg-app"><div class="tg-shell">
  <header class="tg-topbar"><div class="tg-brand"><div class="tg-logo">T</div><div><strong>TagoreK12</strong><span>Campus safety</span></div></div><a class="tg-btn" href="{{ route('tagore.platform.index') }}">Back to platform</a></header>
  <section class="tg-hero"><div><span class="tg-eyebrow">CCTV & AI analytics</span><h1>CCTV connector status</h1><p>Read-only check of the private Frigate NVR connection. This page does not display or proxy live video.</p></div></section>
  <section class="tg-card" aria-live="polite">
    @if(!empty($status['reachable']))
      <span class="tg-status success">Connected</span>
    @elseif(empty($status['configured']))
      <span class="tg-status warning">Not configured</span>
    @else
      <span class="tg-status warning">Unreachable</span>
    @endif
    <h2>{{ $status['message'] ?? 'No status message returned.' }}</h2>
    @if(!empty($status['reachable']))
      <p>Registered cameras reported: <strong>{{ (int) ($status['camera_count'] ?? 0) }}</strong></p>
      <p>Detectors reported: <strong>{{ (int) ($status['detector_count'] ?? 0) }}</strong></p>
      @if(!empty($status['camera_names']))<ul>@foreach($status['camera_names'] as $camera)<li>{{ $camera }}</li>@endforeach</ul>@endif
    @endif
    <p class="muted">A reachable NVR does not prove every camera is recording or that AI detections are accurate. Verify streams, retention, access controls and privacy policy before rollout.</p>
    <a class="tg-btn primary" href="{{ route('tagore.platform.cctv.status', [], false) }}">Check again</a>
  </section>
</div></div>
@endsection
