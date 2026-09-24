<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('e2e')]
class TagorePrototypeE2ETest extends TestCase
{
    private function userByGroup(int $groupId): User
    {
        $user = User::query()
            ->where('usergroup_id', $groupId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->first();

        $this->assertNotNull($user, "Expected a seeded user in group {$groupId}.");

        return $user;
    }

    public function test_seeded_owner_can_access_tagore_dashboard_and_administration(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();

        $this->assertDatabaseHas('tagore_user_roles', ['user_id' => $owner->id, 'status' => 'active']);
        $this->assertDatabaseHas('tagore_user_roles', [
            'user_id' => $owner->id,
            'role_id' => DB::table('tagore_roles')->where('code', 'OWNER')->value('id'),
            'status' => 'active',
        ]);

        $this->actingAs($owner)->get(route('tagore.dashboard'))
            ->assertOk()->assertSee('TagoreK12')->assertSee('Demo School');

        $this->actingAs($owner)->get(route('tagore.admin'))
            ->assertOk()->assertSee('Tagore Administration')->assertSee('TAGORE-1');
    }

    public function test_non_owner_cannot_access_owner_administration(): void
    {
        $teacher = $this->userByGroup(5);

        $this->actingAs($teacher)->get(route('tagore.admin'))->assertForbidden();
    }

    public function test_teacher_dashboard_is_institution_scoped(): void
    {
        $teacher = $this->userByGroup(5);

        $this->assertDatabaseHas('tagore_user_roles', [
            'user_id' => $teacher->id,
            'role_id' => DB::table('tagore_roles')->where('code', 'TEACHER')->value('id'),
            'institution_id' => DB::table('tagore_institutions')->where('school_id', $teacher->school_id)->value('id'),
            'status' => 'active',
        ]);

        $this->actingAs($teacher)->get(route('tagore.dashboard'))
            ->assertOk()->assertSee('TagoreK12')->assertSee('Demo School');
    }

    public function test_parent_can_see_only_linked_children_and_phase_one_data(): void
    {
        $parent = $this->userByGroup(7);

        $childId = DB::table('tagore_parent_students')
            ->where('parent_user_id', $parent->id)
            ->where('status', 'active')
            ->value('student_id');

        $this->assertNotNull($childId, 'Expected the seeded parent to have a linked child.');

        $this->actingAs($parent)->get(route('tagore.parent.dashboard'))
            ->assertOk()->assertSee('Fee outstanding')->assertSee('Attendance')->assertSee('Latest result');

        $this->actingAs($parent)->get(route('tagore.child', ['studentId' => $childId]))->assertOk();
        $this->actingAs($parent)->get(route('tagore.fees.student', ['studentId' => $childId]))->assertOk();

        $otherStudentId = DB::table('users')
            ->where('usergroup_id', 6)
            ->where('id', '!=', $childId)
            ->whereNotExists(function ($query) use ($parent) {
                $query->select(DB::raw(1))
                    ->from('tagore_parent_students as ps')
                    ->whereColumn('ps.student_id', 'users.id')
                    ->where('ps.parent_user_id', $parent->id)
                    ->where('ps.status', 'active');
            })
            ->value('id');

        if ($otherStudentId) {
            $this->actingAs($parent)
                ->get(route('tagore.child', ['studentId' => $otherStudentId]))
                ->assertForbidden();
        }
    }

    public function test_owner_can_view_accounts_and_seeded_fee_data_exists(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();

        $this->assertGreaterThan(
            0,
            DB::table('tagore_fee_obligations')->where('institution_id', DB::table('tagore_institutions')->value('id'))->count()
        );

        $this->actingAs($owner)->get(route('tagore.fees.accounts'))
            ->assertOk()->assertSee('Fee');
    }

    public function test_owner_cannot_create_duplicate_institution_for_same_gegok12_school(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();
        $schoolId = DB::table('tagore_institutions')->where('code', 'TAGORE-1')->value('school_id');

        $this->actingAs($owner)->post(route('tagore.admin.institution'), [
            'school_id' => $schoolId,
            'code' => 'DUPLICATE',
            'display_name' => 'Duplicate Institution',
            'type' => 'school',
        ])->assertStatus(422);

        $this->assertSame(1, DB::table('tagore_institutions')->where('school_id', $schoolId)->count());
    }
}
