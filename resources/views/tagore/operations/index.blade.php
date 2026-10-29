[Reading 118 lines from start (total: 118 lines, 0 remaining)]

@extends('layouts.app')
@section('base-content')
<link rel="stylesheet" href="{{ asset('tagore-erp.css') . '?v=20261029-ui11' }}
<style>
.tg-operations-nav{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 16px}
.tg-operations-nav a{display:inline-flex;align-items:center;padding:9px 12px;border:1px solid var(--tg-line);border-radius:11px;background:var(--tg-surface);color:var(--tg-muted);text-decoration:none;font-size:12px;font-weight:700}
.tg-operations-nav a.active{background:#173d32;border-color:#236b52;color:#a7f3d0}
.tg-operations-tools{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:0 0 14px}
.tg-operations-tools input{flex:1 1 260px;max-width:440px}
.tg-operations-tools select{width:auto;min-width:150px}
.tg-operations-meta{font-size:12px;color:var(--tg-muted)}
.tg-operations-form{margin-top:12px}
.tg-operations-form .tg-grid{grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.tg-operations-form .tg-field{min-width:0}
.tg-operations-form input,.tg-operations-form select,.tg-operations-form textarea{width:100%;min-width:0}
.tg-operations-table td{vertical-align:top}
.tg-operations-actions{display:flex;gap:6px;align-items:flex-start;flex-wrap:wrap}
.tg-operations-row-status{display:inline-flex;padding:4px 8px;border-radius:999px;background:#263342;color:#c9d5e2;font-size:10px;font-weight:800}
.tg-operations-empty{display:none;text-align:center;padding:24px;color:var(--tg-muted)}
@media(max-width:760px){.tg-operations-form .tg-grid{grid-template-columns:1fr}.tg-operations-tools input,.tg-operations-tools select{max-width:none;width:100%;flex:1 1 100%}}
</style>
<div class="tg-app">
  <div class="tg-shell">
    <header class="tg-topbar">
      <a class="tg-brand" href="{{ route('tagore.dashboard') }}" style="text-decoration:none"><img class="tg-logo" src="{{ asset('uploads/tgi-logo.webp') }}" alt="Tagore Group of Institutions"><div><strong>Tagore ERP</strong><span>Operations hub</span></div></a>
      <a class="tg-btn" href="{{ route('tagore.dashboard') }}">ERP Home</a>
    </header>

    <div class="tg-nav">
      @foreach($modules as $key=>$label)
        <a class="{{ $module===$key?'active':'' }}" href="{{ route('tagore.operations.index',['module'=>$key]) }}">{{ $label }}</a>
      @endforeach
    </div>

    @if(session('success'))<div class="tg-alert success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="tg-alert danger"><strong>Please correct the form:</strong> {{ $errors->first() }}</div>@endif

    <section class="tg-hero">
      <div>
        <span class="tg-eyebrow">Extended ERP</span>
        <h1>{{ $moduleTitle }}</h1>
        <p>Institution-scoped records with role-based access and audit history.</p>
      </div>
    </section>

    <section class="tg-card tg-operations-form">
      <div class="tg-section-head"><div><h2>Add {{ $moduleTitle }}</h2><p>Use structured status choices and institution-scoped records. Changes are recorded in the audit trail.</p></div></div>
      <form class="tg-form" method="POST" action="{{ route('tagore.operations.store',$module) }}">
        @csrf
        <div class="tg-grid">
          <label class="tg-field">
            <span>Institution</span>
            <select name="institution_id" required>
              @foreach($institutions as $institution)
                <option value="{{ $institution->id }}" {{ (int)$institution->id === (int)$institutionId ? 'selected' : '' }}>{{ $institution->display_name }}</option>
              @endforeach
            </select>
          </label>
          @foreach($columns as $column)
            <label class="tg-field">
              <span>{{ ucwords(str_replace('_',' ',$column)) }}</span>
              @if($column === 'status')
                <select name="{{ $column }}" required>
                  @foreach($statusOptions as $statusOption)<option value="{{ $statusOption }}">{{ ucwords(str_replace('_',' ',$statusOption)) }}</option>@endforeach
                </select>
              @elseif(str_ends_with($column,'_json'))
                <textarea name="{{ $column }}" rows="3" placeholder='{"key":"value"}'></textarea>
              @elseif(in_array($column,['status','channel','format','post_type','content_type','type']))
                <select name="{{ $column }}">
                  <option value="active">Active</option><option value="pending">Pending</option><option value="draft">Draft</option>
                  <option value="scheduled">Scheduled</option><option value="sent">Sent</option><option value="approved">Approved</option>
                </select>
              @elseif(in_array($column,['description','message','body','notes','reason','action','trigger']))
                <textarea name="{{ $column }}" rows="2"></textarea>
              @else
                <input name="{{ $column }}" value="{{ old($column) }}" />
              @endif
            </label>
          @endforeach
        </div>
        <button class="tg-btn primary" type="submit">Create {{ $moduleTitle }} record</button>
      </form>
    </section>

    <section class="tg-card">
      <div class="tg-section-head"><div><h2>Records</h2><p>Latest {{ $rows->count() }} records for your permitted institutions.</p></div><span class="tg-status success">{{ $moduleTitle }}</span></div>
      <div class="tg-operations-tools">
        <input id="tg-operations-search" type="search" placeholder="Search records in this view…" aria-label="Search operation records" autocomplete="off">
        @if(in_array('status',$columns,true))<select id="tg-operations-status" aria-label="Filter by status"><option value="">All statuses</option>@foreach($statusOptions as $statusOption)<option value="{{ $statusOption }}">{{ ucwords(str_replace('_',' ',$statusOption)) }}</option>@endforeach</select>@endif
        <span id="tg-operations-count" class="tg-operations-meta" role="status" aria-live="polite">{{ $rows->count() }} records</span>
      </div>
      <div class="tg-table-wrap">
        <table class="tg-operations-table">
          <thead><tr><th>ID</th><th>Institution</th>@foreach($columns as $column)<th>{{ ucwords(str_replace('_',' ',$column)) }}</th>@endforeach<th>Actions</th></tr></thead>
          <tbody>
          @forelse($rows as $row)
            <tr class="tg-operations-record" data-status="{{ $row->status ?? '' }}">
              <td>#{{ $row->id }}</td>
              <td>{{ optional($institutions->firstWhere('id',$row->institution_id))->display_name ?? '—' }}</td>
              @foreach($columns as $column)
                <td>{{ is_array($row->{$column} ?? null) ? json_encode($row->{$column}) : ($row->{$column} ?? '—') }}</td>
              @endforeach
              <td>
                <div class="tg-operations-actions"><details>
                  <summary class="tg-btn">Edit</summary>
                  <form class="tg-form" method="POST" action="{{ route('tagore.operations.update',[$module,$row->id]) }}">
                    @csrf @method('PATCH')
                    <div class="tg-grid">
                      @foreach($columns as $column)
                        <label class="tg-field">
                          <span>{{ ucwords(str_replace('_',' ',$column)) }}</span>
                          @php($value=$row->{$column} ?? null)
                          @if($column === 'status')
                            <select name="{{ $column }}" required>
                              @foreach($statusOptions as $statusOption)<option value="{{ $statusOption }}" {{ (string)$value === $statusOption ? 'selected' : '' }}>{{ ucwords(str_replace('_',' ',$statusOption)) }}</option>@endforeach
                            </select>
                          @elseif(str_ends_with($column,'_json'))
                            <textarea name="{{ $column }}" rows="3">{{ is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : $value }}</textarea>
                          @elseif(in_array($column,['status','channel','format','post_type','content_type','type']))
                            <input name="{{ $column }}" value="{{ $value }}" />
                          @elseif(in_array($column,['description','message','body','notes','reason','action','trigger']))
                            <textarea name="{{ $column }}" rows="2">{{ $value }}</textarea>
                          @else
                            <input name="{{ $column }}" value="{{ $value }}" />
                          @endif
                        </label>
                      @endforeach
                    </div>
                    <button class="tg-btn primary" type="submit">Save changes</button>
                  </form>
                </details>
                <form method="POST" action="{{ route('tagore.operations.destroy',[$module,$row->id]) }}" onsubmit="return confirm('Delete this record?')">
                  @csrf @method('DELETE')
                  <button class="tg-btn danger" type="submit">Delete</button>
                </form></div>
              </td>
            </tr>
          @empty
            <tr><td colspan="{{ count($columns)+3 }}">No records yet.</td></tr>
          @endforelse
          </tbody>
        </table>
        <div id="tg-operations-empty" class="tg-operations-empty">No records match your search. Try another term or clear the status filter.</div>
      </div>
    </section>
  </div>
</div>

<script>
(() => {
  const search = document.getElementById('tg-operations-search');
  const status = document.getElementById('tg-operations-status');
  const rows = [...document.querySelectorAll('.tg-operations-record')];
  const count = document.getElementById('tg-operations-count');
  const empty = document.getElementById('tg-operations-empty');
  const apply = () => {
    const query = (search?.value || '').trim().toLocaleLowerCase();
    const wantedStatus = status?.value || '';
    let visible = 0;
    rows.forEach(row => {
      const matchesText = !query || row.innerText.toLocaleLowerCase().includes(query);
      const matchesStatus = !wantedStatus || row.dataset.status === wantedStatus;
      const show = matchesText && matchesStatus;
      row.hidden = !show;
      if (show) visible++;
    });
    if (count) count.textContent = visible + ' of ' + rows.length + ' records';
    if (empty) empty.style.display = rows.length && visible === 0 ? 'block' : 'none';
  };
  search?.addEventListener('input', apply);
  status?.addEventListener('change', apply);
  document.addEventListener('keydown', event => {
    if ((event.ctrlKey || event.metaKey) && event.key === '/') {
      event.preventDefault(); search?.focus();
    }
    if (event.key === 'Escape' && document.activeElement === search) {
      search.value = ''; apply();
    }
  });
  apply();
})();
</script>
@endsection

[executed on device: DESKTOP-564ES39 (d76c0fdf-a9a5-457e-b21a-81313e01406e)]