<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Fieldwork Studio · Tagore ERP</title>
<link rel="stylesheet" href="{{ asset('tagore-erp.css') }}">
<style>
body{margin:0;background:#f3f6fb;color:#172033;font:15px system-ui}.shell{max-width:1100px;margin:auto;padding:24px 16px}.panel{background:white;border:1px solid #e2e8f0;border-radius:14px;padding:18px;margin:14px 0}.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.field{display:flex;flex-direction:column;gap:5px;margin:8px 0}label{font-size:13px;font-weight:650}input,select,textarea,button{font:inherit;border:1px solid #cbd5e1;border-radius:8px;padding:10px}textarea{min-height:130px}button{background:#173b69;color:white;border:0;cursor:pointer}.muted{color:#64748b;font-size:13px}.item{padding:10px;border:1px solid #e2e8f0;border-radius:9px;margin:8px 0}.stack{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.hidden{display:none}.error{color:#b42318}@media(max-width:700px){.grid{grid-template-columns:1fr}}
</style></head>
<body class="tg-app"><main class="shell">
<header class="panel"><strong>Tagore ERP · Admissions Fieldwork Studio</strong><p class="muted">Build reusable forms, publish immutable versions, assign campaigns and submit field enquiries.</p><a href="{{ route('tagore.dashboard') }}">← Dashboard</a></header>
<div id="notice" class="panel hidden" role="status"></div>
@if($canManage)
<section class="panel"><h2>Form template</h2>
<div class="grid"><div class="field"><label>Institution</label><select id="institution"></select></div><div class="field"><label>Template name</label><input id="name" maxlength="150"></div></div>
<div class="field"><label>Description</label><input id="description" maxlength="2000"></div>
<div class="field"><label>Fields schema (JSON)</label><textarea id="fields">[{"key":"student_name","label":"Student name","type":"text","required":true},{"key":"parent_mobile","label":"Parent mobile","type":"text","required":true},{"key":"interested_class","label":"Interested class","type":"select","options":["6","7","8","9","10","11","12"],"required":true},{"key":"needs_hostel","label":"Hostel required?","type":"checkbox","required":false},{"key":"hostel_notes","label":"Hostel details","type":"textarea","required":false,"visible_if":{"field":"needs_hostel","equals":"1"}}]</textarea><p class="muted">Types: text, textarea, number, email, date, select, checkbox. Conditional fields use visible_if.field and visible_if.equals.</p></div>
<div class="stack"><button id="save">Create draft</button><select id="template"><option value="">Choose template</option></select><button id="updateDraft" disabled>Update selected draft</button><button id="publish">Publish new version</button></div></section>
<section class="panel"><h2>Campaign assignment</h2><div class="grid"><div class="field"><label>Campaign name</label><input id="campaignName"></div><div class="field"><label>Published version</label><select id="version"><option value="">Choose version</option></select></div><div class="field"><label>Starts</label><input id="starts" type="datetime-local"></div><div class="field"><label>Ends</label><input id="ends" type="datetime-local"></div></div>
<div class="field"><label>Staff assignments</label><div id="staff" class="stack"></div></div><button id="createCampaign">Create campaign</button><div id="campaigns"></div></section>
@endif
@if($canManage)
<section class="panel" id="reviewPanel"><h2>Submission review</h2><p class="muted">Review submitted enquiries within your institution scope. Decisions remain in the audit trail.</p><div id="reviewQueue" class="muted">Loading submissions…</div></section>
@endif
<section class="panel"><h2>My assigned fieldwork</h2><div id="mine" class="muted">Loading…</div><div id="entry" class="hidden"></div></section>
</main>
<script>
const csrf=@json(csrf_token()), root='/tagore/fieldwork', $=id=>document.getElementById(id), canManage=@json($canManage);let state={};
function msg(t,error=false){$('notice').textContent=t;$('notice').className='panel'+(error?' error':'');}
async function api(path,method='GET',body=null){const r=await fetch(root+path,{method,credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},body:body?JSON.stringify(body):null});const d=await r.json().catch(()=>({message:'Invalid server response'}));if(!r.ok)throw Error(d.message||'Request failed '+r.status);return d;}
function option(text,value){const o=document.createElement('option');o.textContent=text;o.value=value;return o;}
async function load(){const a=await api('/my-campaigns');let m={institutions:[],staff:[],templates:[],versions:[],campaigns:[]};if(canManage){m=await api('/forms');}state=m;state.mine=a.campaigns||[];
if(canManage){
$('institution').replaceChildren(...m.institutions.map(x=>option(x.display_name,x.id)));
$('template').replaceChildren(option('Choose template',''),...(m.templates||[]).map(x=>option(x.name+' · '+x.status,x.id)));
$('template').onchange=()=>{const selected=(state.templates||[]).find(x=>String(x.id)===$('template').value);if(!selected)return;$('name').value=selected.name||'';$('description').value=selected.description||'';$('fields').value=JSON.stringify(JSON.parse(selected.draft_fields||'[]'),null,2);$('updateDraft').disabled=selected.status!=='draft';$('updateDraft').textContent=selected.status==='draft'?'Update selected draft':'Published template — immutable';};

$('version').replaceChildren(option('Choose version',''),...(m.versions||[]).map(x=>option('Template #'+x.template_id+' · v'+x.version,x.id)));
renderStaff();$('institution').onchange=renderStaff;
$('campaigns').replaceChildren(...(m.campaigns||[]).map(x=>{const d=document.createElement('div');d.className='item';d.textContent=x.name+' · '+x.status+' · version '+x.form_version;return d;}));
await loadReviewQueue();
}
$('mine').replaceChildren(...state.mine.map(x=>{const d=document.createElement('div');d.className='item';const b=document.createElement('button');b.textContent='Open '+x.name;b.onclick=()=>openEntry(x);d.append(document.createTextNode(x.name+' · form version '+x.form_version_id+' '),b);return d;}));}
async function loadReviewQueue(){
 if(!canManage)return;
 const data=await api('/submissions/review'),host=$('reviewQueue'),rows=data.submissions||[];
 if(!rows.length){host.textContent='No submitted enquiries are awaiting or have completed review.';return;}
 host.replaceChildren(...rows.map(item=>{
  const card=document.createElement('article');card.className='item';
  const heading=document.createElement('strong');heading.textContent='#'+item.id+' · '+item.campaign_name+' · '+item.status;
  const who=document.createElement('p');who.className='muted';who.textContent='Submitted by '+item.submitted_by_name+' · '+(item.submitted_at||'');
  const payload=document.createElement('pre');payload.style.whiteSpace='pre-wrap';payload.textContent=JSON.stringify(JSON.parse(item.payload||'{}'),null,2);
  card.append(heading,who,payload);
  if(item.status==='submitted'){
   const note=document.createElement('textarea');note.placeholder='Review note (optional)';note.maxLength=2000;note.setAttribute('aria-label','Review note');
   const lead=document.createElement('input');lead.type='number';lead.min='1';lead.placeholder='Admission lead ID (optional)';lead.setAttribute('aria-label','Admission lead ID');
   const actions=document.createElement('div');actions.className='stack';
   for(const decision of ['approved','rejected']){
    const b=document.createElement('button');b.textContent=decision==='approved'?'Approve':'Reject';
    b.onclick=async()=>{try{const body={decision,review_note:note.value};if(lead.value)body.admission_lead_id=Number(lead.value);await api('/submissions/'+item.id+'/review','PATCH',body);msg('Submission #'+item.id+' '+decision+'.');await loadReviewQueue();}catch(e){msg(e.message,true);}};
    actions.append(b);
   }
   card.append(note,lead,actions);
  }else{
   const review=document.createElement('p');review.className='muted';review.textContent='Reviewed '+(item.reviewed_at||'')+' by '+(item.reviewer_name||'unknown')+(item.review_note?' · '+item.review_note:'');card.append(review);
  }
  return card;
 }));
}
function renderStaff(){if(!canManage)return;$('staff').replaceChildren(...(state.staff||[]).filter(x=>String(x.institution_id)===$('institution').value).map(x=>{const l=document.createElement('label');l.className='stack';const c=document.createElement('input');c.type='checkbox';c.value=x.id;c.dataset.staff='1';l.append(c,document.createTextNode(x.name));return l;}));}
$('save').onclick=async()=>{try{const d=await api('/forms','POST',{institution_id:+$('institution').value,name:$('name').value,description:$('description').value,fields:JSON.parse($('fields').value)});msg('Draft saved: #'+d.id);await load();$('template').value=d.id;}catch(e){msg(e.message,true);}};
$('updateDraft').onclick=async()=>{try{const id=+$('template').value;if(!id)throw Error('Select a draft template');const d=await api('/forms/'+id,'PUT',{name:$('name').value,description:$('description').value,fields:JSON.parse($('fields').value)});msg('Draft #'+d.id+' updated.');await load();$('template').value=id;$('template').dispatchEvent(new Event('change'));}catch(e){msg(e.message,true);}};

$('publish').onclick=async()=>{try{const id=+$('template').value;if(!id)throw Error('Select a template');const d=await api('/forms/'+id+'/publish','POST',{fields:JSON.parse($('fields').value)});msg('Published immutable version #'+d.form_version_id);await load();}catch(e){msg(e.message,true);}};
$('createCampaign').onclick=async()=>{try{const assignees=[...document.querySelectorAll('[data-staff]:checked')].map(x=>+x.value);const v=+$('version').value;if(!v||!assignees.length)throw Error('Select a published version and at least one staff member');const template=(state.versions||[]).find(x=>x.id===v);const d=await api('/campaigns','POST',{institution_id:+$('institution').value,template_id:template.template_id,form_version_id:v,name:$('campaignName').value,starts_at:$('starts').value||null,ends_at:$('ends').value||null,assigned_user_ids:assignees});msg('Campaign created #'+d.campaign_id+'; '+d.assigned_count+' staff assigned');await load();}catch(e){msg(e.message,true);}};
function openEntry(c){const root=$('entry');root.classList.remove('hidden');root.replaceChildren();const title=document.createElement('h3');title.textContent=c.name;root.append(title);const payload={},fields=JSON.parse(c.fields);const controls=[];
for(const f of fields){const wrap=document.createElement('div');wrap.className='field';const label=document.createElement('label');label.textContent=f.label;let input;if(f.type==='textarea')input=document.createElement('textarea');else if(f.type==='select'){input=document.createElement('select');(f.options||[]).forEach(v=>input.append(option(v,v)));}else{input=document.createElement('input');input.type=f.type==='checkbox'?'checkbox':f.type==='number'?'number':f.type==='email'?'email':f.type==='date'?'date':'text';}input.oninput=()=>{payload[f.key]=input.type==='checkbox'?(input.checked?'1':'0'):input.value;controls.forEach(x=>{if(x.f.visible_if)x.wrap.classList.toggle('hidden',String(payload[x.f.visible_if.field]??'')!==String(x.f.visible_if.equals));});};wrap.append(label,input);root.append(wrap);controls.push({f,wrap,input});}
for(const final of [false,true]){const b=document.createElement('button');b.textContent=final?'Submit final':'Save draft';b.style.marginRight='8px';b.onclick=async()=>{try{controls.forEach(x=>payload[x.f.key]=x.input.type==='checkbox'?(x.input.checked?'1':'0'):x.input.value);const d=await api('/campaigns/'+c.id+'/submissions','POST',{payload,finalize:final});msg('Submission #'+d.submission_id+' '+d.status);root.classList.add('hidden');}catch(e){msg(e.message,true);}};root.append(b);}}
load().catch(e=>msg(e.message,true));
</script></body></html>