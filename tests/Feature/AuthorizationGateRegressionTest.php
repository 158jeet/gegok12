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

    public function test_legacy_status_update_rejects_cross_school_target_before_mutation(): void
    {
        $school = School::factory()->create();
        $otherSchool = School::factory()->create();
        $actor = User::factory()->for($school)->schoolAdmin()->create();
        $target = User::factory()->for($otherSchool)->create();

        $this->actingAs($actor);

        try {
            app(UserController::class)->updateStatus(
                Request::create('/admin/user/updateStatus/'.$target->name, 'POST', ['status' => 'inactive']),
                $target->name
            );
            $this->fail('A SchoolAdmin must not change a user status in another school.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame('active', $target->fresh()->status);
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
