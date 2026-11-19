<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TagoreGatepassInstitutionScopeTest extends TestCase
{
    public function test_gatepass_rejects_a_student_from_another_institution(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $institutions = DB::table('tagore_institutions')
            ->where('status', 'active')
            ->orderBy('id')
            ->get(['id', 'school_id']);
        $selectedInstitution = $institutions->firstWhere('school_id', $owner->school_id);
        $foreignInstitution = $institutions->first(fn ($institution) => (int) $institution->school_id !== (int) $owner->school_id);

        if (!$selectedInstitution || !$foreignInstitution) {
            $this->markTestSkipped('The synthetic fixture needs two active institutions to test cross-institution rejection.');
        }

        $foreignStudent = User::factory()->create([
            'school_id' => $foreignInstitution->school_id,
            'usergroup_id' => 6,
            'name' => 'Foreign Institution QA Student',
            'email' => 'foreign-gatepass-' . bin2hex(random_bytes(5)) . '@tagore-demo.local',
        ]);

        $response = $this->actingAs($owner)->post(route('tagore.security.gatepass'), [
            'institution_id' => $selectedInstitution->id,
            'student_id' => $foreignStudent->id,
            'pass_type' => 'early-leave',
            'reason' => 'Synthetic cross-institution security test',
            'valid_from' => now()->toDateTimeString(),
            'valid_until' => now()->addHour()->toDateTimeString(),
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('tagore_security_gatepasses', [
            'institution_id' => $selectedInstitution->id,
            'student_id' => $foreignStudent->id,
        ]);
    }

    public function test_gatepass_accepts_a_student_belonging_to_the_selected_institution(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $institution = DB::table('tagore_institutions')
            ->where('school_id', $owner->school_id)
            ->where('status', 'active')
            ->first(['id', 'school_id']);
        $this->assertNotNull($institution);

        $student = User::factory()->create([
            'school_id' => $institution->school_id,
            'usergroup_id' => 6,
            'name' => 'Same Institution QA Student',
            'email' => 'same-gatepass-' . bin2hex(random_bytes(5)) . '@tagore-demo.local',
        ]);

        $response = $this->actingAs($owner)->post(route('tagore.security.gatepass'), [
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'pass_type' => 'early-leave',
            'reason' => 'Synthetic same-institution acceptance test',
            'valid_from' => now()->toDateTimeString(),
            'valid_until' => now()->addHour()->toDateTimeString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tagore_security_gatepasses', [
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'status' => 'pending',
        ]);
    }
}
