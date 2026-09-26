<?php

namespace Tests\Feature;

use App\Models\KoboSubmission;
use App\Services\Kobo\KoboAdmissionImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KoboAdmissionImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_kobo_submission_becomes_admission_lead_and_repeat_import_is_idempotent(): void
    {
        $institutionId = (int) DB::table('tagore_institutions')->value('id');

        config([
            'kobo.asset_uid' => 'asset-1',
            'kobo.institution_id' => $institutionId,
        ]);

        $submission = KoboSubmission::create([
            'asset_uid' => 'asset-1',
            'submission_uid' => 'uuid-admission-1',
            'submission_id' => 501,
            'data' => [
                'student_name' => 'Kobo Student',
                'parent_name' => 'Kobo Parent',
                'mobile' => '9876543210',
                'class_name' => 'VIII',
                'notes' => 'Website enquiry',
            ],
            'synced_at' => now(),
        ]);

        $importer = app(KoboAdmissionImporter::class);

        $this->assertSame(['imported' => 1, 'linked' => 0, 'skipped' => 0, 'failed' => 0], $importer->import());
        $this->assertDatabaseHas('tagore_admission_leads', [
            'institution_id' => $institutionId,
            'student_name' => 'Kobo Student',
            'mobile' => '9876543210',
            'source' => 'KoboToolbox',
        ]);
        $this->assertDatabaseHas('tagore_admission_activities', [
            'type' => 'note',
            'outcome' => 'kobo_import',
        ]);

        $this->assertSame(1, KoboSubmission::whereNotNull('tagore_admission_lead_id')->count());
        $this->assertSame(['imported' => 0, 'linked' => 0, 'skipped' => 0, 'failed' => 0], $importer->import());
        $this->assertSame(1, DB::table('tagore_admission_leads')->where('mobile', '9876543210')->count());

        $this->assertNotNull($submission->fresh()->imported_at);
    }

    public function test_matching_mobile_links_new_kobo_submission_to_existing_lead(): void
    {
        $institutionId = (int) DB::table('tagore_institutions')->value('id');
        config([
            'kobo.asset_uid' => 'asset-2',
            'kobo.institution_id' => $institutionId,
        ]);

        $leadId = DB::table('tagore_admission_leads')->insertGetId([
            'institution_id' => $institutionId,
            'lead_no' => 'ADM-EXISTING',
            'student_name' => 'Existing Student',
            'parent_name' => 'Existing Parent',
            'mobile' => '9999999999',
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        KoboSubmission::create([
            'asset_uid' => 'asset-2',
            'submission_uid' => 'uuid-duplicate',
            'data' => [
                'student_name' => 'Existing Student',
                'parent_name' => 'Existing Parent',
                'mobile' => '9999999999',
            ],
            'synced_at' => now(),
        ]);

        $this->assertSame(['imported' => 0, 'linked' => 1, 'skipped' => 0, 'failed' => 0], app(KoboAdmissionImporter::class)->import());
        $this->assertSame(1, DB::table('tagore_admission_leads')->where('institution_id', $institutionId)->where('mobile', '9999999999')->count());
        $this->assertDatabaseHas('tagore_admission_activities', [
            'lead_id' => $leadId,
            'outcome' => 'kobo_duplicate',
        ]);
    }
}
