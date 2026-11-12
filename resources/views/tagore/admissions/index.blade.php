<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admissions CRM · Tagore ERP</title><link rel="stylesheet" href="{{ asset('tagore-admissions.css') . '?v=20261112-1' }}"></head>
<body><div class="tg-admissions"><div class="tg-ad-shell">
<header class="tg-ad-top"><a class="tg-ad-brand" href="{{ route('tagore.dashboard') }}"><img src="{{ asset('uploads/tgi-logo.webp') }}" alt="Tagore Group"><div><strong>Tagore ERP</strong><span>Admissions workspace</span></div></a><div class="tg-ad-actions"><a class="tg-ad-btn" href="{{ route('tagore.dashboard') }}">Dashboard</a><a class="tg-ad-btn primary" href="{{ route('tagore.admissions.create') }}">＋ New enquiry</a></div></header>
<section class="tg-ad-hero"><div><h1>Admissions CRM</h1><p>One pipeline for every prospective family — from first enquiry to admission, with clear ownership and the next action always visible.</p></div></section>
<div class="tg-ad-grid">
@foreach([['All enquiries','total'],['New','new'],['Follow-up','follow_up'],['Due today','due_today'],['Overdue','overdue'],['Qualified','qualified'],['Admitted','admitted'],['Lost','lost']] as [$label,$key])
<div class="tg-ad-card"><div class="tg-ad-kpi">{{ $label }}</div><div class="tg-ad-value">{{ number_format($stats[$key]) }}</div></div>
@endforeach
</div>
<form method="get" class="tg-ad-filters" aria-label="Filter admission leads">
<input name="q" value="{{ request('q') }}" placeholder="Search student, parent or mobile" aria-label="Search leads">
<select name="status" aria-label="Lead status"><option value="">All statuses</option>@foreach(['new','contacted','follow_up','qualified','admitted','lost'] as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>@endforeach</select>
<select name="assigned_to" aria-label="Lead owner"><option value="">All owners</option>@foreach($staff as $person)<option value="{{ $person->id }}" @selected((string)request('assigned_to')===(string)$person->id)>{{ $person->name }}</option>@endforeach</select>
<select name="follow_up" aria-label="Follow-up status"><option value="">Any follow-up</option><option value="due" @selected(request('follow_up')==='due')>Due today</option><option value="overdue" @selected(request('follow_up')==='overdue')>Overdue</option></select>
<select name="source" aria-label="Lead source"><option value="">All sources</option>@foreach($sources as $item)<option value="{{ $item->source }}" @selected(request('source')===$item->source)>{{ $item->source }} ({{ $item->total }})</option>@endforeach</select>
<select name="campaign" aria-label="Campaign"><option value="">All campaigns</option>@foreach($campaigns as $item)<option value="{{ $item->campaign }}" @selected(request('campaign')===$item->campaign)>{{ $item->campaign }} ({{ $item->total }})</option>@endforeach</select>
<button type="submit">Apply filters</button><a class="tg-ad-btn" href="{{ route('tagore.admissions.index') }}">Reset</a>
</form>
<section class="tg-ad-card tg-ad-section"><div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px"><div><h2 style="margin:0">Enquiry pipeline</h2><span class="tg-ad-muted">Showing {{ $leads->firstItem() ?? 0 }}–{{ $leads->lastItem() ?? 0 }} of {{ $leads->total() }} enquiries</span></div></div>
<div class="tg-ad-tablewrap"><table class="tg-ad-table"><thead><tr><th>Lead ID</th><th>Student / parent</th><th>Contact</th><th>Class</th><th>Source / campaign</th><th>Owner</th><th>Stage</th><th>Next follow-up</th></tr></thead><tbody>
@forelse($leads as $lead)<tr>
<td><a href="{{ route('tagore.admissions.show',$lead) }}"><strong>{{ $lead->lead_no }}</strong></a></td>
<td><strong>{{ $lead->student_name }}</strong><div class="tg-ad-muted">{{ $lead->parent_name ?: 'Parent not added' }}</div></td>
<td>{{ $lead->mobile ?: '—' }}<div class="tg-ad-muted">{{ $lead->email ?: '' }}</div></td>
<td>{{ $lead->class_name ?: '—' }}</td><td>{{ $lead->source ?: '—' }}<div class="tg-ad-muted">{{ $lead->campaign ?: '' }}</div></td>
<td>{{ optional($staff->firstWhere('id',$lead->assigned_to))->name ?: 'Unassigned' }}</td>
<td><span class="tg-ad-pill {{ $lead->status }}">{{ ucfirst(str_replace('_',' ',$lead->status)) }}</span></td>
<td>{{ optional($lead->next_follow_up_at)->format('d M Y, h:i A') ?: 'Not scheduled' }}</td>
</tr>@empty<tr><td colspan="8" class="tg-ad-muted">No enquiries match these filters. Try clearing a filter or create a new enquiry.</td></tr>@endforelse
</tbody></table></div><div class="tg-ad-pagination">{{ $leads->links() }}</div></section>
<div class="tg-ad-two tg-ad-section">
<section class="tg-ad-card"><h2>Conversion performance</h2><div class="tg-ad-value">{{ $stats['conversion_rate'] }}%</div><div class="tg-ad-muted">{{ number_format($stats['admitted']) }} admitted from {{ number_format($stats['total']) }} enquiries</div></section>
<section class="tg-ad-card"><h2>Lead sources & campaigns</h2><div class="tg-ad-two"><div><strong>Top sources</strong>@forelse($sources as $item)<div style="display:flex;justify-content:space-between;padding:5px 0"><span>{{ $item->source }}</span><strong>{{ $item->total }}</strong></div>@empty<p class="tg-ad-muted">No source data yet.</p>@endforelse</div><div><strong>Campaigns</strong>@forelse($campaigns as $item)<div style="display:flex;justify-content:space-between;padding:5px 0"><span>{{ $item->campaign }}</span><strong>{{ $item->total }}</strong></div>@empty<p class="tg-ad-muted">No campaign data yet.</p>@endforelse</div></div></section>
</div>
<section class="tg-ad-card tg-ad-section"><h2>Owner performance</h2><div class="tg-ad-tablewrap"><table class="tg-ad-table" style="min-width:560px"><thead><tr><th>Lead owner</th><th>Assigned</th><th>Admitted</th><th>Lost</th><th>Conversion</th></tr></thead><tbody>@forelse($ownerPerformance as $row)<tr><td>{{ $ownerNames[$row->assigned_to] ?? 'User #'.$row->assigned_to }}</td><td>{{ $row->total }}</td><td>{{ $row->admitted }}</td><td>{{ $row->lost }}</td><td>{{ $row->total ? round(($row->admitted/$row->total)*100,1) : 0 }}%</td></tr>@empty<tr><td colspan="5" class="tg-ad-muted">Owner performance will appear as leads are assigned.</td></tr>@endforelse</tbody></table></div></section>
</div></div></body></html>