<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Tagore Coaching CBT</title>
<style>
body{font-family:Arial,sans-serif;background:#f4f6f8;color:#17212b;margin:0}.wrap{max-width:1180px;margin:auto;padding:24px}.hero{background:#142b45;color:#fff;border-radius:16px;padding:24px;margin-bottom:18px}.panel{background:#fff;border:1px solid #dfe5eb;border-radius:14px;padding:20px;margin:16px 0}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px}label{display:block;font-size:13px;font-weight:700;margin:10px 0 5px}input,select,textarea{box-sizing:border-box;width:100%;padding:10px;border:1px solid #cbd5df;border-radius:8px;margin-top:5px}textarea{min-height:74px}button,.btn{background:#146b54;color:white;border:0;border-radius:8px;padding:10px 14px;cursor:pointer;font-weight:700;text-decoration:none;display:inline-block}.muted{color:#617080;font-size:13px}.item{border:1px solid #e1e7ed;border-radius:10px;padding:12px;margin:8px 0}.tag{display:inline-block;padding:3px 8px;border-radius:20px;background:#e9f0f5;font-size:12px;margin-right:5px}.danger{background:#a93434}
</style></head><body><main class="wrap">
<header class="hero"><p class="muted" style="color:#b9c8d7">TAGORE VEDANT ACADEMY · COACHING ASSESSMENT</p><h1 style="margin:4px 0">JEE / NEET Computer-Based Tests</h1><p style="margin:0;color:#dce6ee">Question bank, paper blueprints, timed attempts and performance analytics — separate from school examinations and Olympiads.</p></header>
@if(session('success'))<div class="panel">{{ session('success') }}</div>@endif
@if($isStudent)
<section class="panel"><h2>Available mock tests</h2><p class="muted">Your attempt is timed from the moment you start. Answers autosave as you work.</p>
@forelse($papers as $paper)<div class="item"><strong>{{ $paper->name }}</strong><p><span class="tag">{{ $paper->exam_type }}</span><span class="tag">{{ $paper->duration_minutes }} minutes</span><span class="tag">{{ $paper->max_attempts }} attempt(s)</span></p><form method="POST" action="{{ route('tagore.cbt.start',$paper->id) }}">@csrf<button>Start / Resume test</button></form></div>@empty<p class="muted">No mock tests are currently available for your institution.</p>@endforelse</section>
@else
<div class="grid">
<section class="panel"><h2>Create question</h2><p class="muted">Questions are saved as drafts. A manager must approve them before they can enter a published paper.</p>
<form id="question-form" method="POST" action="{{ route('tagore.cbt.questions.store') }}">@csrf
<label>Institution<select name="institution_id" required>@foreach($institutions as $institution)<option value="{{ $institution->id }}">{{ $institution->display_name }}</option>@endforeach</select></label>
<label>Question code (optional)<input name="question_code" maxlength="80"></label>
<label>Subject<select name="subject" required><option>PHYSICS</option><option>CHEMISTRY</option><option>MATHS</option><option>BIOLOGY</option></select></label>
<label>Chapter<input name="chapter" required maxlength="120"></label><label>Topic<input name="topic" maxlength="120"></label>
<label>Difficulty<select name="difficulty"><option>easy</option><option selected>medium</option><option>hard</option></select></label>
<label>Question type<select name="question_type" id="question-type"><option value="single_choice">Single correct</option><option value="multiple_choice">Multiple correct</option><option value="numerical">Numerical</option><option value="assertion_reason">Assertion / reason</option></select></label>
<label>Question statement<textarea name="stem" required></textarea></label>
<label>Options JSON (choice questions)<textarea id="options-json" placeholder='{"A":"Option A","B":"Option B","C":"Option C","D":"Option D"}'></textarea></label>
<label>Correct answer JSON<textarea id="answer-json" required placeholder='"B" or ["A","C"] or {"value":12.5,"tolerance":0.01}'></textarea></label>
<label>Explanation<textarea name="explanation"></textarea></label>
<div class="grid"><div><label>Marks<input name="default_marks" type="number" min="0" step="0.25" value="4"></label></div><div><label>Negative marks<input name="default_negative_marks" type="number" min="0" step="0.25" value="1"></label></div></div>
<button type="submit">Save draft question</button></form>
</section>
<section class="panel"><h2>Create paper blueprint</h2><p class="muted">Supports JEE Main, JEE Advanced and NEET with configurable sections, timing and attempt limits.</p>
<form id="paper-form" method="POST" action="{{ route('tagore.cbt.papers.store') }}">@csrf
<label>Institution<select name="institution_id" required>@foreach($institutions as $institution)<option value="{{ $institution->id }}">{{ $institution->display_name }}</option>@endforeach</select></label>
<label>Paper name<input name="name" required maxlength="160"></label><label>Exam pattern<select name="exam_type"><option>JEE_MAIN</option><option>JEE_ADVANCED</option><option>NEET</option></select></label>
<label>Duration (minutes)<input name="duration_minutes" type="number" min="1" max="600" value="180" required></label>
<label>Maximum attempts<input name="max_attempts" type="number" min="1" max="5" value="1"></label>
<label>Available from<input name="available_from" type="datetime-local"></label><label>Available until<input name="available_until" type="datetime-local"></label>
<label>Instructions<textarea name="instructions"></textarea></label>
<label>Sections JSON<textarea id="sections-json" required>{"sections":[{"section_key":"P","title":"Physics","question_limit":25,"required_answer_count":25,"section_type":"single_choice"},{"section_key":"C","title":"Chemistry","question_limit":25,"required_answer_count":25,"section_type":"single_choice"},{"section_key":"M","title":"Mathematics","question_limit":25,"required_answer_count":25,"section_type":"single_choice"}]}</textarea></label>
<label><input type="checkbox" name="shuffle_questions" value="1" checked style="width:auto"> Shuffle questions</label>
<label><input type="checkbox" name="shuffle_options" value="1" style="width:auto"> Shuffle options</label>
<button type="submit">Create draft paper</button></form>
</section></div>
<section class="panel"><h2>Question bank</h2>@forelse($questions as $question)<div class="item"><strong>{{ $question->question_code }}</strong> · {{ $question->subject }} / {{ $question->chapter }} <span class="tag">{{ $question->difficulty }}</span><span class="tag">{{ $question->status }}</span>
@if($question->status==='draft' && $roles->intersect(['OWNER','PRINCIPAL','COORDINATOR'])->isNotEmpty())<form method="POST" action="{{ route('tagore.cbt.questions.approve',$question->id) }}" style="display:inline">@csrf @method('PATCH')<button>Approve</button></form>@endif</div>@empty<p class="muted">No questions have been added yet.</p>@endforelse</section>
<section class="panel"><h2>Mock papers</h2>@forelse($papers as $paper)<div class="item"><strong>{{ $paper->name }}</strong> <span class="tag">{{ $paper->exam_type }}</span><span class="tag">{{ $paper->status }}</span><span class="tag">{{ $paper->duration_minutes }} min</span>
@if($paper->status==='draft' && $roles->intersect(['OWNER','PRINCIPAL','COORDINATOR'])->isNotEmpty())
<form method="POST" action="{{ route('tagore.cbt.papers.questions.store',$paper->id) }}" class="grid">@csrf
<label>Section<select name="section_id" required>@foreach($sections->where('paper_id',$paper->id) as $section)<option value="{{ $section->id }}">{{ $section->title }}</option>@endforeach</select></label>
<label>Approved question<select name="question_id" required>@foreach($approvedQuestions->where('institution_id',$paper->institution_id) as $question)<option value="{{ $question->id }}">{{ $question->question_code }} · {{ $question->subject }} · {{ $question->chapter }}</option>@endforeach</select></label>
<label>Marks<input name="marks" type="number" min="0" step="0.25" value="4"></label><label>Negative marks<input name="negative_marks" type="number" min="0" step="0.25" value="1"></label><button>Add question</button></form>
<form method="POST" action="{{ route('tagore.cbt.papers.publish',$paper->id) }}" style="margin-top:8px">@csrf @method('PATCH')<button>Publish paper</button></form>
@endif
@if($paper->status==='published')<a class="btn" href="{{ route('tagore.cbt.analytics',$paper->id) }}">View analytics</a>@endif
</div>@empty<p class="muted">No papers have been created yet.</p>@endforelse</section>
@endif
</main>
<script>
const csrf=document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
const questionForm=document.getElementById('question-form');
if(questionForm){questionForm.addEventListener('submit',async e=>{e.preventDefault();try{const payload=Object.fromEntries(new FormData(questionForm).entries());payload.options= document.getElementById('options-json').value.trim()?JSON.parse(document.getElementById('options-json').value):null;payload.answer=JSON.parse(document.getElementById('answer-json').value);delete payload._token;const response=await fetch(questionForm.action,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify(payload)});if(!response.ok){const d=await response.json().catch(()=>({}));throw new Error(d.message||'Question could not be saved.');}location.reload();}catch(error){alert(error.message);}});}
const paperForm=document.getElementById('paper-form');
if(paperForm){paperForm.addEventListener('submit',async e=>{e.preventDefault();try{const payload=Object.fromEntries(new FormData(paperForm).entries());const sectionData=JSON.parse(document.getElementById('sections-json').value);payload.sections=sectionData.sections||sectionData;payload.shuffle_questions=paperForm.elements.shuffle_questions.checked;payload.shuffle_options=paperForm.elements.shuffle_options.checked;delete payload._token;const response=await fetch(paperForm.action,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify(payload)});if(!response.ok){const d=await response.json().catch(()=>({}));throw new Error(d.message||'Paper could not be created.');}location.reload();}catch(error){alert(error.message);}});}
</script></body></html>