<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Communication | TagoreK12</title>
<link rel="stylesheet" href="{{ asset('tagore-erp.css') }}">
<style>.tg-communication{max-width:1480px;margin:auto;padding:0 28px 60px}.tg-table-scroll{width:100%;overflow-x:auto}.tg-table-scroll table{width:100%;min-width:720px;border-collapse:collapse;background:#fff}.tg-table-scroll th,.tg-table-scroll td{padding:10px;border-bottom:1px solid #edf2f7;text-align:left;font-size:13px;white-space:nowrap}@media(max-width:700px){.tg-communication{padding:0 14px 40px}.tg-form{grid-template-columns:1fr!important}}</style></head>
<body class="tg-app"><div class="tg-communication">
<header class="tg-topbar"><div class="tg-brand"><div class="tg-logo">T</div><div><strong>TagoreK12</strong><span>Communication Centre</span></div></div><a class="tg-btn" href="{{ route('tagore.dashboard') }}">ERP Home</a></header>
@if(session('success'))<div class="tg-alert success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="tg-alert danger">{{ $errors->first() }}</div>@endif
<section class="tg-hero"><div><span class="tg-eyebrow">Communication</span><h1>Campaigns & Alerts</h1><p>Send or schedule email, SMS, WhatsApp and push campaigns with explicit audience filters.</p></div></section>
<section class="tg-card"><h2>Create campaign</h2><form class="tg-form" method="POST" action="{{ route('tagore.communication.create') }}">@csrf
<label class="tg-field"><span>Institution</span><select name="institution_id" required>@foreach($institutions as $i)<option value="{{ $i->id }}">{{ $i->display_name }}</option>@endforeach</select></label>
<label class="tg-field"><span>Title</span><input name="title" required></label><label class="tg-field"><span>Channel</span><select name="channel"><option>email</option><option>sms</option><option>whatsapp</option><option>push</option></select></label>
<label class="tg-field"><span>Scheduled at</span><input type="datetime-local" name="scheduled_at"></label>
<label class="tg-field" style="grid-column:1/-1"><span>Audience JSON</span><textarea name="audience_json" rows="4" required placeholder='{"user_ids":[101,102]}'></textarea></label>
<label class="tg-field" style="grid-column:1/-1"><span>Message</span><textarea name="message" rows="5" required></textarea></label>
<button class="tg-btn primary" type="submit">Create / send campaign</button></form></section>
<section class="tg-card" style="margin-top:18px"><h2>Campaign history</h2><div class="tg-table-scroll"><table><thead><tr><th>Title</th><th>Channel</th><th>Status</th><th>Sent</th><th>Failed</th><th>Action</th></tr></thead><tbody>
@forelse($campaigns as $c)<tr><td>{{ $c->title }}</td><td>{{ $c->channel }}</td><td>{{ $c->status }}</td><td>{{ $c->sent_count }}</td><td>{{ $c->failed_count }}</td><td>@if(in_array($c->status,['queued','scheduled','partial']))<form method="POST" action="{{ route('tagore.communication.send',$c->id) }}" style="display:inline">@csrf @method('PATCH')<button class="tg-btn">Send now</button></form>@else — @endif</td></tr>
@empty<tr><td colspan="6">No campaigns yet.</td></tr>@endforelse</tbody></table></div></section>
</div></body></html>
