<?php

namespace Tests\\Feature;

use App\\Models\\User;
use Illuminate\\Support\\Facades\\DB;
use Tests\\TestCase;

class TagoreCashierAuthorizationTest extends TestCase
{
    public function test_cashier_can_open_institution_accounts_but_cannot_reconcile_or_change_fee_masters(): void
    {
        $cashier = User::query()->where('email', 'cashier@tagore-demo.local')->firstOrFail();

        $this->actingAs($cashier)->get(route('tagore.fees.accounts'))->assertOk();
        $this->post(route('tagore.fees.reconcile', 999999), [
            'settlement_amount' => 0,
            'settlement_date' => today()->toDateString(),
        ])->assertForbidden();
        $this->get(route('tagore.fees.manage'))->assertForbidden();
        $this->get(route('tagore.fees.editor'))->assertForbidden();
    }

    public function test_cashier_cannot_view_or_collect_for_a_student_in_another_institution(): void
    {
        $cashier = User::query()->where('email', 'cashier@tagore-demo.local')->firstOrFail();
        $institution = DB::table('tagore_institutions')
            ->where('school_id', $cashier->school_id)
            ->where('status', 'active')
            ->first(['id', 'tagore_group_id']);
        $this->assertNotNull($institution);

        $suffix = bin2hex(random_bytes(5));
        $foreignSchoolId = DB::table('schools')->insertGetId([
            'name' => 'Cashier QA Foreign School ' . $suffix,
            'email' => 'cashier-foreign-' . $suffix . '@tagore-demo.local',
            'status' => 1,
            'slug' => 'cashier-foreign-' . $suffix,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tagore_institutions')->insert([
            'tagore_group_id' => $institution->tagore_group_id,
            'school_id' => $foreignSchoolId,
            'code' => 'CASHIER-QA-' . strtoupper($suffix),
            'display_name' => 'Cashier QA Foreign Institution',
            'type' => 'school',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $foreignStudent = User::factory()->create([
            'school_id' => $foreignSchoolId,
            'usergroup_id' => 6,
            'name' => 'Cashier QA Foreign Student',
            'email' => 'cashier-foreign-student-' . $suffix . '@tagore-demo.local',
        ]);

        $this->actingAs($cashier)
            ->get(route('tagore.fees.student', $foreignStudent->id))
            ->assertForbidden();
        $this->post(route('tagore.fees.payment', $foreignStudent->id), [
            'amount' => 100,
            'payment_mode' => 'cash',
        ])->assertForbidden();

        $this->assertDatabaseMissing('tagore_payments', [
            'student_id' => $foreignStudent->id,
        ]);
    }
}
