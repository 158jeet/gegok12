@extends('layouts.app')
@section('content')
<div class="container-fluid py-4">
<h3>Legacy Fee Reconciliation — Batch #{{ $batch->id }}</h3>
<p class="text-muted">Compares the <strong>ALL LEDGER</strong> snapshot with the current Tagore outstanding ledger. This report never changes balances.</p>
<div class="row g-3 mb-4">
@foreach([['Ledger rows',$report['total_rows'],''],['Matched',$report['matched'],'text-success'],['Mismatches',$report['mismatch'],'text-danger'],['Net delta','₹'.number_format($report['delta'],2),'']] as $card)
<div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">{{ $card[0] }}</div><h4 class="{{ $card[2] }}">{{ $card[1] }}</h4></div></div></div>
@endforeach
</div>
<div class="alert alert-info">Legacy total: ₹{{ number_format($report['source_total'],2) }} · Tagore total: ₹{{ number_format($report['system_total'],2) }}. Investigate every mismatch before production migration.</div>
<div class="card"><div class="card-body"><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Source row</th><th>Student</th><th>Legacy balance</th><th>Tagore balance</th><th>Delta</th><th>Status</th></tr></thead><tbody>
@forelse($report['items'] as $item)<tr><td>{{ $item['row_number'] }}</td><td>{{ $item['student_name'] ?: 'Student #'.$item['student_id'] }}</td><td>₹{{ number_format($item['source_balance'],2) }}</td><td>₹{{ number_format($item['system_balance'],2) }}</td><td class="{{ abs($item['delta'])>0.01?'text-danger fw-bold':'' }}">₹{{ number_format($item['delta'],2) }}</td><td>{{ $item['status'] }}</td></tr>@empty<tr><td colspan="6">No mapped ALL LEDGER rows found.</td></tr>@endforelse
</tbody></table></div></div></div>
<a class="btn btn-secondary mt-3" href="{{ route('tagore.fees.import') }}">Back to imports</a>
</div>
@endsection