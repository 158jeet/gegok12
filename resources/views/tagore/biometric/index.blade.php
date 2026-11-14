<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Biometric devices · Tagore ERP</title>
<link rel="stylesheet" href="{{ asset('tagore-erp.css') . '?v=20261029-ui10' }}">
<style>
.tg-bio{max-width:1200px;margin:28px auto;padding:0 20px 48px;color:#17212b}.tg-bio h1{margin:0;font-size:27px}.tg-bio-muted{color:#687785;font-size:13px}.tg-bio-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin:22px 0}.tg-bio-card{background:#fff;border:1px solid #dce4e9;border-radius:15px;padding:20px;box-shadow:0 6px 20px rgba(15,23,42,.04)}.tg-bio-card h2{font-size:17px;margin:0 0 14px}.tg-bio-field{display:block;margin-bottom:12px}.tg-bio-field span{display:block;font-size:12px;font-weight:700;margin-bottom:5px}.tg-bio-field input,.tg-bio-field select{box-sizing:border-box;width:100%;padding:10px;border:1px solid #cbd5dc;border-radius:8px;font:inherit}.tg-bio-btn{border:0;border-radius:8px;background:#176b53;color:white;font-weight:700;padding:10px 14px;cursor:pointer}.tg-bio-table{width:100%;border-collapse:collapse;font-size:13px}.tg-bio-table th,.tg-bio-table td{text-align:left;padding:10px;border-bottom:1px solid #e5ebef;vertical-align:top}.tg-bio-scroll{overflow-x:auto}.tg-bio-notice{padding:12px;border-radius:9px;background:#eaf7f0;margin:14px 0}.tg-bio-warn{padding:12px;border-radius:9px;background:#fff4db;color:#76500c;margin:14px 0}@media(max-width:760px){.tg-bio-grid{grid-template-columns:1fr}.tg-bio{padding:0 12px 30px}}
</style>
</head>
<body class="tg-app">
<main class="tg-bio">
  <p><a href="{{ route('tagore.dashboard') }}">← Dashboard</a></p>
  <h1>Biometric device management</h1>
  <p class="tg-bio-muted">Register multiple T304F machines across institutions and locations. Device sync is deliberately disabled until vendor access and a read-only connection test are configured.</p>
  @if(session('success'))<div class="tg-bio-notice" role="status">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="tg-bio-warn">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
  <div class="tg-bio-grid">
    <section class="tg-bio-card">
      <h2>Register a T304F device</h2>
      <form method="POST" action="{{ route('tagore.biometric.store') }}">
        @csrf
        <label class="tg-bio-field"><span>Institution</span><select name="institution_id" required>@foreach($institutions as $institution)<option value="{{ $institution->id }}">{{ $institution->display_name }}</option>@endforeach</select></label>
        <label class="tg-bio-field"><span>Campus / location</span><input name="site_name" maxlength="120" placeholder="e.g. Main campus / Hostel / College" required></label>
        <label class="tg-bio-field"><span>Device name</span><input name="device_name" maxlength="120" placeholder="e.g. Main Gate Attendance" required></label>
        <label class="tg-bio-field"><span>Serial number</span><input name="serial_number" maxlength="120" required></label>
        <label class="tg-bio-field"><span>Firmware version (if known)</span><input name="firmware_version" maxlength="80"></label>
        <button class="tg-bio-btn" type="submit">Register device</button>
      </form>
    </section>
    <section class="tg-bio-card">
      <h2>Integration readiness</h2>
      <p>Vendor connector: RealtimeBioConnect</p>
      <p>Endpoint: <code>https://apirealtime.com/RealtimeBioConnect.asmx</code></p>
      <p>Model: Realtime T304F</p>
      <p>Polling: 5-minute target, overlapping retrieval window, idempotent event storage</p>
      <div class="tg-bio-warn">Automatic sync is not enabled yet. The serial number, API entitlement, vendor credentials, device cloud-server settings and WSDL operation contract must be confirmed before any device is polled.</div>
      <p class="tg-bio-muted">No face/fingerprint templates are copied into ERP. Only attendance event metadata is required.</p>
    </section>
  </div>
  <section class="tg-bio-card">
    <h2>Registered devices</h2>
    <div class="tg-bio-scroll"><table class="tg-bio-table"><thead><tr><th>Institution / location</th><th>Device</th><th>Serial</th><th>Firmware</th><th>Sync</th><th>Last sync</th><th>Last error</th></tr></thead><tbody>
    @forelse($devices as $device)
      <tr><td>{{ $device->institution }}<br><span class="tg-bio-muted">{{ $device->site_name }}</span></td><td>{{ $device->device_name }}<br><span class="tg-bio-muted">{{ $device->model }}</span></td><td>{{ $device->serial_number }}</td><td>{{ $device->firmware_version ?: 'Not provided' }}</td><td>{{ $device->sync_enabled ? 'Enabled' : 'Not configured' }}</td><td>{{ $device->last_synced_at ?: 'Never' }}</td><td>{{ $device->last_error ?: '—' }}</td></tr>
    @empty<tr><td colspan="7">No devices registered yet. Add each machine separately with its campus/location and serial number.</td></tr>@endforelse
    </tbody></table></div>
  </section>
  <section class="tg-bio-card" style="margin-top:18px">
    <h2>Employee ID mappings</h2>
    <p class="tg-bio-muted">Map the numeric/user ID configured inside each machine to an existing ERP employee. The mapping is scoped to the device's institution.</p>
    <div class="tg-bio-scroll"><table class="tg-bio-table"><thead><tr><th>Device</th><th>Machine user ID</th><th>ERP employee</th></tr></thead><tbody>
    @forelse($mappings as $mapping)<tr><td>{{ $mapping->device_name }}</td><td>{{ $mapping->device_user_id }}</td><td>{{ $mapping->employee_name }} (#{{ $mapping->employee_id }})</td></tr>
    @empty<tr><td colspan="3">No employee mappings have been added.</td></tr>@endforelse
    </tbody></table></div>
    @foreach($devices as $device)
    <form method="POST" action="{{ route('tagore.biometric.mapping.store', $device->id) }}" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px">
      @csrf
      <input name="device_user_id" placeholder="Machine user ID" required style="padding:9px;border:1px solid #cbd5dc;border-radius:8px">
      <input name="employee_id" type="number" min="1" placeholder="ERP employee ID" required style="padding:9px;border:1px solid #cbd5dc;border-radius:8px">
      <button class="tg-bio-btn" type="submit">Map on {{ $device->device_name }}</button>
    </form>
    @endforeach
  </section>
</main>
</body>
</html>
