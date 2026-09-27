<?php

namespace App\Services\Kobo;

use App\Models\KoboSubmission;
use App\Models\TagoreAdmissionActivity;
use App\Models\TagoreAdmissionLead;
use Illuminate\Support\Facades\DB;

class KoboAdmissionImporter
{
    public function import(?string $assetUid = null): array
    {
        $assetUid ??= config('kobo.asset_uid');
        $institutionId = config('kobo.institution_id');

        if (!$assetUid) {
            throw new \RuntimeException('KOBO_ASSET_UID is not configured.');
        }
        if (!$institutionId) {
            throw new \RuntimeException('KOBO_INSTITUTION_ID is not configured.');
        }

        $stats = ['imported' => 0, 'linked' => 0, 'skipped' => 0, 'failed' => 0];

        KoboSubmission::query()
            ->where('asset_uid', $assetUid)
            ->whereNull('tagore_admission_lead_id')
            ->orderBy('id')
            ->chunkById(100, function ($submissions) use (&$stats, $institutionId) {
                foreach ($submissions as $submission) {
                    try {
                        $result = $this->importSubmission($submission, (int) $institutionId);
                        $stats[$result]++;
                    } catch (\Throwable $e) {
                        $submission->update(['import_error' => $e->getMessage()]);
                        $stats['failed']++;
                    }
                }
            });

        return $stats;
    }

    private function importSubmission(KoboSubmission $submission, int $institutionId): string
    {
        $data = is_array($submission->data) ? $submission->data : [];
        $student = $this->value($data, ['student_name', 'student/name', 'name', 'child_name']);
        $parent = $this->value($data, ['parent_name', 'parent/name', 'father_name', 'guardian_name']);
        $mobile = $this->value($data, ['mobile', 'mobile_no', 'phone', 'parent_mobile', 'contact_number']);
        $alternate = $this->value($data, ['alternate_mobile', 'alternate_phone', 'secondary_mobile']);
        $email = $this->value($data, ['email', 'parent_email', 'guardian_email']);
        $class = $this->value($data, ['class_name', 'class', 'standard', 'grade']);
        $notes = $this->value($data, ['notes', 'remarks', 'message', 'enquiry']);
        $source = $this->value($data, ['source', 'lead_source']) ?: 'KoboToolbox';

        if (!$student) {
            $submission->update(['import_error' => 'Student name could not be mapped from Kobo submission.']);
            return 'skipped';
        }

        return DB::transaction(function () use ($submission, $institutionId, $student, $parent, $mobile, $alternate, $email, $class, $notes, $source) {
            $existing = $mobile
                ? TagoreAdmissionLead::where('institution_id', $institutionId)->where('mobile', $mobile)->whereNull('deleted_at')->first()
                : null;

            if ($existing) {
                $submission->update([
                    'tagore_admission_lead_id' => $existing->id,
                    'imported_at' => now(),
                    'import_error' => null,
                ]);
                TagoreAdmissionActivity::create([
                    'lead_id' => $existing->id,
                    'user_id' => null,
                    'type' => 'note',
                    'outcome' => 'kobo_duplicate',
                    'notes' => 'Kobo submission linked to existing lead: '.($submission->submission_uid ?: $submission->id),
                    'completed_at' => now(),
                ]);
                return 'linked';
            }

            $lead = TagoreAdmissionLead::create([
                'institution_id' => $institutionId,
                'lead_no' => 'PENDING-'.bin2hex(random_bytes(8)),
                'student_name' => $student,
                'parent_name' => $parent,
                'mobile' => $mobile,
                'alternate_mobile' => $alternate,
                'email' => $email,
                'class_name' => $class,
                'source' => $source,
                'status' => 'new',
                'notes' => $notes,
            ]);
            $lead->update(['lead_no' => sprintf('ADM-%s-%06d', $institutionId, $lead->id)]);

            TagoreAdmissionActivity::create([
                'lead_id' => $lead->id,
                'user_id' => null,
                'type' => 'note',
                'outcome' => 'kobo_import',
                'notes' => 'Imported from Kobo submission: '.($submission->submission_uid ?: $submission->id),
                'completed_at' => now(),
            ]);

            $submission->update([
                'tagore_admission_lead_id' => $lead->id,
                'imported_at' => now(),
                'import_error' => null,
            ]);

            return 'imported';
        });
    }

    private function value(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data) || is_array($data[$key])) {
                continue;
            }
            $value = trim((string) $data[$key]);
            if ($value !== '') {
                return $value;
            }
        }
        return null;
    }
}
