<?php
/**
 * SPDX-License-Identifier: MIT
 * (c) 2025 GegoSoft Technologies and GegoK12 Contributors
 */
namespace App\Http\Controllers\Receptionist;

use App\Http\Resources\WorkAnniversary as WorkAnniversaryResource;
use App\Http\Resources\Birthday as BirthdayResource;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Smstemplate;
use App\Models\Userprofile;
use App\Models\User;

class BirthdayController extends Controller
{
    /**
     * Return today's student birthdays as a collection resource.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function showBirthday()
    {
        $birthday = Userprofile::with('user')
            ->whereRaw("DATE_FORMAT(date_of_birth, '%m-%d') = DATE_FORMAT(now(),'%m-%d')")
            ->where('school_id', Auth::user()->school_id)
            ->ByRole(6)
            ->get();

        $users = BirthdayResource::collection($birthday);

        return $users;
    }

    /**
     * Return birthday list and available SMS templates for students.
     *
     * @return array{
     *     birthdaylist: \Illuminate\Database\Eloquent\Collection,
     *     templatelist: \Illuminate\Database\Eloquent\Collection
     * }
     */
    public function birthdayUser()
    {
        $birthday = Userprofile::with('user')
            ->whereRaw("DATE_FORMAT(date_of_birth, '%m-%d') = DATE_FORMAT(now(),'%m-%d')")
            ->where('school_id', Auth::user()->school_id)
            ->ByRole(6)
            ->get();

        $templates = Smstemplate::where('name', 'birthday_message')->get();

        return [
            'birthdaylist' => $birthday,
            'templatelist' => $templates,
        ];
    }

    /**
     * Show the receptionist birthday dashboard view.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function birthday()
    {
        return view('/receptionist/dashboard/birthday');
    }

    /**
     * Return today's teacher birthdays as a collection resource.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function showBirthdayTeacher()
    {
        $birthday = Userprofile::with('user')
            ->whereRaw("DATE_FORMAT(date_of_birth, '%m-%d') = DATE_FORMAT(now(),'%m-%d')")
            ->where('school_id', Auth::user()->school_id)
            ->ByRole(5)
            ->get();

        $users = BirthdayResource::collection($birthday);

        return $users;
    }

    /**
     * Return teacher birthday list and available SMS templates.
     *
     * @return array{
     *     birthdaylist: \Illuminate\Database\Eloquent\Collection,
     *     templatelist: \Illuminate\Database\Eloquent\Collection
     * }
     */
    public function birthdayTeacher()
    {
        $birthday = Userprofile::with('user')
            ->whereRaw("DATE_FORMAT(date_of_birth, '%m-%d') = DATE_FORMAT(now(),'%m-%d')")
            ->where('school_id', Auth::user()->school_id)
            ->ByRole(5)
            ->get();

        $templates = Smstemplate::where('name', 'birthday_message')->get();

        return [
            'birthdaylist' => $birthday,
            'templatelist' => $templates,
        ];
    }

    /**
     * Show the receptionist teacher birthday creation view.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function birthdayCreate()
    {
        return view('/receptionist/dashboard/birthdayTeacher');
    }

    /**
     * Return today's work anniversaries as a collection resource.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function showWorkAnniversary()
    {
        $workanniversary = Userprofile::with('user')
            ->whereRaw("DATE_FORMAT(joining_date, '%m-%d') = DATE_FORMAT(now(),'%m-%d')")
            ->where('school_id', Auth::user()->school_id)
            ->ByRole(5)
            ->get();

        $workanniversary = WorkAnniversaryResource::collection($workanniversary);

        return $workanniversary;
    }

    /**
     * Return work anniversary list and templates for display.
     *
     * @return array{
     *     workanniversarylist: \Illuminate\Database\Eloquent\Collection,
     *     templatelist: \Illuminate\Database\Eloquent\Collection
     * }
     */
    public function workAnniversary()
    {
        $workanniversary = Userprofile::with('user')
            ->whereRaw("DATE_FORMAT(joining_date, '%m-%d') = DATE_FORMAT(now(),'%m-%d')")
            ->where('school_id', Auth::user()->school_id)
            ->ByRole(5)
            ->get();

        $templates = Smstemplate::where('name', 'work_anniversary_message')->get();

        return [
            'workanniversarylist' => $workanniversary,
            'templatelist' => $templates,
        ];
    }

    /**
     * Show the receptionist work anniversary view.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function workAnniversaryCreate()
    {
        return view('/receptionist/dashboard/workAnniversary');
    }
}
