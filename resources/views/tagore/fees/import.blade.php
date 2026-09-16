@extends('layouts.app')
@section('content')
<div class="container-fluid py-4">
<h3>Fee Data Import</h3>
<p class="text-muted">Import legacy opening balances without fabricating historical receipts.</p>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="card mb-4"><div class="card-body">
<h5>Preview opening balances</h5>
<p class="small text-muted">Upload XLSX, XLS or CSV containing an OPENING sheet. Matching uses GegoK12 student ID first, then exact name. Ambiguous or unmatched rows are not imported.</p>
<form method="POST" action="{{ route('tagore.fees.import.preview') }}" enctype="multipart/form-data" class="row g-3">@csrf
<div class="col-md-4"><label>Institution</label><select name="institution_id" class="form-select" required><option value="">Select</option>@foreach($institutions as $institution)<option value="{{ $institution->id }}">{{ $institution->display_name }}</option>@endforeach</select></div>
<div class="col-md-3"><label>Academic year ID</label><input name="academic_year_id" type="number" class="form-control"></div>
<div class="col-md-4"><label>Legacy file</label><input name="file" type="file" class="form-control" accept=".xlsx,.xls,.csv" required></div>
<div class="col-md-1 d-flex align-items-end"><button class="btn btn-primary">Preview</button></div>
</form></div></div>
<div class="card"><div class="card-body"><h5>Recent imports</h5><div class="table-responsive"><table class="table table-sm"><thead><tr><th>ID</th><th>Institution</th><th>Source</th><th>Status</th><th>Rows</th><th>Ready</th><th>Errors</th><th></th></tr></thead><tbody>
@forelse($batches as $batch)<tr><td>{{ $batch->id }}</td><td>{{ $batch->institution }}</td><td>{{ $batch->source_name }}</td><td>{{ $batch->status }}</td><td>{{ $batch->row_count }}</td><td>{{ $batch->success_count }}</td><td>{{ $batch->error_count }}</td><td>@if(in_array($batch->status,['ready','needs_review']))<form method="POST" action="{{ route('tagore.fees.import.apply',$batch->id) }}">@csrf<button class="btn btn-sm btn-success">Apply matched</button></form>@endif</td></tr>@empty<tr><td colspan="8">No imports yet.</td></tr>@endforelse
</tbody></table></div></div></div>
</div>
@endsection
