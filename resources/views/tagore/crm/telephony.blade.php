@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h1>CRM - Cloud telephony</h1><p>Institution-scoped click-to-call, inbound call capture and audited dispositions.</p></div>
        <a class="btn btn-outline-secondary" href="{{ route('tagore.admissions.index') }}">Admissions CRM</a>
    </div>
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    @if(session('webhook_url_once'))<div class="alert alert-info"><strong>Copy this webhook URL into the Exotel call-status callback configuration now.</strong><input class="form-control mt-2" readonly value="{{ session('webhook_url_once') }}" onclick="this.select()"></div>@endif

    @if($isOwner)
    <div class="card mb-4"><div class="card-header"><h2 class="h5 mb-0">Connect Exotel</h2></div><div class="card-body">
        <form method="post" action="{{ route('tagore.crm.telephony.save') }}">@csrf
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label" for="institution_id">Institution</label><select class="form-select" name="institution_id" id="institution_id" required>@foreach($institutions as $institution)<option value="{{ $institution->id }}">{{ $institution->display_name }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label" for="account_sid">Exotel Account SID</label><input class="form-control" id="account_sid" name="account_sid" required maxlength="120"></div>
                <div class="col-md-4"><label class="form-label" for="caller_id">Exotel Caller ID</label><input class="form-control" id="caller_id" name="caller_id" required maxlength="40"></div>
                <div class="col-md-4"><label class="form-label" for="api_key">API Key</label><input class="form-control" id="api_key" name="api_key" required autocomplete="off"></div>
                <div class="col-md-4"><label class="form-label" for="api_token">API Token</label><input class="form-control" id="api_token" name="api_token" type="password" required autocomplete="new-password"></div>
                <div class="col-md-4"><label class="form-label" for="api_base_url">API Base URL</label><input class="form-control" id="api_base_url" name="api_base_url" value="https://api.exotel.com" required></div>
            </div>
            <p class="text-muted small mt-3 mb-2">Credentials are encrypted at rest. Only HTTPS Exotel API hosts are accepted. Use a dedicated provider account and configure the generated webhook URL in the provider portal.</p>
            <button class="btn btn-primary" type="submit">Save telephony connection</button>
        </form>
    </div></div>

    @endif
    <div class="card mb-4"><div class="card-header"><h2 class="h5 mb-0">Connected accounts</h2></div><div class="table-responsive"><table class="table table-striped mb-0"><thead><tr><th>Institution ID</th><th>Provider</th><th>Account SID</th><th>Caller ID</th><th>Status</th></tr></thead><tbody>
    @forelse($accounts as $account)<tr><td>{{ $account->institution_id }}</td><td>{{ strtoupper($account->provider) }}</td><td>{{ $account->account_sid }}</td><td>{{ $account->caller_id }}</td><td>{{ $account->is_active ? 'Active' : 'Disabled' }}</td></tr>@empty<tr><td colspan="5">No cloud telephony account connected yet.</td></tr>@endforelse
    </tbody></table></div></div>

    <div class="card"><div class="card-header"><h2 class="h5 mb-0">Recent calls</h2></div><div class="table-responsive"><table class="table table-striped mb-0"><thead><tr><th>When</th><th>Lead</th><th>Direction</th><th>Number</th><th>Status</th><th>Duration</th><th>Disposition</th></tr></thead><tbody>
    @forelse($calls as $call)<tr><td>{{ optional($call->created_at)->format('d M Y H:i') }}</td><td>{{ $call->lead_id ? '#'.$call->lead_id : 'Unmatched inbound' }}</td><td>{{ ucfirst($call->direction) }}</td><td>{{ $call->direction === 'inbound' ? $call->from_number : $call->to_number }}</td><td>{{ ucfirst($call->status) }}</td><td>{{ $call->duration_seconds }} sec</td><td><form method="post" action="{{ route('tagore.crm.telephony.disposition',$call) }}" class="d-flex gap-1 flex-wrap">@csrf<select name="disposition" class="form-select form-select-sm" required><option value="">Disposition</option>@foreach(['connected','callback','interested','not_interested','wrong_number','admission_confirmed'] as $value)<option value="{{ $value }}" @selected($call->disposition===$value)>{{ ucfirst(str_replace('_',' ',$value)) }}</option>@endforeach</select><input class="form-control form-control-sm" name="next_follow_up_at" type="datetime-local" aria-label="Next follow-up"><button class="btn btn-sm btn-outline-primary" type="submit">Save</button></form></td></tr>@empty<tr><td colspan="7">No calls recorded.</td></tr>@endforelse
    </tbody></table></div></div>
</div>
@endsection
