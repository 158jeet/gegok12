<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression contract for the legacy admissions controller's tenant boundary.
 *
 * These checks deliberately inspect each public endpoint independently so a
 * future refactor cannot accidentally remove the school predicate from one
 * action while leaving the others protected. A synthetic second-school HTTP
 * fixture also exercises list/show/edit/update/delete isolation end to end.
 */
class LegacyAdmissionSchoolIsolationTest extends TestCase
{
    private function controllerSource(): string
    {
        return file_get_contents(base_path('app/Http/Controllers/Admin/AdmissionController.php'));
    }

    private function methodSource(string $method): string
    {
        $source = $this->controllerSource();
        $pattern = '/public function ' . preg_quote($method, '/') . '\\s*\\([^)]*\\)\\s*\\{(.*?)(?=\\n    public function |\\n})/s';

        $this->assertSame(1, preg_match($pattern, $source, $matches), "Could not locate {$method}() in AdmissionController.");

        return $matches[1];
    }

    public function test_list_is_scoped_to_authenticated_school_and_active_academic_year(): void
    {
        $method = $this->methodSource('admissionlist');

        $this->assertStringContainsString("Auth::user()->school_id", $method);
        $this->assertStringContainsString("where('school_id'", $method);
        $this->assertStringContainsString("where('academic_year_id'", $method);
        $this->assertStringContainsString("whereIn('application_status', ['Draft', 'Pending'])", $method);
    }

    public function test_each_record_endpoint_scopes_lookup_before_returning_or_mutating_record(): void
    {
        foreach (['show', 'edit', 'update', 'destroy'] as $action) {
            $method = $this->methodSource($action);

            $this->assertStringContainsString("where('school_id', Auth::user()->school_id)", $method, "{$action}() must constrain the record to the authenticated school.");
            $this->assertStringContainsString('firstOrFail()', $method, "{$action}() must fail closed when the record is outside the school.");
        }
    }

    public function test_show_only_offers_sections_from_the_authenticated_school(): void
    {
        $method = $this->methodSource('show');

        $this->assertStringContainsString("StandardLink::where('school_id', Auth::user()->school_id)", $method);
        $this->assertStringContainsString("where('standard_id', " . '$admission->standard_id)', $method);
    }

    public function test_legacy_admission_controller_has_no_debug_dump_or_exception_detail_response(): void
    {
        $source = $this->controllerSource();

        $this->assertDoesNotMatchRegularExpression('/\\b(dd|dump|var_dump)\\s*\\(/', $source);
        $this->assertStringNotContainsString('$e->getMessage()', $source);
        $this->assertStringNotContainsString('exception' . " => " . '$e->getMessage()', $source);
    }

    public function test_foreign_school_admission_is_not_listed_viewed_edited_updated_or_deleted(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();
        $suffix = bin2hex(random_bytes(5));
        $foreignSchoolId = DB::table('schools')->insertGetId([
            'name' => 'Legacy Admission QA School ' . $suffix,
            'email' => 'legacy-admission-' . $suffix . '@tagore-demo.local',
            'phone' => '9000000000',
            'status' => 1,
            'slug' => 'legacy-admission-' . $suffix,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $foreignYearId = DB::table('academic_years')->insertGetId([
            'school_id' => $foreignSchoolId,
            'name' => '2026-2027',
            'start_date' => '2026-06-01 00:00:00',
            'end_date' => '2027-04-30 23:59:59',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $foreignName = 'FOREIGN-ADMISSION-' . $suffix;
        $admissionId = DB::table('admissions')->insertGetId([
            'school_id' => $foreignSchoolId,
            'academic_year_id' => $foreignYearId,
            'name' => $foreignName,
            'date_of_birth' => '2012-01-01',
            'gender' => 'male',
            'height' => '140',
            'weight' => '35',
            'reason_for_leaving' => 'Synthetic security regression',
            'permanent_address' => 'Synthetic address',
            'address_for_communication' => 'Synthetic address',
            'half_yearly_mark_details' => 'Synthetic marks',
            'father_name' => 'Synthetic Father',
            'father_occupation' => 'Synthetic occupation',
            'father_mobile_no' => '9000000001',
            'mother_name' => 'Synthetic Mother',
            'emergency_contact_1' => '9000000002',
            'relation_with_student_1' => 'Father',
            'emergency_contact_2' => '9000000003',
            'relation_with_student_2' => 'Mother',
            'application_no' => 'QA-' . $suffix,
            'application_status' => 'Pending',
            'payment_status' => 'unpaid',
            'remarks' => 'Synthetic fixture only',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get('/admin/admissionlist')
            ->assertOk()
            ->assertDontSee($foreignName);

        $this->get('/admin/admission/show/' . $admissionId)->assertNotFound();
        $this->get('/admin/admission/edit/' . $admissionId)->assertNotFound();
        $this->post('/admin/admission/update/' . $admissionId, ['section_id' => 1])->assertNotFound();
        $this->delete('/admin/admission/delete/' . $admissionId)->assertNotFound();

        $this->assertDatabaseHas('admissions', [
            'id' => $admissionId,
            'school_id' => $foreignSchoolId,
            'name' => $foreignName,
            'application_status' => 'Pending',
        ]);
    }

}
