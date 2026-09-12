<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TagoreK12</title>
    <style>
        :root{font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#172033;background:#f4f7fb}
        *{box-sizing:border-box}body{margin:0}.shell{max-width:1180px;margin:auto;padding:28px 18px 60px}.top{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:28px}.brand{font-size:26px;font-weight:800}.sub{color:#64748b;font-size:14px;margin-top:4px}.pill{background:#e8eefc;padding:7px 12px;border-radius:999px;font-size:13px;color:#334155}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px}.card{background:white;border:1px solid #e2e8f0;border-radius:14px;padding:18px;box-shadow:0 2px 8px rgba(15,23,42,.04)}.label{color:#64748b;font-size:13px}.value{font-size:30px;font-weight:800;margin-top:7px}.section{margin-top:24px}.section h2{font-size:18px;margin:0 0 12px}.table{width:100%;border-collapse:collapse}.table th,.table td{text-align:left;padding:12px;border-bottom:1px solid #edf2f7;font-size:14px}.empty{color:#64748b;padding:18px 0}.nav{display:flex;flex-wrap:wrap;gap:8px;margin:18px 0 28px}.nav a{background:white;border:1px solid #e2e8f0;border-radius:10px;padding:9px 13px;text-decoration:none;color:#334155;font-size:14px}.notice{background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;padding:14px;color:#9a3412;margin-bottom:18px}@media(max-width:800px){.grid{grid-template-columns:repeat(2,1fr)}.top{align-items:flex-start;flex-direction:column}}@media(max-width:480px){.grid{grid-template-columns:1fr}.shell{padding:20px 12px}}
    </style>
</head>
<body>
<div class="shell">
    <div class="top">
        <div><div class="brand">TagoreK12</div><div class="sub">Tagore Group school management prototype</div></div>
        <div class="pill">{{ $roles->implode(', ') ?: 'GegoK12 user' }}</div>
    </div>

    <div class="notice">Prototype mode: this dashboard is additive to GegoK12. Fee and exam Pro packages are not required for this foundation.</div>

    <div class="nav">
        <a href="{{ url('/tagore/dashboard') }}">Dashboard</a>
        <a href="{{ url('/tagore/api/dashboard') }}">API JSON</a>
        <a href="{{ url('/dashboard') }}">GegoK12</a>
    </div>

    <div class="grid">
        <div class="card"><div class="label">Institutions</div><div class="value">{{ $stats['institutions'] }}</div></div>
        <div class="card"><div class="label">My children</div><div class="value">{{ $stats['children'] }}</div></div>
        <div class="card"><div class="label">Fee obligations</div><div class="value">{{ $stats['pending_fees'] }}</div></div>
        <div class="card"><div class="label">Open feedback</div><div class="value">{{ $stats['open_feedback'] }}</div></div>
    </div>

    <div class="section card">
        <h2>Tagore institutions</h2>
        @if($institutions->isEmpty())
            <div class="empty">No Tagore institutions have been configured yet. The migration is ready for linking existing GegoK12 schools.</div>
        @else
            <table class="table"><thead><tr><th>Code</th><th>Institution</th><th>GegoK12 school</th><th>Status</th></tr></thead><tbody>
            @foreach($institutions as $institution)<tr><td>{{ $institution->code }}</td><td>{{ $institution->display_name }}</td><td>{{ $institution->school_name }}</td><td>Active</td></tr>@endforeach
            </tbody></table>
        @endif
    </div>

    <div class="section card">
        <h2>My children</h2>
        @if($children->isEmpty())
            <div class="empty">No Tagore parent-child links are configured for this account yet.</div>
        @else
            <table class="table"><thead><tr><th>Student</th><th>Relationship</th></tr></thead><tbody>
            @foreach($children as $child)<tr><td>{{ $child->name }}</td><td>{{ $child->relationship ?: 'Guardian' }}</td></tr>@endforeach
            </tbody></table>
        @endif
    </div>

    <div class="section card">
        <h2>Phase 1</h2>
        <div class="nav">
            <span class="pill">Fees</span><span class="pill">Attendance</span><span class="pill">Results</span><span class="pill">Feedback</span><span class="pill">Notifications</span><span class="pill">Role + Scope</span>
        </div>
        <div class="sub">The next iteration will connect each card to the existing GegoK12 modules and the Tagore financial/feedback services.</div>
    </div>
</div>
</body>
</html>
