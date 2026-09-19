<?php

namespace App\Services\Tagore;

use Illuminate\Support\Facades\DB;

class LegacyStudentMatcher
{
    public function preview(int $batchId): array
    {
        $batch = DB::table('tagore_fee_import_batches')->where('id', $batchId)->first();
        abort_unless($batch, 404);

        $masterRows = DB::table('tagore_fee_import_rows')
            ->where('batch_id', $batchId)
            ->where('row_type', 'student_master')
            ->orderBy('row_number')
            ->get();

        $students = $this->students((int) $batch->institution_id);
        $mappedKeys = DB::table('tagore_legacy_student_mappings')
            ->where('institution_id', $batch->institution_id)
            ->where('source_system', 'legacy_erp')
            ->pluck('student_id', 'source_key');

        $items = [];
        foreach ($masterRows as $row) {
            $data = $this->data($row);
            $candidates = $this->candidates($data, $students);
            $best = $candidates[0] ?? null;
            $items[] = [
                'row_id' => $row->id,
                'row_number' => $row->row_number,
                'source_key' => $row->external_student_key,
                'legacy_name' => $this->value($data, ['STUDENT NAME', 'STUDENT', 'NAME']),
                'legacy_father' => $this->value($data, ["FATHER'S NAME", 'FATHER NAME', 'FATHER']),
                'legacy_mobile' => $this->value($data, ['MOBILE', 'MOBILE NO', 'CONTACT', 'PHONE']),
                'existing_mapping' => $row->external_student_key && isset($mappedKeys[(string) $row->external_student_key]) ? (int) $mappedKeys[(string) $row->external_student_key] : null,
                'best' => $best,
                'candidates' => array_slice($candidates, 0, 3),
            ];
        }

        $counts = [
            'master_rows' => count($items),
            'exact_registration' => count(array_filter($items, fn ($i) => ($i['best']['reason'] ?? null) === 'registration')),
            'high_confidence' => count(array_filter($items, fn ($i) => ($i['best']['confidence'] ?? 0) >= 90)),
            'review' => count(array_filter($items, fn ($i) => ($i['best']['confidence'] ?? 0) >= 60 && ($i['best']['confidence'] ?? 0) < 90)),
            'unmatched' => count(array_filter($items, fn ($i) => !$i['best'] || ($i['best']['confidence'] ?? 0) < 60)),
            'already_mapped' => count(array_filter($items, fn ($i) => $i['existing_mapping'] !== null)),
        ];

        return ['batch' => $batch, 'counts' => $counts, 'items' => $items];
    }

    public function applyHighConfidence(int $batchId, int $actorId): array
    {
        return DB::transaction(function () use ($batchId, $actorId) {
            $report = $this->preview($batchId);
            $applied = 0;
            foreach ($report['items'] as $item) {
                $best = $item['best'];
                if (!$best || ($best['confidence'] ?? 0) < 90 || $item['existing_mapping'] !== null) continue;
                if (($best['reason'] ?? '') === 'registration' || ($best['confidence'] ?? 0) >= 90) {
                    $this->map($report['batch'], $item['row_id'], (int) $best['student_id'], $actorId, (string) $item['source_key']);
                    $applied++;
                }
            }
            return ['mapped' => $applied, 'report' => $this->preview($batchId)];
        });
    }

    private function students(int $institutionId): array
    {
        $schoolId = DB::table('tagore_institutions')->where('id', $institutionId)->value('school_id');
        if (!$schoolId) return [];

        $students = DB::table('users as u')
            ->leftJoin('userprofiles as up', 'up.user_id', '=', 'u.id')
            ->where('u.school_id', $schoolId)
            ->where('u.usergroup_id', 6)
            ->whereNull('u.deleted_at')
            ->select(['u.id', 'u.name', 'u.mobile_no', 'up.registration_number', 'up.date_of_birth'])
            ->get();

        $parents = DB::table('student_parent_links as spl')
            ->join('users as p', 'p.id', '=', 'spl.parent_id')
            ->where('spl.status', 'active')
            ->whereIn('spl.student_id', $students->pluck('id'))
            ->get(['spl.student_id', 'p.name as parent_name']);

        $parentNames = [];
        foreach ($parents as $parent) $parentNames[(int) $parent->student_id][] = $this->norm($parent->parent_name);

        return $students->map(fn ($s) => [
            'student_id' => (int) $s->id,
            'name' => (string) $s->name,
            'name_norm' => $this->norm($s->name),
            'mobile' => $this->norm($s->mobile_no),
            'registration' => $this->norm($s->registration_number),
            'dob' => $s->date_of_birth ? substr((string) $s->date_of_birth, 0, 10) : null,
            'parent_names' => $parentNames[(int) $s->id] ?? [],
        ])->all();
    }

    private function candidates(array $data, array $students): array
    {
        $name = $this->norm($this->value($data, ['STUDENT NAME', 'STUDENT', 'NAME']));
        $father = $this->norm($this->value($data, ["FATHER'S NAME", 'FATHER NAME', 'FATHER']));
        $mobile = $this->norm($this->value($data, ['MOBILE', 'MOBILE NO', 'CONTACT', 'PHONE']));
        $registration = $this->norm($this->value($data, ['REG NO', 'REGISTRATION NO', 'REGISTRATION NUMBER', 'STUDENT ID', 'ADM NO', 'ADMISSION NO', 'ADMISSION NUMBER']));
        $dob = $this->date($this->value($data, ['DOB', 'DATE OF BIRTH', 'BIRTH DATE']));

        $out = [];
        foreach ($students as $student) {
            $score = 0;
            $reasons = [];
            if ($registration !== '' && $registration === $student['registration']) { $score += 100; $reasons[] = 'registration'; }
            if ($name !== '' && $name === $student['name_norm']) { $score += 55; $reasons[] = 'name'; }
            if ($mobile !== '' && $mobile === $student['mobile']) { $score += 30; $reasons[] = 'mobile'; }
            if ($dob && $dob === $student['dob']) { $score += 15; $reasons[] = 'dob'; }
            if ($father !== '' && in_array($father, $student['parent_names'], true)) { $score += 30; $reasons[] = 'father'; }
            if ($score === 0) continue;

            $confidence = min(100, $score);
            if ($registration !== '' && $registration === $student['registration']) $reason = 'registration';
            elseif ($name !== '' && $name === $student['name_norm'] && $father !== '' && in_array($father, $student['parent_names'], true)) $reason = 'name_father';
            elseif ($name !== '' && $name === $student['name_norm'] && $mobile !== '' && $mobile === $student['mobile']) $reason = 'name_mobile';
            else $reason = 'composite';

            $out[] = [
                'student_id' => $student['student_id'],
                'name' => $student['name'],
                'confidence' => $confidence,
                'reason' => $reason,
                'reasons' => $reasons,
            ];
        }

        usort($out, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);
        if (isset($out[0], $out[1]) && $out[0]['confidence'] === $out[1]['confidence']) {
            $out[0]['confidence'] = max(0, $out[0]['confidence'] - 15);
        }
        return $out;
    }

    private function map(object $batch, int $rowId, int $studentId, int $actorId, string $sourceKey): void
    {
        if ($sourceKey === '') return;
        DB::table('tagore_legacy_student_mappings')->updateOrInsert(
            ['institution_id' => $batch->institution_id, 'source_system' => 'legacy_erp', 'source_key' => $sourceKey],
            ['student_id' => $studentId, 'created_by' => $actorId, 'updated_at' => now(), 'created_at' => now()]
        );
        DB::table('tagore_fee_import_rows')->where('id', $rowId)->update(['student_id' => $studentId, 'status' => 'ready', 'error_message' => null, 'updated_at' => now()]);
    }

    private function data(object $row): array
    {
        $raw = json_decode((string) $row->raw_json, true) ?: [];
        return $raw['data'] ?? [];
    }

    private function value(array $row, array $keys): ?string
    {
        foreach ($keys as $key) if (array_key_exists($key, $row) && trim((string) $row[$key]) !== '') return trim((string) $row[$key]);
        return null;
    }

    private function norm($value): string
    {
        $value = strtoupper(trim((string) $value));
        return preg_replace('/[^A-Z0-9]+/', '', $value) ?? '';
    }

    private function date($value): ?string
    {
        if (!$value) return null;
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'm/d/Y'] as $format) {
            $d = \DateTime::createFromFormat($format, trim((string) $value));
            if ($d) return $d->format('Y-m-d');
        }
        return null;
    }
}
