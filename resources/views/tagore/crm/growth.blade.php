<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>CRM Growth Workspace · Tagore ERP</title>
<link rel="stylesheet" href="{{ asset('tagore-erp.css') }}">
<style>
.crm-wrap{max-width:1500px;margin:auto;padding:24px 18px 48px;color:var(--tg-ink,#172536)}
.crm-head{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:20px}
.crm-head h1{margin:0;font-size:26px}.crm-head p{margin:5px 0;color:var(--tg-muted,#64748b)}
.crm-actions{display:flex;gap:8px;flex-wrap:wrap}.crm-link,.crm-btn{display:inline-flex;align-items:center;justify-content:center;padding:9px 13px;border-radius:9px;border:1px solid #d6e0e9;background:#fff;color:#18334c;text-decoration:none;font-weight:700;font-size:13px;cursor:pointer}
.crm-btn.primary{background:#173d5b;color:#fff;border-color:#173d5b}
.crm-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:18px 0}
.crm-kpi,.crm-card{background:#fff;border:1px solid #e1e8ef;border-radius:14px;padding:16px;min-width:0;box-shadow:0 4px 14px rgba(17,40,60,.04)}
.crm-kpi span{display:block;color:#64748b;font-size:12px;font-weight:700}.crm-kpi strong{display:block;font-size:24px;margin-top:6px;font-variant-numeric:tabular-nums}
.crm-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:14px 0}
.crm-card h2{font-size:17px;margin:0 0 5px}.crm-sub{font-size:12px;color:#64748b;margin:0 0 14px;line-height:1.5}
.crm-pipeline{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}
.crm-stage{background:#f6f8fb;border:1px solid #e7edf3;border-radius:10px;padding:11px;min-width:0}
.crm-stage strong{display:block;font-size:13px;text-transform:capitalize}.crm-stage span{display:block;font-size:12px;color:#64748b;margin-top:4px}
.crm-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}
.crm-form label{display:block;font-size:11px;font-weight:750;color:#536477;margin-bottom:4px}
.crm-form input,.crm-form select,.crm-form textarea{width:100%;min-width:0;box-sizing:border-box;border:1px solid #d5dee8;border-radius:8px;padding:9px;background:#fff;color:#172536;font:inherit;font-size:13px}
.crm-form textarea{min-height:70px;resize:vertical}.crm-full{grid-column:1/-1}
.crm-table-wrap{overflow-x:auto;max-width:100%;border:1px solid #e7edf3;border-radius:10px}
.crm-table{width:100%;min-width:850px;border-collapse:collapse;font-size:13px}.crm-table th,.crm-table td{padding:10px 11px;border-bottom:1px solid #edf1f5;text-align:left;vertical-align:top}.crm-table th{background:#f7f9fb;color:#58697b;font-size:11px;text-transform:uppercase;letter-spacing:.04em}
.crm-pill{display:inline-flex;padding:4px 8px;border-radius:999px;background:#edf3f8;color:#31536d;font-size:10px;font-weight:800;white-space:nowrap}
.crm-pill.won,.crm-pill.active{background:#e5f5ed;color:#176344}.crm-pill.lost,.crm-pill.paused{background:#fff0f0;color:#a43e4a}.crm-pill.negotiation,.crm-pill.proposal{background:#fff4df;color:#94621a}
.crm-row-actions{min-width:240px}.crm-row-actions details{margin-top:6px}.crm-row-actions summary{cursor:pointer;color:#1e5c82;font-weight:750}
.crm-empty{padding:20px;text-align:center;color:#738294;font-size:13px}
@media(max-width:900px){.crm-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.crm-grid{grid-template-columns:1fr}}
@media(max-width:560px){.crm-wrap{padding:16px 10px 32px}.crm-head h1{font-size:22px}.crm-kpis{gap:8px}.crm-kpi{padding:12px}.crm-kpi strong{font-size:20px}.crm-pipeline{grid-template-columns:repeat(2,minmax(0,1fr))}.crm-form{grid-template-columns:1fr}.crm-full{grid-column:auto}}
</style>
</head>
<body class="tg-app">
<div class="crm-wrap">
<header class="crm-head">
  <div><h1>CRM Growth Workspace</h1><p>Manage opportunities, admissions revenue forecasts, campaigns and attribution across your institutions.</p></div>
  <nav class="crm-actions">
    <a class="crm-link" href="{{ route('tagore.dashboard') }}">Dashboard</a>
    <a class="crm-link" href="{{ route('tagore.admissions.index') }}">Admissions leads</a>
    <a class="crm-link" href="{{ route('tagore.crm.telephony.index') }}">Cloud calling</a>
  </nav>
</header>
@if(session('success'))<div class="tg-alert success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="tg-alert danger" role="alert">{{ $errors->first() }}</div>@endif

<section class="crm-kpis">
  <div class="crm-kpi"><span>Open opportunities</span><strong>{{ number_format($metrics['open_opportunities']) }}</strong></div>
  <div class="crm-kpi"><span>Weighted pipeline</span><strong>₹{{ number_format($metrics['weighted_pipeline'],0) }}</strong></div>
  <div class="crm-kpi"><span>Won value</span><strong>₹{{ number_format($metrics['won_value'],0) }}</strong></div>
  <div class="crm-kpi"><span>Active campaigns</span><strong>{{ number_format($metrics['active_campaigns']) }}</strong></div>
  <div class="crm-kpi"><span>Open tasks</span><strong>{{ number_format($metrics['open_tasks']) }}</strong></div>
  <div class="crm-kpi"><span>Overdue tasks</span><strong>{{ number_format($metrics['overdue_tasks']) }}</strong></div>
  <div class="crm-kpi"><span>Open support cases</span><strong>{{ number_format($metrics['open_cases']) }}</strong></div>
  <div class="crm-kpi"><span>Overdue cases</span><strong>{{ number_format($metrics['overdue_cases']) }}</strong></div>
</section>

<section class="crm-card">
  <h2>Opportunity pipeline</h2><p class="crm-sub">Weighted value uses the probability set by the opportunity owner. Won and lost records are retained for reporting.</p>
  <div class="crm-pipeline">
    @foreach($pipeline as $stage)
      <div class="crm-stage"><strong>{{ str_replace('_',' ',$stage['stage']) }}</strong><span>{{ $stage['total'] }} opportunities</span><span>₹{{ number_format($stage['amount'],0) }} total</span><span>₹{{ number_format($stage['weighted_amount'],0) }} weighted</span></div>
    @endforeach
  </div>
</section>

<div class="crm-grid">
  @if($canManageOpportunities)
  <section class="crm-card">
    <h2>Create opportunity</h2><p class="crm-sub">Create a revenue opportunity for admissions, hostel, coaching, college or partnerships. Link an enquiry to preserve its history.</p>
    <form class="crm-form" method="POST" action="{{ route('tagore.crm.opportunities.store') }}">@csrf
      <div><label>Institution</label><select name="institution_id" required>@foreach($institutions as $institution)<option value="{{ $institution->id }}">{{ $institution->display_name }}</option>@endforeach</select></div>
      <div><label>Linked lead ID (optional)</label><input type="number" name="lead_id" min="1" placeholder="Admission enquiry ID"></div>
      <div class="crm-full"><label>Opportunity name</label><input name="name" required maxlength="180" placeholder="e.g. Class 11 NEET 2027 admission"></div>
      <div><label>Type</label><select name="opportunity_type" required><option value="admission">School admission</option><option value="hostel">Hostel</option><option value="coaching">Vedant coaching</option><option value="college">PG college</option><option value="partnership">Partnership</option><option value="corporate">Corporate / institutional</option></select></div>
      <div><label>Stage</label><select name="stage" required>@foreach(['qualification','needs_analysis','proposal','negotiation','won','lost'] as $stage)<option value="{{ $stage }}">{{ ucfirst(str_replace('_',' ',$stage)) }}</option>@endforeach</select></div>
      <div><label>Expected value (₹)</label><input name="amount" type="number" min="0" step="0.01" value="0" required></div>
      <div><label>Win probability (%)</label><input name="probability" type="number" min="0" max="100" value="25" required></div>
      <div><label>Expected close date</label><input name="expected_close_date" type="date"></div>
      <div><label>Owner</label><select name="assigned_to"><option value="">Unassigned</option>@foreach($staff as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></div>
      <div><label>Lead source</label><input name="source" maxlength="60" placeholder="Referral, walk-in, web"></div>
      <div><label>Campaign code</label><input name="campaign" maxlength="100" placeholder="Exact campaign code"></div>
      <div class="crm-full"><label>Notes</label><textarea name="notes" maxlength="5000" placeholder="Need, decision makers, next action and risks"></textarea></div>
      <div class="crm-full"><button class="crm-btn primary" type="submit">Add to pipeline</button></div>
    </form>
  </section>

  @endif
  @if($canManageCampaigns)
  <section class="crm-card">
    <h2>Create marketing campaign</h2><p class="crm-sub">Track budget, actual spend, leads and won revenue. Use the exact campaign code on each enquiry for attribution.</p>
    <form class="crm-form" method="POST" action="{{ route('tagore.crm.campaigns.store') }}">@csrf
      <div><label>Institution</label><select name="institution_id" required>@foreach($institutions as $institution)<option value="{{ $institution->id }}">{{ $institution->display_name }}</option>@endforeach</select></div>
      <div><label>Campaign name</label><input name="name" required maxlength="150" placeholder="Winter admission drive"></div>
      <div><label>Campaign code</label><input name="campaign_code" required maxlength="100" pattern="[A-Za-z0-9_-]+" placeholder="WINTER-ADMISSIONS-27"></div>
      <div><label>Channel</label><select name="channel" required>@foreach(['digital','social','search','referral','event','print','outbound_call','partner','other'] as $channel)<option value="{{ $channel }}">{{ ucfirst(str_replace('_',' ',$channel)) }}</option>@endforeach</select></div>
      <div><label>Status</label><select name="status"><option value="draft">Draft</option><option value="active">Active</option><option value="paused">Paused</option><option value="completed">Completed</option></select></div>
      <div><label>Budget (₹)</label><input name="budget" type="number" min="0" step="0.01" value="0" required></div>
      <div><label>Actual spend (₹)</label><input name="spend" type="number" min="0" step="0.01" value="0" required></div>
      <div><label>Campaign owner</label><select name="owner_user_id"><option value="">Unassigned</option>@foreach($staff as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></div>
      <div><label>Start date</label><input name="starts_on" type="date"></div>
      <div><label>End date</label><input name="ends_on" type="date"></div>
      <div><label>UTM source</label><input name="utm_source" maxlength="100" placeholder="google / facebook"></div>
      <div><label>UTM medium</label><input name="utm_medium" maxlength="100" placeholder="cpc / social / referral"></div>
      <div class="crm-full"><label>Landing page URL</label><input name="landing_url" type="url" maxlength="500" placeholder="https://..."></div>
      <div class="crm-full"><label>Objective</label><input name="objective" maxlength="120" placeholder="Increase Class 11 JEE / NEET enquiries"></div>
      <div class="crm-full"><button class="crm-btn primary" type="submit">Save campaign</button></div>
    </form>
  </section>
  @endif
</div>

<div class="crm-grid">
  @if($canManageTasks)
  <section class="crm-card">
    <h2>Create task / follow-up</h2><p class="crm-sub">Assign calls, emails, meetings, visits and document follow-ups with a named owner and due date.</p>
    <form class="crm-form" method="POST" action="{{ route('tagore.crm.tasks.store') }}">@csrf
      <div><label>Institution</label><select name="institution_id" required>@foreach($institutions as $institution)<option value="{{ $institution->id }}">{{ $institution->display_name }}</option>@endforeach</select></div>
      <div><label>Task type</label><select name="task_type">@foreach(['call','email','meeting','visit','follow_up','document','other'] as $type)<option value="{{ $type }}">{{ ucfirst(str_replace('_',' ',$type)) }}</option>@endforeach</select></div>
      <div class="crm-full"><label>Task title</label><input name="title" required maxlength="180" placeholder="Call parent about Class 11 admission"></div>
      <div><label>Priority</label><select name="priority"><option value="normal">Normal</option><option value="low">Low</option><option value="high">High</option><option value="urgent">Urgent</option></select></div>
      <div><label>Due date / time</label><input type="datetime-local" name="due_at"></div>
      <div><label>Linked lead</label><select name="lead_id"><option value="">No lead linked</option>@foreach($leads as $lead)<option value="{{ $lead->id }}">{{ $lead->lead_no }} · {{ $lead->student_name }}</option>@endforeach</select></div>
      <div><label>Linked opportunity</label><select name="opportunity_id"><option value="">No opportunity linked</option>@foreach($opportunities as $opportunity)<option value="{{ $opportunity->id }}">{{ $opportunity->name }}</option>@endforeach</select></div>
      <div><label>Assigned to</label><select name="assigned_to"><option value="">Unassigned</option>@foreach($staff as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></div>
      <div class="crm-full"><label>Instructions</label><textarea name="description" maxlength="5000"></textarea></div>
      <div class="crm-full"><button class="crm-btn primary" type="submit">Create task</button></div>
    </form>
  </section>
  @endif
  @if($canManageCases)
  <section class="crm-card">
    <h2>Open customer support case</h2><p class="crm-sub">Track parent, student and prospective-family issues with an owner, priority and service deadline.</p>
    <form class="crm-form" method="POST" action="{{ route('tagore.crm.cases.store') }}">@csrf
      <div><label>Institution</label><select name="institution_id" required>@foreach($institutions as $institution)<option value="{{ $institution->id }}">{{ $institution->display_name }}</option>@endforeach</select></div>
      <div><label>Category</label><select name="category">@foreach(['general','fee','transport','hostel','academic','admissions','examination','technology','safety','other'] as $category)<option value="{{ $category }}">{{ ucfirst(str_replace('_',' ',$category)) }}</option>@endforeach</select></div>
      <div class="crm-full"><label>Case subject</label><input name="subject" required maxlength="180" placeholder="Parent requests fee correction / transport support"></div>
      <div><label>Priority</label><select name="priority"><option value="normal">Normal</option><option value="low">Low</option><option value="high">High</option><option value="urgent">Urgent</option></select></div>
      <div><label>Service deadline</label><input type="datetime-local" name="due_at"></div>
      <div><label>Linked lead</label><select name="lead_id"><option value="">No lead linked</option>@foreach($leads as $lead)<option value="{{ $lead->id }}">{{ $lead->lead_no }} · {{ $lead->student_name }}</option>@endforeach</select></div>
      <div><label>Student</label><select name="student_id"><option value="">No student linked</option>@foreach($students as $student)<option value="{{ $student->id }}">{{ $student->name }}</option>@endforeach</select></div>
      <div><label>Parent / guardian</label><select name="parent_user_id"><option value="">No parent linked</option>@foreach($parents as $parent)<option value="{{ $parent->id }}">{{ $parent->name }}</option>@endforeach</select></div>
      <div><label>Assigned to</label><select name="assigned_to"><option value="">Unassigned</option>@foreach($staff as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></div>
      <div class="crm-full"><label>Issue details</label><textarea name="description" maxlength="10000"></textarea></div>
      <div class="crm-full"><button class="crm-btn primary" type="submit">Open support case</button></div>
    </form>
  </section>
  @endif
</div>
<section class="crm-card" style="margin-top:14px">
  <h2>High-intent leads</h2><p class="crm-sub">Priority score is a decision-support signal based on stage, contactability, campaign attribution, recent engagement and follow-up freshness—not an automated admission decision.</p>
  <div class="crm-table-wrap"><table class="crm-table"><thead><tr><th>Lead</th><th>Student / parent</th><th>Stage</th><th>Score</th><th>Source / campaign</th><th>Next follow-up</th><th>Owner</th></tr></thead><tbody>
  @forelse($topLeads as $lead)
    <tr><td><a href="{{ route('tagore.admissions.show',$lead->id) }}"><strong>{{ $lead->lead_no }}</strong></a></td><td>{{ $lead->student_name }}<div class="crm-sub">{{ $lead->parent_name }}</div></td><td><span class="crm-pill">{{ ucfirst(str_replace('_',' ',$lead->status)) }}</span></td><td><span class="crm-pill {{ $lead->crm_score >= 70 ? 'won' : ($lead->crm_score >= 40 ? 'proposal' : '') }}">{{ $lead->crm_score }}/100</span></td><td>{{ $lead->source ?: '—' }}<div class="crm-sub">{{ $lead->campaign ?: 'No campaign' }}</div></td><td>{{ $lead->next_follow_up_at ? \Illuminate\Support\Carbon::parse($lead->next_follow_up_at)->format('d M, H:i') : 'Not scheduled' }}</td><td>{{ $owners[$lead->assigned_to] ?? 'Unassigned' }}</td></tr>
  @empty
    <tr><td colspan="7" class="crm-empty">No admission leads in this institution scope.</td></tr>
  @endforelse
  </tbody></table></div>
</section>
<section class="crm-card">
  <h2>Opportunities</h2><p class="crm-sub">Latest 100 opportunities across your authorised institutions.</p>
  <div class="crm-table-wrap"><table class="crm-table"><thead><tr><th>Opportunity</th><th>Institution</th><th>Lead</th><th>Stage</th><th>Value</th><th>Probability</th><th>Expected close</th><th>Owner</th><th>Update</th></tr></thead><tbody>
  @forelse($opportunities as $opportunity)
    <tr>
      <td><strong>{{ $opportunity->name }}</strong><div class="crm-sub">{{ ucfirst($opportunity->opportunity_type) }} · {{ $opportunity->source ?: 'Direct' }}{{ $opportunity->campaign ? ' · '.$opportunity->campaign : '' }}</div></td>
      <td>{{ optional($institutions->firstWhere('id',$opportunity->institution_id))->display_name ?: '—' }}</td>
      <td>@if($opportunity->lead)<a href="{{ route('tagore.admissions.show',$opportunity->lead_id) }}">{{ $opportunity->lead->lead_no }}</a><div class="crm-sub">{{ $opportunity->lead->student_name }}</div>@else — @endif</td>
      <td><span class="crm-pill {{ $opportunity->stage }}">{{ ucfirst(str_replace('_',' ',$opportunity->stage)) }}</span></td>
      <td>₹{{ number_format((float)$opportunity->amount,2) }}</td><td>{{ $opportunity->probability }}%</td>
      <td>{{ optional($opportunity->expected_close_date)->format('d M Y') ?: '—' }}</td>
      <td>{{ $owners[$opportunity->assigned_to] ?? 'Unassigned' }}</td>
      <td class="crm-row-actions">
        @if($canManageOpportunities)
        <details><summary>Update opportunity</summary>
          <form class="crm-form" method="POST" action="{{ route('tagore.crm.opportunities.update',$opportunity->id) }}">@csrf @method('PATCH')
            <div><label>Stage</label><select name="stage">@foreach(['qualification','needs_analysis','proposal','negotiation','won','lost'] as $stage)<option value="{{ $stage }}" @selected($opportunity->stage===$stage)>{{ ucfirst(str_replace('_',' ',$stage)) }}</option>@endforeach</select></div>
            <div><label>Value (₹)</label><input name="amount" type="number" min="0" step="0.01" value="{{ $opportunity->amount }}" required></div>
            <div><label>Probability (%)</label><input name="probability" type="number" min="0" max="100" value="{{ $opportunity->probability }}" required></div>
            <div><label>Expected close</label><input name="expected_close_date" type="date" value="{{ optional($opportunity->expected_close_date)->format('Y-m-d') }}"></div>
            <div class="crm-full"><label>Owner</label><select name="assigned_to"><option value="">Unassigned</option>@foreach($staff as $person)<option value="{{ $person->id }}" @selected((int)$opportunity->assigned_to===(int)$person->id)>{{ $person->name }}</option>@endforeach</select></div>
            <div class="crm-full"><label>Lost reason (required when lost)</label><input name="lost_reason" value="{{ $opportunity->lost_reason }}" maxlength="180"></div>
            <div class="crm-full"><label>Notes</label><textarea name="notes">{{ $opportunity->notes }}</textarea></div>
            <div class="crm-full"><button class="crm-btn primary" type="submit">Save changes</button></div>
          </form>
        </details>
        @else <span class="crm-sub">Read only</span> @endif
      </td>
    </tr>
  @empty
    <tr><td colspan="9" class="crm-empty">No opportunities yet. Create one from a qualified enquiry or a new group sales conversation.</td></tr>
  @endforelse
  </tbody></table></div>
</section>

<section class="crm-card" style="margin-top:14px">
  <h2>Campaign performance</h2><p class="crm-sub">Lead attribution uses the exact campaign code recorded on enquiries. ROI is calculated from won opportunity value less actual campaign spend.</p>
  <div class="crm-table-wrap"><table class="crm-table"><thead><tr><th>Campaign</th><th>Channel</th><th>Status</th><th>Budget</th><th>Spend</th><th>Leads</th><th>Admitted</th><th>Won revenue</th><th>Cost / lead</th><th>ROI</th></tr></thead><tbody>
  @forelse($campaigns as $campaign)
    <tr><td><strong>{{ $campaign->name }}</strong><div class="crm-sub">{{ $campaign->campaign_code }}</div></td><td>{{ ucfirst(str_replace('_',' ',$campaign->channel)) }}</td><td><span class="crm-pill {{ $campaign->status }}">{{ ucfirst($campaign->status) }}</span></td><td>₹{{ number_format((float)$campaign->budget,0) }}</td><td>₹{{ number_format((float)$campaign->spend,0) }}</td><td>{{ $campaign->attributed_leads }}</td><td>{{ $campaign->admitted_leads }}</td><td>₹{{ number_format($campaign->won_revenue,0) }}</td><td>{{ $campaign->cost_per_lead === null ? '—' : '₹'.number_format($campaign->cost_per_lead,2) }}</td><td>{{ $campaign->roi_percent === null ? '—' : number_format($campaign->roi_percent,1).'%' }}</td></tr>
  @empty
    <tr><td colspan="10" class="crm-empty">No campaigns configured yet.</td></tr>
  @endforelse
  </tbody></table></div>
</section>
<section class="crm-card" style="margin-top:14px">
  <h2>Task queue</h2><p class="crm-sub">Open and upcoming work across admissions, marketing and opportunity follow-ups. Overdue tasks are prioritised.</p>
  <div class="crm-table-wrap"><table class="crm-table"><thead><tr><th>Task</th><th>Linked record</th><th>Priority</th><th>Due</th><th>Owner</th><th>Status / update</th></tr></thead><tbody>
  @forelse($tasks as $task)
    <tr><td><strong>{{ $task->title }}</strong><div class="crm-sub">{{ ucfirst(str_replace('_',' ',$task->task_type)) }}</div></td><td>@if($task->lead)<a href="{{ route('tagore.admissions.show',$task->lead_id) }}">{{ $task->lead->lead_no }}</a>@elseif($task->opportunity){{ $task->opportunity->name }}@else — @endif</td><td><span class="crm-pill {{ in_array($task->priority,['urgent','high'],true) ? 'negotiation' : '' }}">{{ ucfirst($task->priority) }}</span></td><td>{{ optional($task->due_at)->format('d M Y, H:i') ?: '—' }} @if($task->due_at && $task->due_at->isPast() && in_array($task->status,['open','in_progress'],true))<div class="crm-sub" style="color:#a43e4a">Overdue</div>@endif</td><td>{{ $task->assignee->name ?? 'Unassigned' }}</td>
      <td class="crm-row-actions">@if($canManageTasks)<details><summary>{{ ucfirst(str_replace('_',' ',$task->status)) }} · Update</summary><form class="crm-form" method="POST" action="{{ route('tagore.crm.tasks.update',$task->id) }}">@csrf @method('PATCH')
        <div><label>Status</label><select name="status">@foreach(['open','in_progress','completed','cancelled'] as $status)<option value="{{ $status }}" @selected($task->status===$status)>{{ ucfirst(str_replace('_',' ',$status)) }}</option>@endforeach</select></div>
        <div><label>Priority</label><select name="priority">@foreach(['low','normal','high','urgent'] as $priority)<option value="{{ $priority }}" @selected($task->priority===$priority)>{{ ucfirst($priority) }}</option>@endforeach</select></div>
        <div><label>Due</label><input type="datetime-local" name="due_at" value="{{ optional($task->due_at)->format('Y-m-d\\TH:i') }}"></div>
        <div><label>Owner</label><select name="assigned_to"><option value="">Unassigned</option>@foreach($staff as $person)<option value="{{ $person->id }}" @selected((int)$task->assigned_to===(int)$person->id)>{{ $person->name }}</option>@endforeach</select></div>
        <div class="crm-full"><label>Outcome</label><input name="outcome" maxlength="60" value="{{ $task->outcome }}" placeholder="Connected, callback scheduled, completed"></div>
        <div class="crm-full"><button class="crm-btn primary" type="submit">Save task</button></div>
      </form></details>@else <span class="crm-sub">Read only</span>@endif</td>
    </tr>
  @empty
    <tr><td colspan="6" class="crm-empty">No CRM tasks recorded.</td></tr>
  @endforelse
  </tbody></table></div>
</section>

<section class="crm-card" style="margin-top:14px">
  <h2>Customer support cases</h2><p class="crm-sub">Parent and student issues with priority, SLA deadline, ownership and resolution history.</p>
  <div class="crm-table-wrap"><table class="crm-table"><thead><tr><th>Case</th><th>Contact</th><th>Category</th><th>Priority</th><th>Due</th><th>Owner</th><th>Status / update</th></tr></thead><tbody>
  @forelse($cases as $case)
    <tr><td><strong>#{{ $case->id }} · {{ $case->subject }}</strong><div class="crm-sub">{{ $case->description }}</div></td><td>@if($case->lead)<a href="{{ route('tagore.admissions.show',$case->lead_id) }}">{{ $case->lead->lead_no }}</a>@endif{{ $case->student->name ?? '' }}{{ $case->parent_user_id ? ' · '.($case->parent->name ?? '') : '' }}</td><td>{{ ucfirst(str_replace('_',' ',$case->category)) }}</td><td><span class="crm-pill {{ in_array($case->priority,['urgent','high'],true) ? 'negotiation' : '' }}">{{ ucfirst($case->priority) }}</span></td><td>{{ optional($case->due_at)->format('d M Y, H:i') ?: '—' }} @if($case->due_at && $case->due_at->isPast() && in_array($case->status,['open','in_progress','waiting_on_parent'],true))<div class="crm-sub" style="color:#a43e4a">SLA overdue</div>@endif</td><td>{{ $case->assignee->name ?? 'Unassigned' }}</td>
      <td class="crm-row-actions">@if($canManageCases)<details><summary>{{ ucfirst(str_replace('_',' ',$case->status)) }} · Update</summary><form class="crm-form" method="POST" action="{{ route('tagore.crm.cases.update',$case->id) }}">@csrf @method('PATCH')
        <div><label>Status</label><select name="status">@foreach(['open','in_progress','waiting_on_parent','resolved','closed'] as $status)<option value="{{ $status }}" @selected($case->status===$status)>{{ ucfirst(str_replace('_',' ',$status)) }}</option>@endforeach</select></div>
        <div><label>Priority</label><select name="priority">@foreach(['low','normal','high','urgent'] as $priority)<option value="{{ $priority }}" @selected($case->priority===$priority)>{{ ucfirst($priority) }}</option>@endforeach</select></div>
        <div><label>Due</label><input type="datetime-local" name="due_at" value="{{ optional($case->due_at)->format('Y-m-d\\TH:i') }}"></div>
        <div><label>Owner</label><select name="assigned_to"><option value="">Unassigned</option>@foreach($staff as $person)<option value="{{ $person->id }}" @selected((int)$case->assigned_to===(int)$person->id)>{{ $person->name }}</option>@endforeach</select></div>
        <div class="crm-full"><label>Resolution (required when resolved or closed)</label><textarea name="resolution">{{ $case->resolution }}</textarea></div>
        <div class="crm-full"><button class="crm-btn primary" type="submit">Update case</button></div>
      </form></details>@else <span class="crm-sub">Read only</span>@endif</td>
    </tr>
  @empty
    <tr><td colspan="7" class="crm-empty">No support cases recorded.</td></tr>
  @endforelse
  </tbody></table></div>
</section>
</div>
</body>
</html>
