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
