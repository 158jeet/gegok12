<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Olympiad tests Â· {{ $child->name }} | Tagore ERP</title><link rel="stylesheet" href="{{ asset('tagore-erp.css') }}"></head>
<body class="tg-app"><div class="tg-shell">
<header class="tg-topbar"><a class="tg-brand" href="{{ route('tagore.parent.dashboard') }}" style="text-decoration:none"><img class="tg-logo" src="/uploads/tgi-logo.webp" alt="Tagore Group of Institutions"><div><strong>Tagore ERP</strong><span>Parent workspace</span></div></a><div class="tg-top-actions"><span class="tg-status">Parent-supervised exam</span></div></header>
<main class="tg-main">
<section class="tg-hero"><div class="tg-eyebrow">OLYMPIAD CENTRE</div><h1>Tests for {{ $child->name }}</h1><p>Register your child, launch the timed test when it opens, and review the submitted result here.</p><div class="tg-hero-actions"><a class="tg-btn" href="{{ route('tagore.parent.dashboard') }}">Back to family home</a></div></section>
@if(session('status'))<div class="tg-card" role="status" style="margin:14px 0;border-left:4px solid var(--tg-success)">{{ session('status') }}</div>@endif
@if($errors->any())<div class="tg-card" role="alert" style="margin:14px 0;border-left:4px solid var(--tg-danger)"><strong>Could not continue</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if($events->isEmpty())<div class="tg-card tg-empty"><strong>No eligible Olympiad tests yet</strong><p>{{ $grade ? 'There are no published tests for your childâ€™s class at the moment.' : 'The school needs to finish linking your child to an active class before eligible tests can appear.' }}</p></div>
@else
<div class="tg-section-head"><div><h2>Available tests</h2><p>Only published tests matching your childâ€™s class are shown.</p></div></div><div class="tg-grid">
@foreach($events as $event)<article class="tg-card"><div class="tg-card-head"><div><h2>{{ $event->title }}</h2><p class="tg-kpi-meta">{{ $event->code }} Â· {{ $event->duration_minutes }} minutes</p></div>
@if($event->is_submitted)<span class="tg-status">Submitted</span>@elseif($event->registration)<span class="tg-status">{{ str_replace('_',' ',ucfirst($event->registration->status)) }}</span>@else<span class="tg-status">Registration</span>@endif</div>
@if($event->description)<p>{{ $event->description }}</p>@endif
<p class="tg-kpi-meta">Starts: {{ $event->exam_starts_at ? \Illuminate\Support\Carbon::parse($event->exam_starts_at)->timezone('Asia/Kolkata')->format('d M Y, h:i A') : 'Schedule pending' }}</p>
<div class="tg-actions" style="margin-top:14px">
@if($event->is_submitted)<a class="tg-btn primary" href="{{ route('tagore.parent.olympiad.result', ['studentId'=>$studentId,'olympiadId'=>$event->id]) }}">View result</a>
@elseif($event->can_launch)<form method="post" action="{{ route('tagore.parent.olympiad.launch', ['studentId'=>$studentId,'olympiadId'=>$event->id]) }}">@csrf<button class="tg-btn primary" type="submit">{{ $event->registration->status === 'in_progress' ? 'Continue test' : 'Start test' }}</button></form>
@elseif($event->can_register)<form method="post" action="{{ route('tagore.parent.olympiad.register', ['studentId'=>$studentId,'olympiadId'=>$event->id]) }}">@csrf<button class="tg-btn primary" type="submit">Register child</button></form>
@elseif($event->registration)<span class="tg-kpi-meta">The exam is not open right now.</span>
@else<span class="tg-kpi-meta">Registration is not open.</span>@endif
</div></article>@endforeach
</div>@endif
</main><nav class="tg-mobile-nav"><a href="{{ route('tagore.parent.dashboard') }}"><b>âŒ‚</b>Home</a><a href="{{ route('tagore.dashboard') }}"><b>â‹®</b>ERP</a></nav></div></body></html>
