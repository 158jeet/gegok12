<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
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

    public function test_legacy_password_reset_and_email_verification_reject_cross_school_targets(): void
    {
        $school = School::factory()->create();
        $otherSchool = School::factory()->create();
        $actor = User::factory()->for($school)->schoolAdmin()->create();
        $target = User::factory()->for($otherSchool)->create();

        $this->actingAs($actor);

        foreach (['resetPassword', 'emailVerification'] as $method) {
            try {
                app(UserController::class)->{$method}($target->id);
                $this->fail("The {$method} endpoint must reject a target in another school.");
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
    }

    public function test_legacy_credential_lookup_returns_not_found_for_cross_school_target(): void
    {
        $school = School::factory()->create();
        $otherSchool = School::factory()->create();
        $actor = User::factory()->for($school)->schoolAdmin()->create();
        $target = User::factory()->for($otherSchool)->create();

        $this->actingAs($actor);

        try {
            app(\App\Http\Controllers\Admin\UserProfileController::class)->getCredentials($target->name);
            $this->fail('Credential lookup must not resolve a user from another school.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            $this->assertNotEmpty($exception->getModel());
        }
    }

    public function test_legacy_school_admin_cannot_change_the_protected_owner_account(): void
    {
        $school = School::factory()->create();
        $actor = User::factory()->for($school)->schoolAdmin()->create();
        $owner = User::factory()->for($school)->schoolAdmin()->create();

        $ownerRoleId = DB::table('tagore_roles')->where('code', 'OWNER')->value('id');
        if (!$ownerRoleId) {
            $ownerRoleId = DB::table('tagore_roles')->insertGetId([
                'name' => 'Owner',
                'code' => 'OWNER',
                'is_system' => true,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('tagore_user_roles')->insert([
            'user_id' => $owner->id,
            'role_id' => $ownerRoleId,
            'institution_id' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($actor);

        foreach (['resetPassword', 'emailVerification'] as $method) {
            try {
                app(UserController::class)->{$method}($owner->id);
                $this->fail("A SchoolAdmin must not run {$method} against the Owner account.");
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }

        try {
            app(UserController::class)->updateStatus(
                Request::create('/admin/user/updateStatus/'.$owner->name, 'POST', ['status' => 'inactive']),
                $owner->name
            );
            $this->fail('A SchoolAdmin must not change the Owner account status.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        try {
            app(\App\Http\Controllers\Admin\UserProfileController::class)->getCredentials($owner->name);
            $this->fail('A SchoolAdmin must not read the Owner account credentials.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_legacy_impersonation_rejects_cross_school_targets_before_session_switch(): void
    {
        $school = School::factory()->create();
        $otherSchool = School::factory()->create();
        $actor = User::factory()->for($school)->schoolAdmin()->create();

        $targets = [
            User::factory()->for($otherSchool)->create(['usergroup_id' => 5]),
            User::factory()->for($otherSchool)->create(['usergroup_id' => 8]),
            User::factory()->for($otherSchool)->create(['usergroup_id' => 6]),
            User::factory()->for($otherSchool)->create(['usergroup_id' => 3]),
        ];
        $methods = ['impersonate', 'librarianimpersonate', 'studentimpersonate', 'schoolAdminimpersonate'];

        $this->actingAs($actor);

        foreach ($methods as $index => $method) {
            try {
                app(\App\Http\Controllers\Auth\ImpersonateController::class)->{$method}($targets[$index]->id);
                $this->fail("The {$method} endpoint must reject a target in another school.");
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }

        $this->assertAuthenticatedAs($actor);
    }

    public function test_legacy_impersonation_never_allows_the_owner_account_as_a_target(): void
    {
        $school = School::factory()->create();
        $actor = User::factory()->for($school)->schoolAdmin()->create();
        $owner = User::factory()->for($school)->schoolAdmin()->create();

        $ownerRoleId = DB::table('tagore_roles')->where('code', 'OWNER')->value('id');
        if (!$ownerRoleId) {
            $ownerRoleId = DB::table('tagore_roles')->insertGetId([
                'name' => 'Owner',
                'code' => 'OWNER',
                'is_system' => true,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('tagore_user_roles')->insert([
            'user_id' => $owner->id,
            'role_id' => $ownerRoleId,
            'institution_id' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($actor);

        try {
            app(\App\Http\Controllers\Auth\ImpersonateController::class)->impersonate($owner->id);
            $this->fail('The Owner account must never be impersonated.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertAuthenticatedAs($actor);
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
