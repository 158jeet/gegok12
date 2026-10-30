@extends('layouts.admin.layout')

@section('content')
<div class="tg-main">
  <section class="tg-hero">
    <div>
      <p class="tg-eyebrow">COMMUNICATIONS</p>
      <h1>Parent feedback</h1>
      <p>Review concerns, track response status and follow up with families from one workspace.</p>
    </div>
    <form method="GET" action="{{ url('/admin/feedbacks') }}" class="tg-form" style="grid-template-columns:minmax(220px,1fr) auto;align-items:end;max-width:560px">
      <div class="tg-field">
        <label for="feedback-search">Search feedback</label>
        <input id="feedback-search" type="search" name="search" value="{{ request('search') }}" placeholder="Parent, category or message">
      </div>
      <div class="tg-actions">
        <button type="submit" class="tg-btn primary">Search</button>
        <a href="{{ url('/admin/feedbacks') }}" class="tg-btn">Reset</a>
      </div>
    </form>
  </section>
  @include('partials.message')
  <section class="tg-section">
    <div class="tg-section-head">
      <div><h2>Feedback inbox</h2><p>Latest parent conversations and their current response status.</p></div>
      <span class="tg-status">{{ $feedbacks->total() }} conversations</span>
    </div>
    <div class="tg-card">
      @include('admin.feedbacks.list')
    </div>
  </section>
</div>
@endsection
