@extends('layouts.app')
@section('base-content')
<link rel="stylesheet" href="{{ asset('tagore-erp.css') }}">
<div class="tg-app"><div class="tg-shell">
<header class="tg-topbar"><div class="tg-brand"><div class="tg-logo">T</div><div><strong>TagoreK12</strong><span>Payroll & HR</span></div></div><a class="tg-btn" href="{{ route('tagore.operations.index',['module'=>'payroll']) }}">Operations</a></header>
@if(session('success'))<div class="tg-alert success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="tg-alert danger">{{ $errors->first() }}</div>@endif
<section class="tg-hero"><div><span class="tg-eyebrow">Faculty Management</span><h1>Payroll</h1><p>Generate, review, approve and mark monthly payroll as paid, with PF, ESI and TDS components.</p></div></section>
<section class="tg-card">
<h2>Generate payroll</h2>
<form class="tg-form" method="POST" action="{{ route('tagore.payroll.generate') }}">@csrf
<div class="tg-grid">
<label class="tg-field"><span>Institution</span><select name="institution_id" required>@foreach($institutions as $i)<option value="{{ $i->id }}">{{ $i->display_name }}</option>@endforeach</select></label>
<label class="tg-field"><span>Month</span><input type="month" name="month" value="{{ now()->format('Y-m') }}" required></label>
<label class="tg-field" style="grid-column:1/-1"><span>Employees JSON</span><textarea name="employees_json" rows="10" required placeholder='[{"employee_id":123,"basic_salary":30000,"allowances":{"HRA":5000,"TA":2000},"pf_rate":12,"esi_rate":0,"tds":1000,"other_deductions":0}]'></textarea></label>
</div>
<button class="tg-btn primary">Generate payroll</button>
</form>
</section>
<section class="tg-card"><div class="tg-card-head"><div><h2>Payroll runs</h2><p>{{ $runs->count() }} latest runs</p></div></div>
<div class="tg-table-wrap"><table><thead><tr><th>Month</th><th>Institution</th><th>Status</th><th>Gross</th><th>Deductions</th><th>Net</th><th>Actions</th></tr></thead><tbody>
@forelse($runs as $run)<tr><td>{{ $run->month }}</td><td>{{ optional($institutions->firstWhere('id',$run->institution_id))->display_name }}</td><td>{{ $run->status }}</td><td>₹{{ number_format($run->gross_total,2) }}</td><td>₹{{ number_format($run->deduction_total,2) }}</td><td>₹{{ number_format($run->net_total,2) }}</td><td>
@if($run->status==='processed')<form method="POST" action="{{ route('tagore.payroll.approve',$run->id) }}" style="display:inline">@csrf @method('PATCH')<button class="tg-btn">Approve</button></form>@endif
@if($run->status==='approved')<form method="POST" action="{{ route('tagore.payroll.paid',$run->id) }}" style="display:inline">@csrf @method('PATCH')<button class="tg-btn primary">Mark paid</button></form>@endif
</td></tr>@empty<tr><td colspan="7">No payroll runs yet.</td></tr>@endforelse
</tbody></table></div></section>
</div></div>
@endsection
