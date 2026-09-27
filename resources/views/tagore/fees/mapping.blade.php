@extends('layouts.app')
@section('content')
<div class="container-fluid py-4">
<h3>Legacy Student Mapping — Batch #{{ $batch->id }}</h3>
<p class="text-muted">Every unresolved legacy student must be explicitly linked to a GegoK12 student before the batch can be applied. Numeric legacy IDs are never assumed to be GegoK12 user IDs.</p>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(!$rows->count())<div class="alert alert-success">No unresolved student rows remain. The batch is ready for final validation.</div>@else
<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Sheet row</th><th>Legacy key</th><th>Legacy student</th><th>Issue</th><th>Map to GegoK12 student</th><th></th></tr></thead><tbody>
@foreach($rows as $row)<tr>
<td>{{ $row->row_number }}</td><td>{{ $row->external_student_key ?: '—' }}</td><td>{{ data_get(json_decode($row->raw_json,true),'data.STUDENT NAME') ?: data_get(json_decode($row->raw_json,true),'data.STUDENT') ?: data_get(json_decode($row->raw_json,true),'data.NAME') }}</td><td class="text-danger">{{ $row->error_message }}</td>
<td><form method="POST" action="{{ route('tagore.fees.import.mapping.store',[$batch->id,$row->id]) }}" class="d-flex gap-2">@csrf<select name="student_id" class="form-select form-select-sm" required><option value="">Select student</option>@foreach($students as $s)<option value="{{ $s->id }}">{{ $s->name }} (#{{ $s->id }})</option>@endforeach</select><button class="btn btn-sm btn-primary">Map</button></form></td>
<td></td></tr>@endforeach
</tbody></table></div>@endif
<a class="btn btn-secondary" href="{{ route('tagore.fees.import') }}">Back to imports</a>
</div>
@endsection
