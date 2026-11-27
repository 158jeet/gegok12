<?php

namespace Tests\Feature;

use App\Models\User;
use App\Observers\UserObserver;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TagoreUserObserverTest extends TestCase
{
    public function test_non_student_user_does_not_query_student_academic_assignment(): void
    {
        Cache::shouldReceive('forget')->once()->with('parent_list1');
        Cache::shouldReceive('forget')->once()->with('standardLink1');

        $user = new User([
            'school_id' => 1,
            'usergroup_id' => User::TEACHER_USERGROUP_ID,
        ]);

        (new UserObserver())->created($user);
    }

    public function test_student_without_latest_academic_assignment_is_safe(): void
    {
        Cache::shouldReceive('forget')->once()->with('parent_list1');
        Cache::shouldReceive('forget')->once()->with('standardLink1');

        $user = new User([
            'school_id' => 1,
            'usergroup_id' => User::STUDENT_USERGROUP_ID,
        ]);
        $user->setRelation('studentAcademicLatest', null);

        (new UserObserver())->created($user);
    }

    public function test_student_with_latest_assignment_invalidates_class_caches(): void
    {
        Cache::shouldReceive('forget')->once()->with('parent_list1');
        Cache::shouldReceive('forget')->once()->with('standardLink1');
        Cache::shouldReceive('forget')->once()->with('class_students_42');
        Cache::shouldReceive('forget')->once()->with('class_student_count42');

        $user = new User([
            'school_id' => 1,
            'usergroup_id' => User::STUDENT_USERGROUP_ID,
        ]);
        $user->setRelation('studentAcademicLatest', (object) ['standardLink_id' => 42]);

        (new UserObserver())->created($user);
    }
}
