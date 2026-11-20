<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;
use App\Models\User;

class StudentAcademicTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $students = User::where('usergroup_id',6)->get();

       	foreach($students as $student) 
        {
        	factory(\App\Models\StudentAcademic::class, 1)->create([
                'school_id'     =>  $student->school_id,
                
                'user_id'       =>  $student->id, 
            ]);
		}
    }
}
