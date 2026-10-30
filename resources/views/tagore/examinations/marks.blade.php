<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Marks entry | Tagore ERP</title><link rel="stylesheet" href="{{ asset('tagore-erp.css') }}">
<style>
.mark-shell{max-width:1200px;margin:auto;padding:26px 18px;color:var(--tg-text,#e8edf7)}.mark-card{background:var(--tg-card,#111827);border:1px solid var(--tg-border,#273449);border-radius:16px;padding:18px}.mark-muted{color:var(--tg-muted,#94a3b8)}.mark-wrap{overflow-x:auto;margin-top:16px}.mark-table{border-collapse:collapse;width:100%;min-width:680px}.mark-table th,.mark-table td{text-align:left;padding:10px;border-bottom:1px solid var(--tg-border,#273449)}.mark-table input[type=number],.mark-table input[type=text]{background:#0b1220;color:#e8edf7;border:1px solid #334155;border-radius:8px;padding:8px;max-width:140px}.mark-btn{background:#166534;color:#fff;border:0;border-radius:9px;padding:10px 14px;font-weight:700}.mark-alert{padding:12px;background:#123b2b;border-radius:9px;margin-bottom:14px}@media(max-width:600px){.mark-shell{padding:16px 10px}.mark-card{padding:12px}}
</style></head><body class="tg-app"><main class="mark-shell">
<a class="mark-muted" href="{{ route('tagore.examinations.index') }}">← Back to examinations</a>
<h1 style="font-size:26px;font-weight:800;margin:14px 0 4px">Marks entry</h1>
<p class="mark-muted">{{ $subject->exam_name }} · {{ $subject->subject_name }} · Maximum {{ number_format($subject->max_marks,2) }} · Pass {{ number_format($subject->pass_marks,2) }}</p>
@if(session('success'))<div class="mark-alert">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mark-alert" style="background:#4c1d1d"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<section class="mark-card"><p class="mark-muted">Enter a mark for every enrolled student or tick Absent. Saving updates this subject's mark sheet; final submission locks the examination for moderation.</p>
<form method="post" action="{{ route('tagore.examinations.marks.save',$subject->id) }}" id="exam-marks-form">@csrf
<div class="mark-wrap"><table class="mark-table"><thead><tr><th>Roll no.</th><th>Student</th><th>Marks / {{ number_format($subject->max_marks,2) }}</th><th>Absent</th><th>Remarks</th></tr></thead><tbody>
@forelse($students as $student)@php($mark=$saved->get($student->id))<tr><td>{{ $student->roll_number ?: '—' }}</td><td>{{ $student->name }}</td><td><input type="number" name="marks[{{ $loop->index }}][marks_obtained]" min="0" max="{{ $subject->max_marks }}" step="0.01" value="{{ $mark?->marks_obtained }}" {{ $mark?->is_absent ? 'disabled' : '' }} data-marks-input aria-label="Marks for {{ $student->name }}"><input type="hidden" name="marks[{{ $loop->index }}][student_id]" value="{{ $student->id }}"></td><td><input type="checkbox" name="marks[{{ $loop->index }}][is_absent]" value="1" {{ $mark?->is_absent ? 'checked' : '' }} data-absent-toggle aria-label="Absent: {{ $student->name }}"></td><td><input type="text" name="marks[{{ $loop->index }}][remarks]" maxlength="500" value="{{ $mark?->remarks }}" aria-label="Remarks for {{ $student->name }}"></td></tr>@empty<tr><td colspan="5" class="mark-muted">No students are enrolled in this class for the selected academic year.</td></tr>@endforelse
</tbody></table></div>
@if($students->isNotEmpty())<div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-top:16px"><span class="mark-muted">{{ $students->count() }} enrolled students</span><button class="mark-btn" type="submit">Save marks</button></div>@endif
</form></section></main>
<script>
(function(){
 const form=document.getElementById('exam-marks-form');
 if(!form)return;
 form.querySelectorAll('[data-absent-toggle]').forEach(function(toggle){
  const row=toggle.closest('tr');
  const marks=row&&row.querySelector('[data-marks-input]');
  if(!marks)return;
  const sync=function(){marks.disabled=toggle.checked;if(toggle.checked)marks.value='';};
  toggle.addEventListener('change',sync);
  sync();
 });
})();
</script></body></html>