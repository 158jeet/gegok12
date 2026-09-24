<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tagore Administration</title>
    <style>
        body{font-family:system-ui,-apple-system,sans-serif;background:#f5f7f9;color:#17202a;margin:0}.wrap{max-width:1200px;margin:auto;padding:24px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:18px}.card{background:#fff;border:1px solid #e4e8ec;border-radius:14px;padding:20px;box-shadow:0 2px 8px #00000008}.wide{grid-column:1/-1}h1,h2{margin-top:0}label{display:block;font-size:13px;font-weight:600;margin:10px 0 5px}input,select{width:100%;box-sizing:border-box;padding:10px;border:1px solid #ccd3da;border-radius:8px}button{margin-top:12px;padding:10px 15px;border:0;border-radius:8px;background:#17202a;color:white;font-weight:600;cursor:pointer}.msg{padding:12px;background:#eaf7ee;border-radius:8px;margin-bottom:16px}table{width:100%;border-collapse:collapse;font-size:14px}th,td{text-align:left;padding:10px;border-bottom:1px solid #edf0f2}.muted{color:#68737d;font-size:13px}@media(max-width:650px){.wrap{padding:14px}table{display:block;overflow:auto;white-space:nowrap}}
    </style>
</head>
<body>
<div class="wrap">
    <h1>Tagore Administration</h1>
    <p class="muted">Group, institution, academic-year and role administration. Owner access only.</p>
    @if(session('success'))<div class="msg">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="msg">{{ $errors->first() }}</div>@endif

    <div class="grid">
        <section class="card">
            <h2>Tagore Group</h2>
            <strong>{{ $group?->name ?? 'Not configured' }}</strong>
            <p class="muted">Code: {{ $group?->code ?? '—' }}</p>
            <p class="muted">The group is the top-level boundary for institutions and future reporting.</p>
        </section>

        <section class="card">
            <h2>Add Institution</h2>
            <form method="post" action="{{ url('/tagore/admin/institution') }}">
                @csrf
                <label>GegoK12 school</label>
                <select name="school_id" required>
                    @forelse($availableSchools as $school)
                        <option value="{{ $school->id }}">{{ $school->name }}</option>
                    @empty
                        <option value="" disabled>No unlinked GegoK12 schools available</option>
                    @endforelse
                </select>
                <label>Institution code</label><input name="code" placeholder="e.g. TPS" required>
                <label>Display name</label><input name="display_name" placeholder="e.g. Tagore Public School" required>
                <label>Type</label><input name="type" placeholder="e.g. school" value="">
                <button>Add institution</button>
            </form>
        </section>

        <section class="card wide">
            <h2>Institutions</h2>
            <table><thead><tr><th>Code</th><th>Name</th><th>Type</th><th>GegoK12 school</th><th>Status</th></tr></thead><tbody>
            @forelse($institutions as $i)<tr><td>{{ $i->code }}</td><td>{{ $i->display_name }}</td><td>{{ $i->type }}</td><td>{{ $i->school_name }}</td><td>{{ $i->status }}</td></tr>@empty<tr><td colspan="5">No institutions configured.</td></tr>@endforelse
            </tbody></table>
        </section>

        <section class="card">
            <h2>Add Academic Year</h2>
            <form method="post" action="{{ url('/tagore/admin/academic-year') }}">
                @csrf
                <label>Institution</label><select name="institution_id" required>@foreach($institutions as $i)<option value="{{ $i->id }}">{{ $i->display_name }}</option>@endforeach</select>
                <label>Name</label><input name="name" placeholder="e.g. 2026-27" required>
                <label>Start date</label><input type="date" name="start_date" required>
                <label>End date</label><input type="date" name="end_date" required>
                <label>Status</label><select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select>
                <button>Add academic year</button>
            </form>
        </section>

        <section class="card">
            <h2>Assign Role</h2>
            <form method="post" action="{{ url('/tagore/admin/role') }}">
                @csrf
                <label>User ID</label><input type="number" name="user_id" placeholder="GegoK12 user ID" required>
                <label>Role</label><select name="role_id" required>@foreach($rolesList as $r)<option value="{{ $r->id }}">{{ $r->name }} ({{ $r->code }})</option>@endforeach</select>
                <label>Institution</label><select name="institution_id"><option value="">Group-wide / none</option>@foreach($institutions as $i)<option value="{{ $i->id }}">{{ $i->display_name }}</option>@endforeach</select>
                <button>Save role assignment</button>
            </form>
            <p class="muted">Institution assignment is mandatory in practice for non-owner operational roles.</p>
        </section>

        <section class="card wide">
            <h2>Academic Years</h2>
            <table><thead><tr><th>Name</th><th>Institution</th><th>Start</th><th>End</th><th>Status</th></tr></thead><tbody>
            @forelse($years as $y)<tr><td>{{ $y->name }}</td><td>{{ $y->institution ?? '—' }}</td><td>{{ $y->start_date }}</td><td>{{ $y->end_date }}</td><td>{{ $y->status }}</td></tr>@empty<tr><td colspan="5">No academic years configured.</td></tr>@endforelse
            </tbody></table>
        </section>

        <section class="card wide">
            <h2>Active Role Assignments</h2>
            <table><thead><tr><th>User</th><th>User ID</th><th>Role</th><th>Institution</th></tr></thead><tbody>
            @forelse($assignments as $a)<tr><td>{{ $a->name }}</td><td>{{ $a->user_id }}</td><td>{{ $a->role_name }}</td><td>{{ $a->institution ?? 'Group-wide' }}</td></tr>@empty<tr><td colspan="4">No role assignments.</td></tr>@endforelse
            </tbody></table>
        </section>
    </div>
</div>
</body>
</html>
