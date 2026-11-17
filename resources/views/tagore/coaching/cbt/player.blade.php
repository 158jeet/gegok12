<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $attempt->paper_name }} · CBT</title>
<style>
body{font-family:Arial,sans-serif;background:#eef2f5;color:#182431;margin:0}.top{background:#142b45;color:white;padding:16px 24px;display:flex;justify-content:space-between;align-items:center}.wrap{max-width:1200px;margin:auto;padding:20px;display:grid;grid-template-columns:1fr 260px;gap:16px}.panel{background:white;border:1px solid #dce3ea;border-radius:12px;padding:18px}.q{display:none}.q.active{display:block}.choice{display:block;padding:10px;border:1px solid #dce3ea;border-radius:8px;margin:8px 0}.choice input{margin-right:8px}.nav{display:grid;grid-template-columns:repeat(5,1fr);gap:6px}.nav button{padding:9px;background:#e6edf2;color:#1c3042;border:0;border-radius:6px}.nav button.done{background:#b9e4d4}.nav button.marked{background:#f5dfaa}.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:20px}.actions button{border:0;border-radius:8px;padding:10px 14px;cursor:pointer;background:#145f4b;color:white}.actions .secondary{background:#e7edf2;color:#1c3042}.timer{font-size:22px;font-weight:bold;color:#ffe59a}.muted{color:#68798a;font-size:13px}@media(max-width:800px){.wrap{grid-template-columns:1fr}.nav{grid-template-columns:repeat(8,1fr)}}
</style></head><body>
<header class="top"><div><strong>{{ $attempt->paper_name }}</strong><div class="muted" style="color:#c8d5e0">{{ $attempt->instructions }}</div></div><div><div class="muted" style="color:#c8d5e0">Time remaining</div><div id="timer" class="timer">--:--</div></div></header>
<main class="wrap"><section class="panel">
@foreach($questions as $i=>$item)
<div class="q {{ $i===0?'active':'' }}" data-index="{{ $i }}" data-id="{{ $item['id'] }}" data-type="{{ $item['question']['question_type'] }}" data-marked="{{ $item['marked_for_review'] ? '1' : '0' }}">
<div class="muted">Question {{ $i+1 }} · +{{ $item['marks'] }} / −{{ $item['negative_marks'] }}</div>
<h2>{{ $item['question']['subject'] }} · {{ $item['question']['chapter'] }}</h2><p style="white-space:pre-wrap;font-size:18px">{{ $item['question']['stem'] }}</p>
@if(in_array($item['question']['question_type'],['single_choice','assertion_reason']))
@foreach($item['question']['options']??[] as $key=>$option)<label class="choice"><input type="radio" name="answer-{{ $item['id'] }}" value="{{ $key }}" {{ (string)$item['answer']===(string)$key?'checked':'' }}> <strong>{{ $key }}.</strong> {{ $option }}</label>@endforeach
@elseif($item['question']['question_type']==='multiple_choice')
@foreach($item['question']['options']??[] as $key=>$option)<label class="choice"><input type="checkbox" name="answer-{{ $item['id'] }}[]" value="{{ $key }}" {{ is_array($item['answer'])&&in_array($key,$item['answer'])?'checked':'' }}> <strong>{{ $key }}.</strong> {{ $option }}</label>@endforeach
@else
<label>Numerical answer<input type="number" step="any" class="numerical-answer" value="{{ is_array($item['answer'])?($item['answer']['value']??''):$item['answer'] }}" style="padding:12px;width:180px"></label>
@endif
</div>
@endforeach
<div class="actions"><button type="button" class="secondary" id="prev">Previous</button><button type="button" id="save-next">Save & Next</button><button type="button" class="secondary" id="mark">Mark for review</button><button type="button" class="secondary" id="clear">Clear response</button></div>
</section>
<aside class="panel"><h3>Question palette</h3><div id="palette" class="nav"></div><p class="muted">Green: answered · Amber: marked for review · Grey: not answered</p>
<form id="submit-form" method="POST" action="{{ route('tagore.cbt.submit',$attempt->id) }}">@csrf<button type="submit" class="actions" style="width:100%;justify-content:center;background:#a93434">Submit test</button></form>
<p class="muted" id="save-status">Answers autosave when changed.</p></aside></main>
<script>
const csrf=@json(csrf_token()), saveUrl=@json(route('tagore.cbt.answer',$attempt->id)), expires=Date.parse(@json($attempt->expires_at)), questions=[...document.querySelectorAll('.q')];let current=0,marked=new Set(),answered=new Set();
function active(){questions.forEach((q,i)=>q.classList.toggle('active',i===current));renderPalette();}
function answerFor(q){const type=q.dataset.type;if(type==='multiple_choice')return [...q.querySelectorAll('input[type=checkbox]:checked')].map(x=>x.value);if(type==='numerical'){const v=q.querySelector('.numerical-answer').value;return v===''?null:{value:v};}return q.querySelector('input[type=radio]:checked')?.value??null;}
async function save(q){const answer=answerFor(q);const id=Number(q.dataset.id);await fetch(saveUrl,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({question_id:id,answer,marked_for_review:marked.has(id),time_spent_seconds:0})}).then(async r=>{if(!r.ok)throw new Error('Autosave failed');});if(answer!==null&&answer!==''&&(!Array.isArray(answer)||answer.length))answered.add(id);else answered.delete(id);document.getElementById('save-status').textContent='All changes saved';renderPalette();}
function renderPalette(){const p=document.getElementById('palette');p.replaceChildren();questions.forEach((q,i)=>{const b=document.createElement('button');const id=Number(q.dataset.id);b.textContent=i+1;if(answered.has(id))b.classList.add('done');if(marked.has(id))b.classList.add('marked');b.onclick=()=>{current=i;active();};p.append(b);});}
questions.forEach(q=>{const id=Number(q.dataset.id),answer=answerFor(q);if(answer!==null&&answer!==''&&(!Array.isArray(answer)||answer.length))answered.add(id);if(q.dataset.marked==='1')marked.add(id);});
questions.forEach(q=>q.addEventListener('change',()=>save(q).catch(e=>document.getElementById('save-status').textContent=e.message)));
document.getElementById('prev').onclick=()=>{current=Math.max(0,current-1);active();};
document.getElementById('save-next').onclick=async()=>{try{await save(questions[current]);current=Math.min(questions.length-1,current+1);active();}catch(e){alert(e.message);}};
document.getElementById('mark').onclick=async()=>{const id=Number(questions[current].dataset.id);marked.add(id);await save(questions[current]);};
document.getElementById('clear').onclick=async()=>{questions[current].querySelectorAll('input[type=radio],input[type=checkbox]').forEach(x=>x.checked=false);const n=questions[current].querySelector('.numerical-answer');if(n)n.value='';await save(questions[current]);};
function tick(){const left=Math.max(0,expires-Date.now());const m=Math.floor(left/60000),s=Math.floor((left%60000)/1000);document.getElementById('timer').textContent=String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');if(left<=0){document.getElementById('submit-form').submit();}}
document.getElementById('submit-form').addEventListener('submit',e=>{if(!confirm('Submit this test now? You cannot change answers after submission.'))e.preventDefault();});
active();tick();setInterval(tick,1000);
</script></body></html>