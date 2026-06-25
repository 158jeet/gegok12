<?php
/**
 * SPDX-License-Identifier: MIT
 * (c) 2025 GegoSoft Technologies and GegoK12 Contributors
 */
namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ActivityLog;

/**
 * Class ActivityLogController
 *
 * Controller for displaying activity logs for the authenticated admin user.
 *
 * @package App\Http\Controllers\Admin
 */
class ActivityLogController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $activitylog = ActivityLog::where('causer_id',Auth::id())->orderby('id','desc')->paginate(10);
       
        return view('/admin/activity_log/show',[ 'activitylog' => $activitylog ]);
    }
}
