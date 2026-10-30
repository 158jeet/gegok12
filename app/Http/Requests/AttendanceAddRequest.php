<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Helpers\SiteHelper;
use App\Models\Attendance;
use App\Models\AbsentReason;
use App\Models\StandardLink;
use App\Models\StudentAcademic;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceAddRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        Validator::extend('check_date',function($attribute,$value,$parameters,$validator)
        {   
            $academic_year  = SiteHelper::getAcademicYear(Auth::user()->school_id);
            $start_date = date('Y-m-d',strtotime($academic_year->start_date));
            $end_date = $academic_year->end_date ? date('Y-m-d',strtotime($academic_year->end_date)) : date('Y-m-d');

            if( ( request('date') <= date('Y-m-d') ) && ( request('date') >= $start_date ) && ( request('date') <= $end_date )
            {
                return true;
            }
                
            return false;
        });

        Validator::extend('check_session',function($attribute,$value,$parameters,$validator)
        {   
            $academic_year  = SiteHelper::getAcademicYear(Auth::user()->school_id);
            $date = date('Y-m-d',strtotime(request('date')));
            $standardLink_id = (int)request('standardLink_id');
            $attendance = Attendance::where([
                ['school_id',Auth::user()->school_id],
                ['academic_year_id',$academic_year->id],
                ['date','=',$date],
                ['session',request('session')],
                ['standardLink_id',$standardLink_id]
            ])->exists();
            
            if($attendance)
            {
                return false;
            }
            return true;
        });

        Validator::extend('check_user',function($attribute,$value,$parameters,$validator)
        { 
          $count=0;
          for($i=0 ; $i < Request('absentCount') ; $i++)
          { 
            if($value==request('user_id'.$i))
            {
              $count++;
            }
          }
          if($count<=1)
          {
            return true;
          }
          return false;
        });


        $rules =
        [
            //
            'standardLink_id'   => 'required|integer',
            'date'              => 'required|date|check_date',
            'session'           => 'required|in:forenoon,afternoon|check_session',
            'absentCount'       => 'required|integer|min:0|max:500',
            'presentCount'      => 'required|integer|min:0|max:500',
        ];

        for($i=0;$i<Request('absentCount');$i++)
        {   
            Validator::extend('check_remarks',function($attribute,$value,$parameters,$validator)
            { 
                return preg_match('/^[A-Za-z_~\-!@#\$%\^&*.,:(\)\s]+$/', $value);
            });

            $rules['user_id'.$i]    = 'required|check_user';
            $rules['reason_id'.$i]  = 'required';
            $rules['remarks'.$i]    = 'nullable|max:20|check_remarks';
        }
        for($i=0;$i<Request('presentCount');$i++)
        {
            $rules['present_id'.$i] = 'required|integer';
        }

        return $rules;
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            $schoolId = (int) $user->school_id;
            $academicYear = SiteHelper::getAcademicYear($schoolId);
            if (!$academicYear) {
                $validator->errors()->add('standardLink_id', 'No active academic year is configured for this school.');
                return;
            }

            $standardLinkId = (int) $this->input('standardLink_id');
            $standardLink = StandardLink::query()
                ->where('id', $standardLinkId)
                ->where('school_id', $schoolId)
                ->where('academic_year_id', $academicYear->id)
                ->first();
            if (!$standardLink) {
                $validator->errors()->add('standardLink_id', 'Select a class or section belonging to your active school year.');
                return;
            }

            if ((int) $user->usergroup_id === 5) {
                $assigned = DB::table('class_teacher_links')
                    ->where('school_id', $schoolId)
                    ->where('teacher_id', $user->id)
                    ->where('standardLink_id', $standardLinkId)
                    ->exists() || (int) ($standardLink->class_teacher_id ?? 0) === (int) $user->id;
                if (!$assigned) {
                    $validator->errors()->add('standardLink_id', 'You can record attendance only for a class assigned to you.');
                    return;
                }
            }

            $absentIds = [];
            for ($i = 0; $i < (int) $this->input('absentCount'); $i++) {
                $absentIds[] = (int) $this->input('user_id'.$i);
            }
            $presentIds = [];
            for ($i = 0; $i < (int) $this->input('presentCount'); $i++) {
                $presentIds[] = (int) $this->input('present_id'.$i);
            }
            $allIds = array_merge($absentIds, $presentIds);

            if (!$allIds) {
                $validator->errors()->add('presentCount', 'Select at least one student before saving attendance.');
                return;
            }
            if (count($allIds) !== count(array_unique($allIds))) {
                $validator->errors()->add('presentCount', 'A student can appear only once in an attendance session.');
                return;
            }

            $enrolledIds = StudentAcademic::query()
                ->where('school_id', $schoolId)
                ->where('academic_year_id', $academicYear->id)
                ->where('standardLink_id', $standardLinkId)
                ->whereIn('user_id', $allIds)
                ->distinct()
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            if (count($enrolledIds) !== count($allIds)) {
                $validator->errors()->add('standardLink_id', 'Attendance can be recorded only for students enrolled in the selected class and academic year.');
                return;
            }

            if ($absentIds) {
                $reasonIds = [];
                for ($i = 0; $i < (int) $this->input('absentCount'); $i++) {
                    $reasonIds[] = (int) $this->input('reason_id'.$i);
                }
                $activeReasonCount = AbsentReason::query()->where('status', 1)->whereIn('id', $reasonIds)->distinct()->count('id');
                if ($activeReasonCount !== count(array_unique($reasonIds))) {
                    $validator->errors()->add('reason_id0', 'Select a valid active absence reason for each absent student.');
                }
            }
        });
    }

    public function messages()
    {
        $messages = 
        [
            //
            'standardLink_id.required'  =>  'Class is required',
            'date.required'             =>  'Date is required',
            'date.check_date'           =>  'Enter valid Date',
            'session.required'          =>  'Session is required',
            'session.check_session'     =>  'Attendance already updated',
        ];

        for($i=0 ; $i < Request('absentCount') ; $i++)
        {
            $messages['user_id'.$i.'.required']         = 'Student is required';
            $messages['user_id'.$i.'.check_user']       = 'Student is already exists';
            $messages['reason_id'.$i.'.required']       = 'Reason is required';
            $messages['remarks'.$i.'.check_remarks']    = 'Enter Valid Remarks';
            $messages['remarks'.$i.'.max']              = 'Remarks may not be greater than 20 letters';
        }

        return $messages;
    }
}