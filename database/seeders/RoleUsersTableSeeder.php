<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\RoleUser;
use App\Models\Users\TeacherUser;

class RoleUsersTableSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 *
	 * @return void
	 */
	public function run()
	{
		//
		$teachers = TeacherUser::where('usergroup_id', 5)->get();

		foreach ($teachers as $teacher) {
			$designation = $teacher->getTeacherDetails()['designation'];
			if (($designation == 'principal') || ($designation == 'vice_principal') || ($designation == 'head_of_the_department')) {
				RoleUser::factory(1)->create([
					'user_id'	=>  $teacher->id,
					'role_id'   =>  2,
				]);
			} else {
				RoleUser::factory(1)->create([
					'user_id'   =>  $teacher->id,
					'role_id'   =>  1,
				]);
			}
		}
	}
}
