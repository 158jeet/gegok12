<?php

namespace App\Observers;

use Illuminate\Support\Facades\Cache;
use App\Helpers\SiteHelper;
use App\Models\User;

class UserObserver
{
    /**
     * Handle the user "created" event.
     *
     * @param  \App\Models\User  $user
     * @return void
     */
    public function created(User $user)
    {
        $this->forgetUserCaches($user);
    }

    /**
     * Handle the user "updated" event.
     *
     * @param  \App\Models\User  $user
     * @return void
     */
    public function updated(User $user)
    {
        $this->forgetUserCaches($user);
    }

    /**
     * Clear school caches for every user, and class caches only for students
     * that actually have a latest academic assignment.
     */
    private function forgetUserCaches(User $user): void
    {
        Cache::forget('parent_list'.$user->school_id);
        Cache::forget('standardLink'.$user->school_id);

        if ((int) $user->usergroup_id !== User::STUDENT_USERGROUP_ID) {
            return;
        }

        $standardLinkId = $user->studentAcademicLatest?->standardLink_id;

        if (!$standardLinkId) {
            return;
        }

        Cache::forget('class_students_'.$standardLinkId);
        Cache::forget('class_student_count'.$standardLinkId);
    }

    /**
     * Handle the user "deleted" event.
     *
     * @param  \App\Models\User  $user
     * @return void
     */
    public function deleted(User $user)
    {
        //
    }

    /**
     * Handle the user "restored" event.
     *
     * @param  \App\Models\User  $user
     * @return void
     */
    public function restored(User $user)
    {
        //
    }

    /**
     * Handle the standard link "force deleted" event.
     *
     * @param  \App\Models\User  $user
     * @return void
     */
    public function forceDeleted(User $user)
    {
        //
    }
}