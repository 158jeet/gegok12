@extends('layouts.app')
@section('base-content')
<link rel="stylesheet" href="{{ asset('tagore-erp.css') }}">
<div class="tg-app">
  <div class="tg-shell">
    <header class="tg-topbar">
      <div class="tg-brand"><div class="tg-logo">T</div><div><strong>TagoreK12</strong><span>ERP Operations Hub</span></div></div>
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

    <section class="tg-card">
      <h2>Add {{ $moduleTitle }}</h2>
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
              @if(str_ends_with($column,'_json'))
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
      <div class="tg-card-head">
        <div><h2>Records</h2><p>{{ $rows->count() }} latest records</p></div>
      </div>
      <div class="tg-table-wrap">
        <table>
          <thead><tr><th>ID</th><th>Institution</th>@foreach($columns as $column)<th>{{ ucwords(str_replace('_',' ',$column)) }}</th>@endforeach<th>Actions</th></tr></thead>
          <tbody>
          @forelse($rows as $row)
            <tr>
              <td>#{{ $row->id }}</td>
              <td>{{ optional($institutions->firstWhere('id',$row->institution_id))->display_name ?? '—' }}</td>
              @foreach($columns as $column)
                <td>{{ is_array($row->{$column} ?? null) ? json_encode($row->{$column}) : ($row->{$column} ?? '—') }}</td>
              @endforeach
              <td>
                <details>
                  <summary class="tg-btn">Edit</summary>
                  <form class="tg-form" method="POST" action="{{ route('tagore.operations.update',[$module,$row->id]) }}">
                    @csrf @method('PATCH')
                    <div class="tg-grid">
                      @foreach($columns as $column)
                        <label class="tg-field">
                          <span>{{ ucwords(str_replace('_',' ',$column)) }}</span>
                          @php($value=$row->{$column} ?? null)
                          @if(str_ends_with($column,'_json'))
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
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="{{ count($columns)+3 }}">No records yet.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </section>
  </div>
</div>
@endsection
