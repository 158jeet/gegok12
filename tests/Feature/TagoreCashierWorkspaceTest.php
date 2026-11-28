<?php

namespace Tests\Feature;

use App\Events\SinglePushEvent;
use App\Models\User;
use App\Services\Tagore\FeeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TagoreCashierWorkspaceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) DB::rollBack();
        parent::tearDown();
    }

    public function test_cashier_has_a_dedicated_collection_workspace_and_scoped_csv_register(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $cashier = User::query()->where('email', 'cashier@tagore-demo.local')->firstOrFail();
        $institution = DB::table('tagore_institutions')->where('school_id', $cashier->school_id)->where('status', 'active')->firstOrFail();
        $student = User::query()->where('email', 'student@tagore-demo.local')->where('school_id', $institution->school_id)->firstOrFail();

        app(FeeService::class)->createObligation((int) $student->id, (int) $institution->id, [
            'due_date' => now()->addDay()->toDateString(),
            'frequency' => 'one_time',
            'gross_amount' => 100,
            'items' => [['fee_head' => 'Cashier desk QA', 'code' => 'QA-CASHIER-DESK', 'gross_amount' => 100]],
        ], (int) $owner->id);

        $paymentId = app(FeeService::class)->recordOfflinePayment(
            (int) $student->id, (int) $institution->id, 40, null, (int) $cashier->id,
            'QA-CASHIER-DESK-REFERENCE', [], 'upi'
        );
        $receipt = DB::table('tagore_payments')->where('id', $paymentId)->value('receipt_no');

        app(FeeService::class)->createObligation((int) $student->id, (int) $institution->id, [
            'due_date' => now()->addDay()->toDateString(),
            'frequency' => 'one_time',
            'gross_amount' => 100,
            'items' => [['fee_head' => 'Owner-only QA', 'code' => 'QA-OWNER-COLLECTION', 'gross_amount' => 100]],
        ], (int) $owner->id);
        app(FeeService::class)->recordOfflinePayment(
            (int) $student->id, (int) $institution->id, 25, null, (int) $owner->id,
            'QA-OWNER-ONLY-REFERENCE', [], 'cash'
        );

        $this->actingAs($cashier)
            ->get(route('tagore.cashier.index', ['from' => now()->toDateString(), 'to' => now()->toDateString()]))
            ->assertOk()
            ->assertSee('Cashier desk')
            ->assertSee('Quick collect')
            ->assertSee('Collection register')
            ->assertSee('QA-CASHIER-DESK-REFERENCE')
            ->assertDontSee('QA-OWNER-ONLY-REFERENCE')
            ->assertSee('UPI');

        $export = $this->actingAs($cashier)->get(route('tagore.cashier.export', [
            'from' => now()->toDateString(), 'to' => now()->toDateString(),
        ]));
        $export->assertOk();
        $this->assertStringContainsString('text/csv', $export->headers->get('content-type'));
        $this->assertStringContainsString((string) $receipt, $export->streamedContent());
        $this->assertStringContainsString('QA-CASHIER-DESK-REFERENCE', $export->streamedContent());
        $this->assertStringNotContainsString('QA-OWNER-ONLY-REFERENCE', $export->streamedContent());
    }

    public function test_cashier_can_assign_an_approved_fee_structure_and_generate_demands_within_its_institution(): void
    {
        $cashier = User::query()->where('email', 'cashier@tagore-demo.local')->firstOrFail();
        $institution = DB::table('tagore_institutions')->where('school_id', $cashier->school_id)->where('status', 'active')->firstOrFail();
        $student = User::query()->where('email', 'student@tagore-demo.local')->where('school_id', $institution->school_id)->firstOrFail();
        $academic = DB::table('student_academics')->where('user_id', $student->id)
            ->where('school_id', $institution->school_id)->whereNotNull('standardLink_id')->first();
        $this->assertNotNull($academic, 'The demo student must have an active class/section for cashier assignment acceptance.');

        $structureId = DB::table('tagore_fee_structures')->insertGetId([
            'institution_id' => $institution->id,
            'academic_year_id' => $academic->academic_year_id,
            'name' => 'Cashier Assignment QA ' . bin2hex(random_bytes(3)),
            'description' => 'Synthetic acceptance-only fee structure.',
            'frequency' => 'one_time',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tagore_fee_components')->insert([
            'fee_structure_id' => $structureId,
            'name' => 'Acceptance Tuition',
            'code' => 'QA-CASHIER-ASSIGN',
            'category' => 'tuition',
            'amount' => 1250,
            'tax_rate' => 0,
            'is_optional' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($cashier)->get(route('tagore.cashier.fees'))
            ->assertOk()->assertSee('Fee assignments')->assertSee('Assign an approved fee structure');

        $this->actingAs($cashier)->post(route('tagore.cashier.fees.assign'), [
            'fee_structure_id' => $structureId,
            'standard_link_id' => $academic->standardLink_id,
        ])->assertRedirect()->assertSessionHas('success');

        $assignment = DB::table('tagore_fee_structure_assignments')
            ->where('fee_structure_id', $structureId)
            ->where('standard_link_id', $academic->standardLink_id)
            ->first();
        $this->assertNotNull($assignment);

        $this->actingAs($cashier)->post(route('tagore.cashier.fees.preview', $assignment->id))
            ->assertRedirect(route('tagore.cashier.fees'))
            ->assertSessionHas('cashier_bulk_preview_' . $assignment->id);

        $this->actingAs($cashier)->post(route('tagore.cashier.fees.generate', $assignment->id), [
            'due_date' => now()->addDays(30)->toDateString(),
        ])->assertRedirect(route('tagore.cashier.fees'))->assertSessionHas('success');

        $this->assertDatabaseHas('tagore_fee_obligations', [
            'student_id' => $student->id,
            'institution_id' => $institution->id,
            'fee_structure_id' => $structureId,
            'net_amount' => 1250,
        ]);
        $this->actingAs($cashier)->get(route('tagore.fees.manage'))->assertForbidden();
    }

    public function test_cashier_workspace_does_not_grant_finance_master_or_reversal_access(): void
    {
        $cashier = User::query()->where('email', 'cashier@tagore-demo.local')->firstOrFail();

        $this->actingAs($cashier)->get(route('tagore.cashier.index'))->assertOk();
        $this->actingAs($cashier)->get(route('tagore.fees.manage'))->assertForbidden();
        $this->actingAs($cashier)->get(route('tagore.fees.editor'))->assertForbidden();
        $this->actingAs($cashier)->post(route('tagore.fees.wallet.credit'), [
            'institution_id' => DB::table('tagore_institutions')->where('school_id', $cashier->school_id)->value('id'),
            'student_id' => User::query()->where('email', 'student@tagore-demo.local')->value('id'),
            'amount' => 10,
        ])->assertForbidden();
    }

    public function test_cashier_can_send_an_audited_app_reminder_to_linked_parents(): void
    {
        $cashier = User::query()->where('email', 'cashier@tagore-demo.local')->firstOrFail();
        $institution = DB::table('tagore_institutions')->where('school_id', $cashier->school_id)->where('status', 'active')->firstOrFail();
        $student = User::query()->where('email', 'student@tagore-demo.local')->where('school_id', $institution->school_id)->firstOrFail();
        $parent = User::query()->where('usergroup_id', 7)->where('school_id', $institution->school_id)
            ->whereNull('deleted_at')->firstOrFail();

        DB::table('tagore_parent_students')->updateOrInsert(
            ['parent_user_id' => $parent->id, 'student_id' => $student->id],
            ['relationship' => 'Guardian', 'is_primary' => true, 'is_guardian' => true, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]
        );

        app(FeeService::class)->createObligation((int) $student->id, (int) $institution->id, [
            'due_date' => now()->addDay()->toDateString(),
            'frequency' => 'one_time',
            'gross_amount' => 500,
            'items' => [['fee_head' => 'Reminder QA', 'code' => 'QA-CASHIER-REMINDER', 'gross_amount' => 500]],
        ], (int) $cashier->id);

        Event::fake([SinglePushEvent::class]);
        $this->actingAs($cashier)->post(route('tagore.cashier.reminder', $student->id))
            ->assertRedirect()->assertSessionHas('success');

        Event::assertDispatched(SinglePushEvent::class, function (SinglePushEvent $event) use ($parent, $student) {
            return (int) $event->data['user_id'] === (int) $parent->id
                && str_contains($event->data['message'], $student->name);
        });
        $this->assertDatabaseHas('tagore_audit_events', [
            'user_id' => $cashier->id,
            'institution_id' => $institution->id,
            'action' => 'FEE_DUE_REMINDER_SENT',
            'entity_type' => 'users',
            'entity_id' => $student->id,
        ]);
    }

    public function test_non_finance_staff_cannot_open_cashier_workspace_or_export_collection_data(): void
    {
        $teacher = User::query()->where('email', 'teacher@tagore-demo.local')->firstOrFail();

        $this->actingAs($teacher)->get(route('tagore.cashier.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('tagore.cashier.export'))->assertForbidden();
    }
}
