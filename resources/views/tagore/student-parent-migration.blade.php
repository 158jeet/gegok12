<x-layouts.app>
    <div class="max-w-6xl mx-auto px-4 py-6 space-y-6">
        <div>
            <h1 class="text-2xl font-semibold">Student & Parent Migration</h1>
            <p class="text-sm text-gray-600">Sync existing GegoK12 students, parents and active parent-child links into the Tagore layer. No credentials or legacy records are created or changed.</p>
        </div>

        @if(session('success'))
            <div class="rounded border border-green-200 bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif

        <form method="GET" class="rounded-lg border bg-white p-4 flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-sm font-medium mb-1">Institution source school</label>
                <select name="school_id" class="rounded border px-3 py-2 min-w-64">
                    <option value="">All schools</option>
                    @foreach($schools as $school)
                        <option value="{{ $school->id }}" @selected($schoolId === (int)$school->id)>{{ $school->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="rounded bg-gray-900 text-white px-4 py-2">Refresh preview</button>
        </form>

        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            @foreach([
                ['Students in GegoK12', $summary['students_source']],
                ['Students in Tagore', $summary['students_tagore']],
                ['Parents in GegoK12', $summary['parents_source']],
                ['Parents in Tagore', $summary['parents_tagore']],
                ['Source parent-child links', $summary['links_source']],
                ['Tagore parent-child links', $summary['links_tagore']],
            ] as [$label, $value])
                <div class="rounded-lg border bg-white p-4"><div class="text-sm text-gray-500">{{ $label }}</div><div class="text-2xl font-semibold mt-1">{{ number_format($value) }}</div></div>
            @endforeach
        </div>

        <div class="rounded-lg border bg-amber-50 p-4 text-sm text-amber-900">
            <strong>Safe sync:</strong> the operation is idempotent. It reuses existing GegoK12 users and parent-child links, creates/updates only Tagore role/link records, and writes an audit event for each sync.
        </div>

        <form method="POST" action="{{ route('tagore.migration.student-parent.sync') }}" onsubmit="return confirm('Sync the selected student/parent records into Tagore?');" class="rounded-lg border bg-white p-4">
            @csrf
            <input type="hidden" name="school_id" value="{{ $schoolId }}">
            <button class="rounded bg-blue-600 text-white px-5 py-2 font-medium">Run Student & Parent Sync</button>
        </form>
    </div>
</x-layouts.app>
