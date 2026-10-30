<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Examinations | Tagore ERP</title><link rel="stylesheet" href="{{ asset('tagore-erp.css') }}">
<style>
.exam-shell{max-width:1320px;margin:auto;padding:28px 20px;color:var(--tg-text,#e8edf7)}.exam-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;margin-bottom:22px}.exam-muted{color:var(--tg-muted,#94a3b8)}.exam-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px}.exam-card{background:var(--tg-card,#111827);border:1px solid var(--tg-border,#273449);border-radius:16px;padding:18px;min-width:0}.exam-form{display:grid;gap:12px}.exam-form label{display:grid;gap:6px;font-size:13px;color:var(--tg-muted,#94a3b8)}.exam-form input,.exam-form select{width:100%;min-width:0;background:#0b1220;color:#e8edf7;border:1px solid #334155;border-radius:9px;padding:10px}.exam-btn{background:#166534;color:#fff;border:0;border-radius:9px;padding:10px 14px;font-weight:700;cursor:pointer}.exam-table-wrap{overflow-x:auto;margin-top:16px}.exam-table{width:100%;border-collapse:collapse;min-width:700px}.exam-table th,.exam-table td{text-align:left;padding:11px;border-bottom:1px solid var(--tg-border,#273449)}.exam-status{font-size:12px;border-radius:99px;padding:4px 9px;background:#1e293b}.exam-actions{display:flex;gap:8px;flex-wrap:wrap}.exam-actions form{display:inline}.exam-alert{padding:12px 14px;border-radius:10px;background:#123b2b;margin-bottom:14px}.exam-error{background:#4c1d1d}.exam-small{font-size:12px}
@media(max-width:600px){.exam-shell{padding:18px 12px}.exam-card{padding:14px}}
</style></head>
<body class="tg-app"><main class="exam-shell">
<div class="exam-head"><div><a href="{{ route('tagore.dashboard') }}" class="exam-muted">← Back to dashboard</a><h1 style="font-size:28px;font-weight:800;margin:12px 0 4px">Examinations & results</h1><p class="exam-muted">Create exams, configure subject schedules, enter marks, moderate and publish verified results.</p></div></div>
@if(session('success'))<div class="exam-alert">{{ session('success') }}</div>@endif
@if($errors->any())<div class="exam-alert exam-error"><strong>Please correct the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="exam-grid">
@if($roles->intersect(['OWNER','PRINCIPAL','COORDINATOR','IT_ADMIN'])->isNotEmpty())
<section class="exam-card"><h2 style="font-size:18px;font-weight:750;margin:0 0 14px">Create examination</h2><form class="exam-form" method="post" action="{{ route('tagore.examinations.store') }}">@csrf
<label>Institution<select name="institution_id" id="exam-institution" required>@foreach($institutions as $i)<option value="{{ $i->id }}" data-school-id="{{ $i->school_id }}">{{ $i->display_name }}</option>@endforeach</select></label>
<label>Academic year<select name="academic_year_id" id="exam-year" required>@foreach($academicYears as $y)<option value="{{ $y->id }}" data-school-id="{{ $y->school_id }}">{{ $y->name }}</option>@endforeach</select></label>
<label>Class<select name="standard_link_id" id="exam-section" required>@foreach($standardLinks as $sl)<option value="{{ $sl->id }}" data-school-id="{{ $sl->school_id }}" data-year-id="{{ $sl->academic_year_id }}">{{ $sl->standard_name }} · Section {{ $sl->section_name }}</option>@endforeach</select></label>
<label>Examination name<input name="name" maxlength="120" required placeholder="e.g. Half Yearly Examination"></label>
<label>Exam type<select name="exam_type"><option value="unit_test">Unit test</option><option value="half_yearly">Half yearly</option><option value="annual">Annual</option><option value="pre_board">Pre-board</option><option value="board">Board</option><option value="other">Other</option></select></label>
<label>Start date<input type="date" name="start_date" required></label><label>End date<input type="date" name="end_date" required></label><button class="exam-btn">Create examination</button></form></section>
@endif
<section class="exam-card"><h2 style="font-size:18px;font-weight:750;margin:0 0 8px">Workflow & controls</h2><p class="exam-muted exam-small">Draft → Marks entry → Submitted → Moderated → Published. Only an authorised Principal/Owner can moderate and publish. Publishing updates the existing student/parent result feed. Withdrawals are recorded and hide the result from portals.</p><p class="exam-muted exam-small">Build the subject schedule and enter marks for every enrolled student before submitting results. All changes are restricted to your assigned institution.</p></section>
</div>
<section class="exam-card" style="margin-top:18px"><h2 style="font-size:18px;font-weight:750;margin:0 0 8px">Examination register</h2><div class="exam-table-wrap"><table class="exam-table"><thead><tr><th>Examination</th><th>Institution</th><th>Academic year</th><th>Class</th><th>Dates</th><th>Status</th><th>Workflow</th></tr></thead><tbody>
@forelse($exams as $exam)<tr><td><strong>{{ $exam->name }}</strong><div class="exam-muted exam-small">{{ ucfirst(str_replace('_',' ',$exam->exam_type)) }}</div></td><td>{{ $exam->institution_name }}</td><td>{{ $exam->academic_year_name }}</td><td>{{ $exam->standard_name }}</td><td>{{ $exam->start_date }} – {{ $exam->end_date }}</td><td><span class="exam-status">{{ ucfirst(str_replace('_',' ',$exam->status)) }}</span></td><td><div class="exam-actions">
@if(in_array($exam->status,['draft','marks_entry']))<form method="post" action="{{ route('tagore.examinations.submit',$exam->id) }}">@csrf<button class="exam-btn" type="submit">Submit for moderation</button></form>@endif
@if($exam->status==='submitted' && $roles->intersect(['OWNER','PRINCIPAL','IT_ADMIN'])->isNotEmpty())<form method="post" action="{{ route('tagore.examinations.moderate',$exam->id) }}">@csrf<button class="exam-btn">Moderate</button></form>@endif
@if($exam->status==='moderated' && $roles->intersect(['OWNER','PRINCIPAL','IT_ADMIN'])->isNotEmpty())<form method="post" action="{{ route('tagore.examinations.publish',$exam->id) }}">@csrf<button class="exam-btn">Publish results</button></form>@endif
@if($exam->status==='published' && $roles->intersect(['OWNER','PRINCIPAL','IT_ADMIN'])->isNotEmpty())<form method="post" action="{{ route('tagore.examinations.withdraw',$exam->id) }}">@csrf<button class="exam-btn" style="background:#991b1b">Withdraw</button></form>@endif
</div></td></tr>
<tr><td colspan="7" style="background:rgba(15,23,42,.35)">
<strong>Subject schedule & marks</strong>
@php($scheduledSubjects = $examSubjects->get($exam->id, collect()))
@if($scheduledSubjects->isNotEmpty())
<div class="exam-actions" style="margin:10px 0">@foreach($scheduledSubjects as $scheduled)<span class="exam-status">{{ $scheduled->subject_name }} · {{ $scheduled->max_marks }} marks @if(in_array($exam->status,['draft','marks_entry']))<a style="color:#93c5fd;margin-left:8px" href="{{ route('tagore.examinations.marks',$scheduled->id) }}">Enter marks</a>@endif</span>@endforeach</div>
@else<p class="exam-muted exam-small">No subjects scheduled yet.</p>@endif
@if(in_array($exam->status,['draft','marks_entry']) && $roles->intersect(['OWNER','PRINCIPAL','COORDINATOR','IT_ADMIN'])->isNotEmpty())
<form class="exam-form" style="grid-template-columns:repeat(auto-fit,minmax(130px,1fr));align-items:end;margin-top:10px" method="post" action="{{ route('tagore.examinations.subjects.store',$exam->id) }}">@csrf
<label>Subject<select name="subject_id" required><option value="">Select subject</option>@foreach($subjectOptions->where('school_id',$exam->school_id)->where('academic_year_id',$exam->academic_year_id)->where('standard_id',$exam->standard_id)->where('section_id', DB::table('standards_link')->where('id',$exam->standard_link_id)->value('section_id')) as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select></label>
<label>Maximum marks<input type="number" name="max_marks" min="1" max="10000" step="0.01" value="100" required></label><label>Pass marks<input type="number" name="pass_marks" min="0" max="10000" step="0.01" value="33" required></label>
<label>Exam date<input type="date" name="exam_date" min="{{ $exam->start_date }}" max="{{ $exam->end_date }}"></label><label>Start time<input type="time" name="start_time"></label><label>Duration (minutes)<input type="number" name="duration_minutes" min="1" max="600"></label><button class="exam-btn">Add subject</button></form>
@endif
</td></tr>@empty<tr><td colspan="7" class="exam-muted">No examinations have been created for your institution scope.</td></tr>@endforelse
</tbody></table></div></section>
</main><script>
(function(){
 const institution=document.getElementById('exam-institution');
 const year=document.getElementById('exam-year');
 const section=document.getElementById('exam-section');
 if(!institution||!year||!section)return;
 function filter(select, predicate){
  let first=null;
  Array.from(select.options).forEach(option=>{
   if(!option.value)return;
   const visible=predicate(option);
   option.hidden=!visible; option.disabled=!visible;
   if(visible&&!first)first=option;
  });
  if(first&&select.selectedOptions[0]?.disabled)select.value=first.value;
 }
 function sync(){
  const school=institution.selectedOptions[0]?.dataset.schoolId;
  filter(year,option=>option.dataset.schoolId===school);
  const yearId=year.value;
  filter(section,option=>option.dataset.schoolId===school&&option.dataset.yearId===yearId);
 }
 institution.addEventListener('change',sync);
 year.addEventListener('change',sync);
 sync();
})();
</script></body></html>