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
            ->get(['id', 'school_id', 'tagore_group_id']);
        $selectedInstitution = $institutions->firstWhere('school_id', $owner->school_id);
        $foreignInstitution = $institutions->first(fn ($institution) => (int) $institution->school_id !== (int) $owner->school_id);

        $this->assertNotNull($selectedInstitution, 'The Owner fixture must have an active institution.');

        if (!$foreignInstitution) {
            $suffix = bin2hex(random_bytes(5));
            $foreignSchoolId = DB::table('schools')->insertGetId([
                'name' => 'Gatepass QA School ' . $suffix,
                'email' => 'gatepass-school-' . $suffix . '@tagore-demo.local',
                'status' => 1,
                'slug' => 'gatepass-qa-' . $suffix,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $foreignInstitutionId = DB::table('tagore_institutions')->insertGetId([
                'tagore_group_id' => $selectedInstitution->tagore_group_id,
                'school_id' => $foreignSchoolId,
                'code' => 'GATEPASS-QA-' . strtoupper($suffix),
                'display_name' => 'Gatepass QA Institution',
                'type' => 'school',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $foreignInstitution = (object) [
                'id' => $foreignInstitutionId,
                'school_id' => $foreignSchoolId,
            ];
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

    public function test_approved_gatepass_is_single_use_and_public_response_does_not_disclose_token_hash(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $institution = DB::table('tagore_institutions')->where('school_id', $owner->school_id)->where('status', 'active')->first(['id', 'school_id']);
        $student = User::factory()->create([
            'school_id' => $institution->school_id,
            'usergroup_id' => 6,
            'name' => 'Single Use Gatepass QA Student',
            'email' => 'single-gatepass-' . bin2hex(random_bytes(5)) . '@tagore-demo.local',
        ]);

        $created = $this->actingAs($owner)->post(route('tagore.security.gatepass'), [
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'pass_type' => 'early-leave',
            'reason' => 'Single-use verification acceptance',
            'valid_from' => now()->subMinute()->toDateTimeString(),
            'valid_until' => now()->addHour()->toDateTimeString(),
        ])->assertRedirect();

        $message = (string) $created->getSession()->get('success');
        preg_match('/Token: ([A-Za-z0-9]+)/', $message, $matches);
        $this->assertNotEmpty($matches[1] ?? null, 'The newly issued gatepass token must be available once to the issuing operator.');
        $token = $matches[1];
        $pass = DB::table('tagore_security_gatepasses')->where('token_hash', hash('sha256', $token))->first();
        $this->assertNotNull($pass);

        $this->actingAs($owner)->patch(route('tagore.security.gatepass.decide', $pass->id), [
            'decision' => 'approved',
        ])->assertRedirect();

        auth()->logout();
        $this->get(route('tagore.security.gatepass.verify', $token))
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('pass.status', 'used')
            ->assertJsonMissingPath('pass.token_hash');

        $this->assertTrue(DB::table('tagore_security_gatepasses')->where('id', $pass->id)->whereNotNull('verified_at')->exists());
        $this->assertDatabaseHas('tagore_security_events', [
            'institution_id' => $institution->id,
            'actor_user_id' => null,
            'event_type' => 'GATEPASS_VERIFIED',
            'subject_id' => $pass->id,
        ]);

        $this->get(route('tagore.security.gatepass.verify', $token))
            ->assertOk()
            ->assertJsonPath('valid', false);
    }

    public function test_visitor_host_must_belong_to_the_selected_institution(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $institution = DB::table('tagore_institutions')->where('school_id', $owner->school_id)->where('status', 'active')->first(['id', 'tagore_group_id']);
        $suffix = bin2hex(random_bytes(5));
        $foreignSchoolId = DB::table('schools')->insertGetId([
            'name' => 'Visitor Host QA School ' . $suffix,
            'email' => 'visitor-host-' . $suffix . '@tagore-demo.local',
            'status' => 1,
            'slug' => 'visitor-host-' . $suffix,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tagore_institutions')->insert([
            'tagore_group_id' => $institution->tagore_group_id,
            'school_id' => $foreignSchoolId,
            'code' => 'VISITOR-HOST-' . strtoupper($suffix),
            'display_name' => 'Visitor Host QA Institution',
            'type' => 'school',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $foreignHost = User::factory()->create([
            'school_id' => $foreignSchoolId,
            'usergroup_id' => 5,
            'status' => 'active',
            'name' => 'Foreign Visitor Host',
            'email' => 'foreign-host-' . $suffix . '@tagore-demo.local',
        ]);

        $this->actingAs($owner)->post(route('tagore.security.visitor'), [
            'institution_id' => $institution->id,
            'visitor_name' => 'Synthetic Visitor',
            'phone' => '+919999999999',
            'purpose' => 'Institution scope test',
            'host_user_id' => $foreignHost->id,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('tagore_security_visitors', [
            'institution_id' => $institution->id,
            'host_user_id' => $foreignHost->id,
        ]);
    }
}
