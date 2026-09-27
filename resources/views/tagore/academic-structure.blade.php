<x-layouts.app>
    <div class="container py-4">
        <h1 class="mb-1">Academic Structure</h1>
        <p class="text-muted">Manage streams and institution-specific class sections without duplicating GegoK12 academic records.</p>

        @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
        @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card p-3 mb-4">
                    <h5>Add Stream</h5>
                    <form method="POST" action="{{ route('tagore.academic.stream') }}">
                        @csrf
                        <label class="form-label">Institution</label>
                        <select name="institution_id" class="form-select mb-2" required>
                            @foreach($institutions as $i)<option value="{{ $i->id }}">{{ $i->display_name }}</option>@endforeach
                        </select>
                        <input name="name" class="form-control mb-2" placeholder="Science" required>
                        <input name="code" class="form-control mb-3" placeholder="SCI" required>
                        <button class="btn btn-primary">Add Stream</button>
                    </form>
                </div>

                <div class="card p-3">
                    <h5>Add Class Section</h5>
                    <form method="POST" action="{{ route('tagore.academic.section') }}">
                        @csrf
                        <select name="institution_id" class="form-select mb-2" required>
                            @foreach($institutions as $i)<option value="{{ $i->id }}">{{ $i->display_name }}</option>@endforeach
                        </select>
                        <select name="academic_year_id" class="form-select mb-2" required>
                            @foreach($years as $y)<option value="{{ $y->id }}">{{ $y->name }}</option>@endforeach
                        </select>
                        <input name="standard_link_id" type="number" class="form-control mb-2" placeholder="GegoK12 class/standard ID" required>
                        <select name="stream_id" class="form-select mb-2">
                            <option value="">No stream</option>
                            @foreach($streams as $s)<option value="{{ $s->id }}">{{ $s->name }} — {{ $s->code }}</option>@endforeach
                        </select>
                        <div class="row g-2"><div class="col"><input name="name" class="form-control" placeholder="Section A1" required></div><div class="col"><input name="code" class="form-control" placeholder="A1" required></div></div>
                        <button class="btn btn-primary mt-3">Add Section</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card p-3 mb-4">
                    <h5>Streams</h5>
                    <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Institution</th><th>Stream</th><th>Code</th></tr></thead><tbody>
                        @forelse($streams as $s)<tr><td>{{ $institutions->firstWhere('id',$s->institution_id)?->display_name }}</td><td>{{ $s->name }}</td><td>{{ $s->code }}</td></tr>@empty<tr><td colspan="3">No streams configured.</td></tr>@endforelse
                    </tbody></table></div>
                </div>
                <div class="card p-3">
                    <h5>Class Sections</h5>
                    <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Institution</th><th>Academic Year</th><th>Class</th><th>Section</th><th>Stream</th></tr></thead><tbody>
                        @forelse($sections as $s)<tr><td>{{ $s->institution }}</td><td>{{ $s->academic_year }}</td><td>{{ $s->standard_link_id }}</td><td>{{ $s->name }}</td><td>{{ $s->stream ?: '—' }}</td></tr>@empty<tr><td colspan="5">No sections configured.</td></tr>@endforelse
                    </tbody></table></div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
