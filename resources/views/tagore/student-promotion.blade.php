<x-layouts.app>
    <div class="container py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <h1 class="mb-1">Student Promotion</h1>
                <p class="text-muted mb-0">Promote a selected batch into the next academic year. Source-year records remain unchanged.</p>
            </div>
        </div>

        @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
        @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

        <div class="card p-3 mb-4">
            <h5>Choose institution and academic years</h5>
            <form method="GET" action="{{ route('tagore.students.promotion.index') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Institution</label>
                    <select name="institution_id" class="form-select" required>
                        @foreach($institutions as $item)
                            <option value="{{ $item->id }}" @selected($institutionId === (int) $item->id)>{{ $item->display_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Source academic year</label>
                    <select name="source_academic_year_id" class="form-select" required>
                        @foreach($years as $year)
                            <option value="{{ $year->id }}" @selected($sourceYearId === (int) $year->id)>{{ $year->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Target academic year</label>
                    <select name="target_academic_year_id" class="form-select" required>
                        @foreach($years as $year)
                            <option value="{{ $year->id }}" @selected($targetYearId === (int) $year->id)>{{ $year->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2"><button class="btn btn-outline-primary w-100">Load Students</button></div>
            </form>
        </div>

        @if($institution)
        <div class="card p-3">
            <h5>Select destination class and students</h5>
            <form method="POST" action="{{ route('tagore.students.promote') }}">
                @csrf
                <input type="hidden" name="institution_id" value="{{ $institutionId }}">
                <input type="hidden" name="source_academic_year_id" value="{{ $sourceYearId }}">
                <input type="hidden" name="target_academic_year_id" value="{{ $targetYearId }}">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Destination class / section</label>
                        <select name="target_standard_link_id" class="form-select" required>
                            <option value="">Choose destination class</option>
                            @foreach($classes as $class)
                                <option value="{{ $class->id }}">Class ID {{ $class->standard_id }} — Section ID {{ $class->section_id }} (Link #{{ $class->id }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <span class="text-muted small">Select up to 200 students. Students already enrolled in the target year are skipped without overwriting their records.</span>
                    </div>
                </div>
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                    <strong>Students in source year ({{ $students->count() }} shown; maximum 1,000 loaded)</strong>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.querySelectorAll('.promotion-student').forEach((el, i) => el.checked = i < 200)">Select first 200</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Select</th><th>Student</th><th>Roll number</th><th>Current class link</th></tr></thead>
                        <tbody>
                        @forelse($students as $student)
                            <tr>
                                <td><input class="form-check-input promotion-student" type="checkbox" name="student_ids[]" value="{{ $student->id }}"></td>
                                <td>{{ $student->name }} <span class="text-muted">(#{{ $student->id }})</span></td>
                                <td>{{ $student->roll_number ?: '—' }}</td>
                                <td>{{ $student->standardLink_id ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted">No students found for the selected source year.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <button class="btn btn-primary" @disabled($students->isEmpty() || $classes->isEmpty())>Process Promotion Batch</button>
            </form>
        </div>
        @else
            <div class="alert alert-warning">No institution is available for your active Principal / Coordinator / Owner role.</div>
        @endif
    </div>
</x-layouts.app>
