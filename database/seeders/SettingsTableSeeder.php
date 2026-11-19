<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;

class SettingsTableSeeder extends Seeder
{
    public function run()
    {
        $now = now();

        $settings = [
            ['key'=>'sitetitle','name'=>'Site Title','description'=>'Site Title to show in Browser Bar','value'=>'School-Plus','field'=>'{"name":"value","label":"Value", "title":"Site Title" ,"type":"text"}','active'=>1],
            ['key'=>'sitename','name'=>'Site Name','description'=>'This site name is used in emails and copyrights','value'=>'School-Plus','field'=>'{"name":"value","label":"Value", "title":"Site Title" ,"type":"text"}','active'=>1],
            ['key'=>'sitelogo','name'=>'Site Logo','description'=>'Logo of the website. Recommended Size : 220px (w) x 45px (h)','value'=>'images/logo.png','field'=>'{"name":"value","label":"Value" ,"type":"browse"}','active'=>1],
            ['key'=>'favicon','name'=>'Favicon','description'=>'Site Favicon','value'=>'images/favicon.png','field'=>'{"name":"value","label":"Value", "title":"Site Favicon" ,"type":"browse", "disk":"uploads"}','active'=>1],
            ['key'=>'maintenance','name'=>'Maintenance','description'=>'Maintenance','value'=>0,'field'=>'{"name":"value","label":"Maintenance" ,"type":"radio", "options":{"1":"Active", "0":"Inactive"}}','active'=>1],
            ['key'=>'login_status','name'=>'login','description'=>'login','value'=>1,'field'=>'{"name":"value","label":"Userlogin" ,"type":"radio", "options":{"1":"Active", "0":"Inactive"}}','active'=>1],
            ['key'=>'register_status','name'=>'Register Status','description'=>'Register Status','value'=>1,'field'=>'{"name":"value","label":"Register Status" ,"type":"radio", "options":{"1":"Active", "0":"Inactive"}}','active'=>1],
            ['key'=>'assignment_status','name'=>'Assignment Status','description'=>'Assignment Status','value'=>0,'field'=>'{"name":"value","label":"Register Status" ,"type":"radio", "options":{"1":"Active", "0":"Inactive"}}','active'=>1],
            ['key'=>'homework_status','name'=>'Homework Status','description'=>'Homework Status','value'=>0,'field'=>'{"name":"value","label":"Register Status" ,"type":"radio", "options":{"1":"Active", "0":"Inactive"}}','active'=>1],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                $setting + ['created_at' => $now, 'updated_at' => $now]
            );
        }
    }
}
