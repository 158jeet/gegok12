@extends('layouts.app')
@section('content')
<div class="container-fluid py-4">
<h3>2026-27 Legacy Fee Migration</h3>
<p class="text-muted">Preview the complete workbook before applying it. Opening balances are imported as opening financial positions, not fabricated receipts. No batch can be applied while any row requires review.</p>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="card mb-4"><div class="card-body">
<h5>Preview workbook</h5>
<p class="small text-muted">The 2026-27 workbook parser recognizes FEE STRUCTURE, STUDENTS, OPENING, BUS FEE 26-27, XII SCI FEE STRUCTURE, FEE CONCESSION and ALL LEDGER. Header rows are detected per sheet. Legacy numeric identifiers require explicit mapping and are never assumed to be GegoK12 user IDs.</p>
<form method="POST" action="{{ route('tagore.fees.import.preview') }}" enctype="multipart/form-data" class="row g-3">@csrf
<div class="col-md-3"><label class="form-label">Institution</label><select name="institution_id" class="form-select" required><option value="">Select</option>@foreach($institutions as $i)<option value="{{ $i->id }}">{{ $i->display_name }}</option>@endforeach</select></div>
<div class="col-md-3"><label class="form-label">Academic year</label><select name="academic_year_id" class="form-select" required><option value="">Select</option>@foreach($years as $y)<option value="{{ $y->id }}">{{ $y->name }}</option>@endforeach</select></div>
<div class="col-md-4"><label class="form-label">Legacy workbook</label><input name="file" type="file" class="form-control" accept=".xlsx,.xls,.csv" required></div>
<div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Preview &amp; validate</button></div>
</form></div></div>
<div class="card"><div class="card-body"><h5>Recent migration batches</h5><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>ID</th><th>Institution</th><th>Source</th><th>Status</th><th>Rows</th><th>Ready</th><th>Errors</th><th>Action</th></tr></thead><tbody>
@forelse($batches as $b)<tr><td>{{ $b->id }}</td><td>{{ $b->institution }}</td><td>{{ $b->source_name }}</td><td><span class="badge bg-secondary">{{ $b->status }}</span></td><td>{{ $b->row_count }}</td><td>{{ $b->success_count }}</td><td class="{{ $b->error_count ? 'text-danger fw-bold' : '' }}">{{ $b->error_count }}</td><td>@if($b->status==='needs_review')<a class="btn btn-sm btn-warning" href="{{ route('tagore.fees.import.mapping',$b->id) }}">Review &amp; map</a>@elseif($b->status==='ready')<a class="btn btn-sm btn-outline-primary me-1" href="{{ route('tagore.fees.import.reconciliation',$b->id) }}">Reconciliation</a><form method="POST" action="{{ route('tagore.fees.import.apply',$b->id) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success">Apply validated batch</button></form>@elseif($b->status==='applied')<a class="btn btn-sm btn-outline-primary" href="{{ route('tagore.fees.import.reconciliation',$b->id) }}">Reconciliation</a>@else<span class="text-muted">Review required</span>@endif</td></tr>@empty<tr><td colspan="8">No migration batches yet.</td></tr>@endforelse
</tbody></table></div></div></div>
</div>
@endsection
