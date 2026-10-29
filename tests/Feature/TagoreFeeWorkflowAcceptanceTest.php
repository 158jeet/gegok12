<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Tagore\FeeService;
use App\Services\Tagore\FeeEditorService;
use App\Services\Tagore\FeeVaultService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TagoreFeeWorkflowAcceptanceTest extends TestCase
{
    private ?int $obligationId = null;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    public function test_offline_payment_updates_obligation_ledger_and_receipt_atomically(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $institution = DB::table('tagore_institutions')
            ->where('school_id', $owner->school_id)
            ->where('status', 'active')
            ->first();
        $this->assertNotNull($institution, 'Owner must have an active institution fixture.');

        $student = User::query()
            ->where('usergroup_id', 6)
            ->where('school_id', $institution->school_id)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $service = app(FeeService::class);
        $this->obligationId = $service->createObligation(
            (int) $student->id,
            (int) $institution->id,
            [
                'due_date' => now()->addDay()->toDateString(),
                'frequency' => 'one_time',
                'gross_amount' => 100.00,
                'items' => [[
                    'fee_head' => 'Acceptance tuition',
                    'code' => 'QA-TUITION',
                    'gross_amount' => 100.00,
                    'discount_amount' => 0,
                    'concession_amount' => 0,
                ]],
            ],
            (int) $owner->id,
        );

        $reference = 'QA-FEE-' . bin2hex(random_bytes(5));
        $paymentId = $service->recordOfflinePayment(
            (int) $student->id,
            (int) $institution->id,
            40.00,
            null,
            (int) $owner->id,
            $reference,
            [['fee_obligation_id' => $this->obligationId, 'amount' => 40.00]],
            'bank_transfer',
        );

        $this->assertDatabaseHas('tagore_payments', [
            'id' => $paymentId,
            'student_id' => $student->id,
            'institution_id' => $institution->id,
            'amount' => '40.00',
            'payment_mode' => 'bank_transfer',
            'reference_number' => $reference,
            'status' => 'success',
        ]);
        $this->assertDatabaseHas('tagore_payment_allocations', [
            'payment_id' => $paymentId,
            'fee_obligation_id' => $this->obligationId,
            'amount' => '40.00',
        ]);
        $this->assertDatabaseHas('tagore_fee_obligations', [
            'id' => $this->obligationId,
            'gross_amount' => '100.00',
            'paid_amount' => '40.00',
            'outstanding_amount' => '60.00',
            'status' => 'partial',
        ]);
        $this->assertDatabaseHas('tagore_fee_obligation_items', [
            'fee_obligation_id' => $this->obligationId,
            'fee_head' => 'Acceptance tuition',
            'paid_amount' => '40.00',
            'outstanding_amount' => '60.00',
        ]);
        $this->assertDatabaseHas('tagore_financial_transactions', [
            'reference_type' => 'tagore_payment',
            'reference_id' => $paymentId,
            'transaction_type' => 'PAYMENT',
            'credit' => '40.00',
        ]);
        $this->assertDatabaseHas('tagore_audit_events', [
            'user_id' => $owner->id,
            'action' => 'FEE_PAYMENT_RECORDED',
            'entity_type' => 'tagore_payments',
            'entity_id' => $paymentId,
        ]);

        $paymentCount = DB::table('tagore_payments')->count();
        try {
            $service->recordOfflinePayment(
                (int) $student->id,
                (int) $institution->id,
                61.00,
                null,
                (int) $owner->id,
                'QA-OVERPAY-' . bin2hex(random_bytes(4)),
                [['fee_obligation_id' => $this->obligationId, 'amount' => 61.00]],
                'cash',
            );
            $this->fail('An allocation greater than the outstanding balance must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('allocations', $exception->errors());
        }

        $this->assertSame($paymentCount, DB::table('tagore_payments')->count(), 'Rejected overpayment must roll back its payment row.');
        $this->assertDatabaseHas('tagore_fee_obligations', [
            'id' => $this->obligationId,
            'paid_amount' => '40.00',
            'outstanding_amount' => '60.00',
            'status' => 'partial',
        ]);
    }


    public function test_fee_percentage_adjustment_updates_line_items_and_rejects_reduction_below_paid_amount(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $institution = DB::table('tagore_institutions')
            ->where('school_id', $owner->school_id)
            ->where('status', 'active')
            ->firstOrFail();

        $student = User::factory()->create([
            'name' => 'Fee Editor Acceptance Student',
            'email' => 'fee-editor-acceptance-' . bin2hex(random_bytes(4)) . '@example.invalid',
            'school_id' => $institution->school_id,
            'usergroup_id' => 6,
        ]);

        $feeService = app(FeeService::class);
        $obligationId = $feeService->createObligation(
            (int) $student->id,
            (int) $institution->id,
            [
                'gross_amount' => 100.00,
                'items' => [
                    ['fee_head' => 'Tuition', 'code' => 'QA-TUITION-A', 'gross_amount' => 60.00],
                    ['fee_head' => 'Activity', 'code' => 'QA-ACTIVITY-B', 'gross_amount' => 40.00],
                ],
            ],
            (int) $owner->id,
        );

        $editor = app(FeeEditorService::class);
        $this->assertSame(1, $editor->bulkAdjust([
            'institution_id' => (int) $institution->id,
            'student_id' => (int) $student->id,
        ], 10.0, 10.0, (int) $owner->id));

        $this->assertDatabaseHas('tagore_fee_obligations', [
            'id' => $obligationId,
            'gross_amount' => '110.00',
            'net_amount' => '110.00',
            'outstanding_amount' => '110.00',
        ]);
        $items = DB::table('tagore_fee_obligation_items')
            ->where('fee_obligation_id', $obligationId)
            ->orderBy('id')
            ->get();
        $this->assertSame('66.00', number_format((float) $items[0]->gross_amount, 2, '.', ''));
        $this->assertSame('44.00', number_format((float) $items[1]->gross_amount, 2, '.', ''));
        $this->assertSame('110.00', number_format((float) $items->sum('net_amount'), 2, '.', ''));
        $this->assertDatabaseHas('tagore_fee_editor_events', [
            'fee_obligation_id' => $obligationId,
            'edited_by' => $owner->id,
            'percentage' => '10.000',
        ]);

        $feeService->recordOfflinePayment(
            (int) $student->id,
            (int) $institution->id,
            80.00,
            null,
            (int) $owner->id,
            'QA-FEE-EDITOR-PAYMENT',
            [['fee_obligation_id' => $obligationId, 'amount' => 80.00]],
            'cash',
        );

        $itemsAfterPayment = DB::table('tagore_fee_obligation_items')
            ->where('fee_obligation_id', $obligationId)
            ->orderBy('id')
            ->get();
        $this->assertSame('66.00', number_format((float) $itemsAfterPayment[0]->paid_amount, 2, '.', ''));
        $this->assertSame('14.00', number_format((float) $itemsAfterPayment[1]->paid_amount, 2, '.', ''));
        $this->assertSame('30.00', number_format((float) $itemsAfterPayment->sum('outstanding_amount'), 2, '.', ''));

        try {
            $editor->bulkAdjust([
                'institution_id' => (int) $institution->id,
                'student_id' => (int) $student->id,
            ], -30.0, -30.0, (int) $owner->id);
            $this->fail('A fee reduction below the amount already collected must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('percentage', $exception->errors());
        }

        $this->assertDatabaseHas('tagore_fee_obligations', [
            'id' => $obligationId,
            'gross_amount' => '110.00',
            'paid_amount' => '80.00',
            'outstanding_amount' => '30.00',
            'status' => 'partial',
        ]);
        $this->assertSame(1, DB::table('tagore_fee_editor_events')->where('fee_obligation_id', $obligationId)->count());
        $this->assertSame('110.00', number_format((float) DB::table('tagore_fee_obligation_items')->where('fee_obligation_id', $obligationId)->sum('net_amount'), 2, '.', ''));
    }




    public function test_parent_fee_statement_shows_balances_but_hides_payment_history_and_receipt_identifiers(): void
    {
        $parent = User::query()->where('email', 'parent@tagore-demo.local')->firstOrFail();
        $student = User::query()->where('email', 'student@tagore-demo.local')->firstOrFail();
        $institution = DB::table('tagore_institutions')
            ->where('school_id', $student->school_id)
            ->where('status', 'active')
            ->firstOrFail();

        DB::table('tagore_parent_students')->updateOrInsert(
            ['parent_user_id' => $parent->id, 'student_id' => $student->id],
            ['relationship' => 'Guardian', 'is_primary' => true, 'is_guardian' => true, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]
        );

        $feeService = app(FeeService::class);
        $obligationId = $feeService->createObligation(
            (int) $student->id,
            (int) $institution->id,
            [
                'gross_amount' => 90.00,
                'items' => [[
                    'fee_head' => 'Parent-visible balance',
                    'code' => 'QA-PARENT-BALANCE',
                    'gross_amount' => 90.00,
                ]],
            ],
            null,
        );
        $paymentId = $feeService->recordOfflinePayment(
            (int) $student->id,
            (int) $institution->id,
            20.00,
            (int) $parent->id,
            (int) $parent->id,
            'QA-PRIVATE-HISTORY-' . bin2hex(random_bytes(4)),
            [['fee_obligation_id' => $obligationId, 'amount' => 20.00]],
            'cash',
        );
        $receiptNo = DB::table('tagore_payments')->where('id', $paymentId)->value('receipt_no');

        $this->actingAs($parent)
            ->get(route('tagore.fees.student', ['studentId' => $student->id]))
            ->assertOk()
            ->assertSee('Fee Statement')
            ->assertSee('Fee obligations')
            ->assertSee('Outstanding')
            ->assertDontSee('Payment history')
            ->assertDontSee((string) $receiptNo)
            ->assertDontSee('QA-PRIVATE-HISTORY');
    }

    public function test_year_end_fee_vault_is_encrypted_integrity_checked_and_idempotent(): void
    {
        Storage::fake('local');

        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $institution = DB::table('tagore_institutions')
            ->where('school_id', $owner->school_id)
            ->where('status', 'active')
            ->firstOrFail();
        $academicYear = DB::table('academic_years')
            ->where('school_id', $institution->school_id)
            ->orderByDesc('start_date')
            ->first();
        $this->assertNotNull($academicYear, 'Acceptance seed must include an academic year.');

        $student = User::factory()->create([
            'name' => 'Fee Vault Acceptance Student',
            'email' => 'fee-vault-acceptance-' . bin2hex(random_bytes(4)) . '@example.invalid',
            'school_id' => $institution->school_id,
            'usergroup_id' => 6,
        ]);

        app(FeeService::class)->createObligation(
            (int) $student->id,
            (int) $institution->id,
            [
                'academic_year_id' => (int) $academicYear->id,
                'gross_amount' => 125.00,
                'items' => [[
                    'fee_head' => 'Archived tuition',
                    'code' => 'QA-VAULT-TUITION',
                    'gross_amount' => 125.00,
                ]],
            ],
            (int) $owner->id,
        );

        $vault = app(FeeVaultService::class);
        $first = $vault->closeYear((int) $academicYear->id, (int) $institution->id, (int) $owner->id);
        $second = $vault->closeYear((int) $academicYear->id, (int) $institution->id, (int) $owner->id);

        $this->assertSame($first['id'], $second['id'], 'Closing the same year twice must not create a second archive.');
        $this->assertSame('archived', $first['status']);
        $this->assertTrue(Storage::disk('local')->exists($first['archive_path']));
        $ciphertext = Storage::disk('local')->get($first['archive_path']);
        $this->assertNotSame('{"students"', substr($ciphertext, 0, 11), 'Archive payload must not be stored as plain JSON.');
        $this->assertSame(hash('sha256', $ciphertext), $first['archive_sha256']);
        $this->assertDatabaseHas('tagore_audit_events', [
            'user_id' => $owner->id,
            'institution_id' => $institution->id,
            'action' => 'FEE_YEAR_ARCHIVED',
            'entity_id' => $first['id'],
        ]);

        $archives = $vault->studentArchives((int) $student->id);
        $this->assertCount(1, $archives);
        $this->assertSame($student->id, (int) $archives[0]['records'][0]['student_id']);

        Storage::disk('local')->put($first['archive_path'], 'tampered archive');
        $this->assertSame([], $vault->studentArchives((int) $student->id), 'Tampered archive must be rejected by its integrity hash.');
    }

    public function test_fee_editor_is_limited_to_its_assigned_institution(): void
    {
        $editor = User::query()->where('email', 'fees@tagore-demo.local')->firstOrFail();
        $assignedInstitutionId = DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $editor->id)
            ->where('ur.status', 'active')
            ->where('r.code', 'FEE_EDITOR')
            ->whereNotNull('ur.institution_id')
            ->value('ur.institution_id');
        $this->assertNotNull($assignedInstitutionId, 'Fee editor fixture must have an assigned institution.');

        $otherInstitution = DB::table('tagore_institutions')
            ->where('status', 'active')
            ->where('id', '!=', $assignedInstitutionId)
            ->first();

        // Cross-institution authorization tests must not depend on the demo seeder
        // accidentally containing multiple schools. Create an isolated second tenant.
        if (!$otherInstitution) {
            $assignedInstitution = DB::table('tagore_institutions')->where('id', $assignedInstitutionId)->firstOrFail();
            $suffix = bin2hex(random_bytes(4));
            $otherSchoolId = DB::table('schools')->insertGetId([
                'name' => 'Acceptance Isolation School ' . $suffix,
                'email' => 'acceptance-isolation-' . $suffix . '@tagore-demo.local',
                'phone' => '9' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
                'status' => 1,
                'slug' => 'acceptance-isolation-' . $suffix,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $otherInstitutionId = DB::table('tagore_institutions')->insertGetId([
                'tagore_group_id' => $assignedInstitution->tagore_group_id,
                'school_id' => $otherSchoolId,
                'code' => 'QA-' . strtoupper($suffix),
                'display_name' => 'Acceptance Isolation School ' . $suffix,
                'type' => 'school',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $otherInstitution = DB::table('tagore_institutions')->where('id', $otherInstitutionId)->first();
        }

        $this->actingAs($editor)
            ->get(route('tagore.fees.editor'))
            ->assertOk()
            ->assertDontSee($otherInstitution->display_name);

        $this->actingAs($editor)
            ->post(route('tagore.fees.editor.bulk'), [
                'institution_id' => $otherInstitution->id,
                'min_percent' => 5,
                'max_percent' => 5,
            ])
            ->assertForbidden();
    }

    public function test_fee_editor_and_coordinator_cannot_record_offline_payments(): void
    {
        $institution = DB::table('tagore_institutions')->where('status', 'active')->first();
        $student = User::query()
            ->where('usergroup_id', 6)
            ->where('school_id', $institution->school_id)
            ->whereNull('deleted_at')
            ->firstOrFail();

        foreach (['fees@tagore-demo.local', 'coordinator@tagore-demo.local'] as $email) {
            $user = User::query()->where('email', $email)->firstOrFail();
            $this->actingAs($user)
                ->post(route('tagore.fees.payment', $student->id), [
                    'amount' => 10,
                    'payment_mode' => 'cash',
                    'reference_number' => 'QA-UNAUTHORIZED',
                ])
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('tagore_payments', ['reference_number' => 'QA-UNAUTHORIZED']);
    }
}
