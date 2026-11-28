@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1>CRM · Cloud calling</h1>
            <p class="text-muted mb-0">Click-to-call, inbound call capture, call dispositions, callback tasks and call history.</p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('tagore.crm.index') }}">CRM workspace</a>
    </div>

    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    @if(session('webhook_url_once'))
        <div class="alert alert-info">
            <strong>Copy this webhook URL into the Exotel call-status callback configuration now. It is shown only once.</strong>
            <input class="form-control mt-2" readonly value="{{ session('webhook_url_once') }}" onclick="this.select()">
        </div>
    @endif

    @if($isOwner)
    <div class="card mb-4">
        <div class="card-header"><h2 class="h5 mb-0">Connect Exotel</h2></div>
        <div class="card-body">
            <form method="post" action="{{ route('tagore.crm.telephony.save') }}">@csrf
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label" for="institution_id">Institution</label><select class="form-select" name="institution_id" id="institution_id" required>@foreach($institutions as $institution)<option value="{{ $institution->id }}">{{ $institution->display_name }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label" for="account_sid">Exotel Account SID</label><input class="form-control" id="account_sid" name="account_sid" required maxlength="120"></div>
                    <div class="col-md-4"><label class="form-label" for="caller_id">Exotel Caller ID</label><input class="form-control" id="caller_id" name="caller_id" required maxlength="40"></div>
                    <div class="col-md-4"><label class="form-label" for="api_key">API Key</label><input class="form-control" id="api_key" name="api_key" required autocomplete="off"></div>
                    <div class="col-md-4"><label class="form-label" for="api_token">API Token</label><input class="form-control" id="api_token" name="api_token" type="password" required autocomplete="new-password"></div>
                    <div class="col-md-4"><label class="form-label" for="api_base_url">API Base URL</label><input class="form-control" id="api_base_url" name="api_base_url" value="https://api.in.exotel.com" required></div>
                    <div class="col-md-12"><label><input type="checkbox" name="record_calls" value="1"> Enable call recording (only after required caller notices and consent are in place)</label></div>
                </div>
                <p class="text-muted small mt-3 mb-2">Credentials are encrypted at rest. Only HTTPS Exotel API hosts are accepted. Use a dedicated provider account and configure the generated webhook URL in the provider portal.</p>
                <button class="btn btn-primary" type="submit">Save telephony connection</button>
            </form>
        </div>
    </div>
    @endif

    <div class="card mb-4">
        <div class="card-header"><h2 class="h5 mb-0">Connected accounts</h2></div>
        <div class="table-responsive"><table class="table table-striped mb-0">
            <thead><tr><th>Institution</th><th>Provider</th><th>Account SID</th><th>Caller ID</th><th>Recording</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($accounts as $account)
                <tr><td>{{ optional($institutions->firstWhere('id',$account->institution_id))->display_name ?: '#'.$account->institution_id }}</td><td>{{ strtoupper($account->provider) }}</td><td>{{ $account->account_sid }}</td><td>{{ $account->caller_id }}</td><td>{{ $account->record_calls ? 'Enabled' : 'Off' }}</td><td><span class="badge {{ $account->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $account->is_active ? 'Active' : 'Disabled' }}</span></td></tr>
            @empty
                <tr><td colspan="6">No cloud telephony account connected yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>

    @if($canManageAgents)
    <div class="card mb-4">
        <div class="card-header"><h2 class="h5 mb-0">Assign calling agents</h2></div>
        <div class="card-body">
            <p class="text-muted small">Each CRM user receives a registered agent number. Click-to-call uses this server-side assignment; staff cannot enter another agent's number when initiating calls.</p>
            <form method="post" action="{{ route('tagore.crm.telephony.agents.save') }}">@csrf
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Telephony account</label><select class="form-select" name="telephony_account_id" required><option value="">Select account</option>@foreach($accounts->where('is_active',true) as $account)<option value="{{ $account->id }}">{{ optional($institutions->firstWhere('id',$account->institution_id))->display_name }} · {{ $account->account_sid }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label">CRM user</label><select class="form-select" name="user_id" required><option value="">Select user</option>@foreach($staff as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label class="form-label">Agent phone number</label><input class="form-control" name="agent_number" placeholder="+919876543210" required pattern="\+?[1-9][0-9]{7,14}"></div>
                    <div class="col-md-1 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit">Save</button></div>
                </div>
            </form>
        </div>
        <div class="table-responsive"><table class="table table-striped mb-0">
            <thead><tr><th>Institution</th><th>Agent</th><th>Account</th><th>Number</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($agents as $agent)
                <tr><td>{{ optional($institutions->firstWhere('id',$agent->institution_id))->display_name }}</td><td>{{ $agent->user->name ?? 'Deleted user' }}</td><td>{{ $agent->account->account_sid ?? '—' }}</td><td>{{ $agent->agent_number }}</td><td>{{ $agent->is_active ? 'Active' : 'Disabled' }}</td></tr>
            @empty
                <tr><td colspan="5">No calling agents configured yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
    @endif

    <div class="card">
        <div class="card-header"><h2 class="h5 mb-0">Recent calls</h2></div>
        <div class="table-responsive"><table class="table table-striped mb-0">
            <thead><tr><th>When</th><th>Lead</th><th>Direction</th><th>Number</th><th>Status</th><th>Duration</th><th>Recording</th><th>Disposition / callback</th></tr></thead>
            <tbody>
            @forelse($calls as $call)
                <tr>
                    <td>{{ optional($call->created_at)->format('d M Y H:i') }}</td>
                    <td>@if($call->lead)<a href="{{ route('tagore.admissions.show',$call->lead_id) }}">{{ $call->lead->lead_no }}</a><div class="small text-muted">{{ $call->lead->student_name }}</div>@else Unmatched inbound @endif</td>
                    <td>{{ ucfirst($call->direction) }}</td>
                    <td>{{ $call->direction === 'inbound' ? $call->from_number : $call->to_number }}</td>
                    <td>{{ ucfirst(str_replace('-',' ',$call->status)) }}</td>
                    <td>{{ $call->duration_seconds }} sec</td>
                    <td>@if($call->recording_url)<a href="{{ $call->recording_url }}" target="_blank" rel="noopener noreferrer">Listen</a>@else — @endif</td>
                    <td>
                        <form method="post" action="{{ route('tagore.crm.telephony.disposition',$call) }}" class="d-flex gap-1 flex-wrap">@csrf
                            <select name="disposition" class="form-select form-select-sm" required><option value="">Disposition</option>@foreach(['connected','callback','interested','not_interested','wrong_number','admission_confirmed'] as $value)<option value="{{ $value }}" @selected($call->disposition===$value)>{{ ucfirst(str_replace('_',' ',$value)) }}</option>@endforeach</select>
                            <input class="form-control form-control-sm" name="next_follow_up_at" type="datetime-local" aria-label="Next follow-up">
                            <input class="form-control form-control-sm" name="notes" maxlength="2000" placeholder="Call notes" aria-label="Call notes">
                            <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">No calls recorded.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</div>
@endsection
