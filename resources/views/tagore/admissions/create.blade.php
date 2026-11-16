<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>New enquiry · Tagore ERP</title><link rel="stylesheet" href="{{ asset('tagore-admissions.css') . '?v=20261113-2' }}"></head>
<body><div class="tg-admissions"><div class="tg-ad-shell">
<header class="tg-ad-top"><a class="tg-ad-brand" href="{{ route('tagore.admissions.index') }}"><img src="{{ asset('uploads/tgi-logo.webp') }}" alt="Tagore Group"><div><strong>Tagore ERP</strong><span>Admissions workspace</span></div></a><a class="tg-ad-btn" href="{{ route('tagore.admissions.index') }}">← Back to pipeline</a></header>
<section class="tg-ad-hero"><div><h1>Create an enquiry</h1><p>Capture the family’s details, select the right institution and assign a clear owner for the next follow-up.</p></div></section>
@if($errors->any())<div class="tg-ad-errors" role="alert"><strong>Please review these fields:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<section class="tg-ad-card"><form method="post" action="{{ route('tagore.admissions.store') }}">@csrf
<div class="tg-ad-formgrid">
<div class="tg-ad-field"><label for="institution_id">Institution *</label><select id="institution_id" name="institution_id" required>@foreach($institutions as $i)<option value="{{ $i->id }}" @selected((string)old('institution_id')===(string)$i->id)>{{ $i->display_name }}</option>@endforeach</select></div>
<div class="tg-ad-field"><label for="academic_year_id">Academic year ID</label><input id="academic_year_id" name="academic_year_id" type="number" min="1" value="{{ old('academic_year_id') }}" placeholder="Optional"></div>
<div class="tg-ad-field"><label for="student_name">Student name *</label><input id="student_name" name="student_name" required maxlength="150" value="{{ old('student_name') }}" placeholder="Prospective student’s full name"></div>
<div class="tg-ad-field"><label for="parent_name">Parent / guardian name</label><input id="parent_name" name="parent_name" maxlength="150" value="{{ old('parent_name') }}"></div>
<div class="tg-ad-field"><label for="mobile">Primary mobile</label><input id="mobile" name="mobile" maxlength="30" value="{{ old('mobile') }}" inputmode="tel"></div>
<div class="tg-ad-field"><label for="alternate_mobile">Alternate mobile</label><input id="alternate_mobile" name="alternate_mobile" maxlength="30" value="{{ old('alternate_mobile') }}" inputmode="tel"></div>
<div class="tg-ad-field"><label for="email">Email</label><input id="email" name="email" type="email" maxlength="190" value="{{ old('email') }}"></div>
<div class="tg-ad-field"><label for="class_name">Class / programme</label><input id="class_name" name="class_name" maxlength="80" value="{{ old('class_name') }}" placeholder="e.g. Class IX, JEE 2027"></div>
<div class="tg-ad-field"><label for="source">Enquiry source</label><input id="source" name="source" maxlength="60" value="{{ old('source') }}" placeholder="Walk-in, website, referral, campaign"></div>
<div class="tg-ad-field"><label for="campaign">Campaign</label><input id="campaign" name="campaign" maxlength="100" value="{{ old('campaign') }}" placeholder="e.g. Admissions 2027"></div>
<div class="tg-ad-field"><label for="assigned_to">Lead owner</label><select id="assigned_to" name="assigned_to"><option value="">Leave unassigned</option>@foreach($staff as $person)<option value="{{ $person->id }}" @selected((string)old('assigned_to')===(string)$person->id)>{{ $person->name }}</option>@endforeach</select></div>
<div class="tg-ad-field"><label for="next_follow_up_at">Next follow-up</label><input id="next_follow_up_at" name="next_follow_up_at" type="datetime-local" value="{{ old('next_follow_up_at') }}"></div>
<div class="tg-ad-field tg-ad-full"><label for="notes">Initial notes</label><textarea id="notes" name="notes" maxlength="5000" placeholder="Parent’s requirement, preferred visit time, programme interest or next action…">{{ old('notes') }}</textarea></div>
</div><div class="tg-ad-actions" style="margin-top:18px"><button type="submit">Create enquiry</button><a class="tg-ad-btn" href="{{ route('tagore.admissions.index') }}">Cancel</a></div>
</form></section></div></div></body></html>