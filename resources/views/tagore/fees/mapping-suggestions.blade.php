@extends('layouts.app')
@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3>Legacy Student Match Review — Batch #{{ $batch->id }}</h3>
            <p class="text-muted mb-0">Only high-confidence matches can be auto-applied. Ambiguous matches remain for manual confirmation.</p>
        </div>
        <form method="POST" action="{{ route('tagore.fees.import.mapping.auto', $batch->id) }}">@csrf
            <button class="btn btn-primary">Apply High-Confidence Matches</button>
        </form>
    </div>

    <div class="row g-3 mb-4">
        @foreach([
            'Master rows' => $report['counts']['master_rows'],
            'Exact registration' => $report['counts']['exact_registration'],
            'High confidence' => $report['counts']['high_confidence'],
            'Review' => $report['counts']['review'],
            'Unmatched' => $report['counts']['unmatched'],
            'Already mapped' => $report['counts']['already_mapped'],
        ] as $label => $value)
        <div class="col-6 col-md-2"><div class="card h-100"><div class="card-body"><div class="text-muted small">{{ $label }}</div><div class="fs-4 fw-bold">{{ $value }}</div></div></div></div>
        @endforeach
    </div>

    <div class="alert alert-warning">
        Automatic matching never treats a name alone as a guaranteed identity. Registration matches are strongest; composite matches are retained for review when confidence is insufficient.
    </div>

    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead><tr><th>Row</th><th>Legacy key</th><th>Legacy student</th><th>Father</th><th>Best candidate</th><th>Confidence</th><th>Reason</th><th>Alternatives</th></tr></thead>
            <tbody>
            @foreach($report['items'] as $item)
                <tr>
                    <td>{{ $item['row_number'] }}</td>
                    <td>{{ $item['source_key'] ?: '—' }}</td>
                    <td>{{ $item['legacy_name'] ?: '—' }}</td>
                    <td>{{ $item['legacy_father'] ?: '—' }}</td>
                    <td>{{ $item['best']['name'] ?? 'No match' }} @if(!empty($item['best']['student_id']))<small class="text-muted">#{{ $item['best']['student_id'] }}</small>@endif</td>
                    <td>{{ $item['best']['confidence'] ?? 0 }}%</td>
                    <td>{{ $item['best']['reason'] ?? 'unmatched' }}</td>
                    <td>
                        @foreach(array_slice($item['candidates'], 1) as $candidate)
                            <div>{{ $candidate['name'] }} — {{ $candidate['confidence'] }}%</div>
                        @endforeach
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <a class="btn btn-secondary" href="{{ route('tagore.fees.import.mapping', $batch->id) }}">Manual mapping</a>
    <a class="btn btn-secondary" href="{{ route('tagore.fees.import') }}">Back to imports</a>
</div>
@endsection
