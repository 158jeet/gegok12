<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationGateRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_gate_restricts_legacy_user_targets_to_the_same_school(): void
    {
        $school = School::factory()->create();
        $otherSchool = School::factory()->create();
        $actor = User::factory()->for($school)->schoolAdmin()->create();
        $sameSchoolMember = User::factory()->for($school)->create();
        $otherSchoolMember = User::factory()->for($otherSchool)->create();

        $this->assertTrue(Gate::forUser($actor)->allows('member', $sameSchoolMember));
        $this->assertFalse(Gate::forUser($actor)->allows('member', $otherSchoolMember));
    }

    public function test_post_reply_gate_checks_the_parent_post_school(): void
    {
        $school = School::factory()->create();
        $otherSchool = School::factory()->create();
        $user = User::factory()->for($school)->create();

        $sameSchoolReply = (object) ['post' => (object) ['school_id' => $school->id]];
        $otherSchoolReply = (object) ['post' => (object) ['school_id' => $otherSchool->id]];

        $this->assertTrue(Gate::forUser($user)->allows('post_reply', $sameSchoolReply));
        $this->assertFalse(Gate::forUser($user)->allows('post_reply', $otherSchoolReply));
    }
}
