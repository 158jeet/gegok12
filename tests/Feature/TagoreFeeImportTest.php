<?php

namespace Tests\Feature;

use Tests\TestCase;

class TagoreFeeImportTest extends TestCase
{
    public function test_fee_layer_files_exist(): void
    {
        foreach ([
            'app/Services/Tagore/LegacyFeeImportService.php','app/Services/Tagore/FeeService.php','app/Services/Tagore/OnlinePaymentService.php',
            'app/Http/Controllers/Tagore/FeeImportController.php','app/Http/Controllers/Tagore/FeeManagementController.php','app/Http/Controllers/Tagore/OnlinePaymentController.php','app/Http/Controllers/Tagore/FeeController.php',
            'resources/views/tagore/fees/import.blade.php','resources/views/tagore/fees/manage.blade.php','resources/views/tagore/payments/checkout.blade.php','resources/views/tagore/fees/accounts.blade.php','resources/views/tagore/fees/receipt.blade.php',
            'database/migrations/2026_09_16_120000_complete_tagore_fee_engine.php','database/migrations/2026_09_16_121000_create_tagore_fee_import_tables.php',
            'database/migrations/2026_09_16_130000_create_tagore_fee_structure_assignments.php','database/migrations/2026_09_16_140000_create_tagore_payment_order_allocations.php',
        ] as $path) $this->assertFileExists(base_path($path));
    }

    public function test_fee_routes_are_present(): void
    {
        $routes=file_get_contents(base_path('routes/tagore.php'));
        foreach(['tagore.fees.manage','tagore.fees.manage.structure','tagore.fees.manage.demand','tagore.fees.manage.assignment','tagore.fees.manage.bulk.preview','tagore.fees.manage.bulk','tagore.fees.import','tagore.fees.import.preview','tagore.fees.import.apply','tagore.payments.initiate','tagore.payments.confirm','tagore.fees.reconcile','tagore.fees.receipt','payments/webhook'] as $route) $this->assertStringContainsString($route,$routes);
    }

    public function test_fee_service_contains_atomic_demand_and_payment_paths(): void
    {
        $service=file_get_contents(base_path('app/Services/Tagore/FeeService.php'));
        foreach(['createObligation','createBulkDemands','recordOfflinePayment','tagore_payment_allocations','DB::transaction','createInstallments'] as $needle) $this->assertStringContainsString($needle,$service);
    }

    public function test_bulk_fee_flow_is_class_section_based_and_idempotent(): void
    {
        $controller=file_get_contents(base_path('app/Http/Controllers/Tagore/FeeManagementController.php')); $view=file_get_contents(base_path('resources/views/tagore/fees/manage.blade.php')); $migration=file_get_contents(base_path('database/migrations/2026_09_16_130000_create_tagore_fee_structure_assignments.php'));
        foreach(['standard_link_id','student_academics','eligibleStudents','createBulkDemands','whereNotIn'] as $needle) $this->assertStringContainsString($needle,$controller);
        foreach(['bulk.preview','bulk','assignment_id','Generate'] as $needle) $this->assertStringContainsString($needle,$view);
        foreach(['tagore_fee_structure_assignments','standard_link_id','fee_structure_id'] as $needle) $this->assertStringContainsString($needle,$migration);
    }

    public function test_bulk_fee_flow_enforces_institution_and_class_scope(): void
    {
        $controller=file_get_contents(base_path('app/Http/Controllers/Tagore/FeeManagementController.php'));
        foreach(['authorizeInstitution','authorizeClassScope','institution_id','school_id','academic_year_id'] as $needle) $this->assertStringContainsString($needle,$controller);
    }

    public function test_online_payment_flow_is_signed_and_idempotent(): void
    {
        $service=file_get_contents(base_path('app/Services/Tagore/OnlinePaymentService.php')); $controller=file_get_contents(base_path('app/Http/Controllers/Tagore/OnlinePaymentController.php')); $migration=file_get_contents(base_path('database/migrations/2026_09_16_140000_create_tagore_payment_order_allocations.php'));
        foreach(['createOrder','hash_hmac','webhook_secret','payment.captured','tagore_payment_order_allocations','where(\'status\',\'success\')','nextReceiptNo'] as $needle) $this->assertStringContainsString($needle,$service);
        foreach(['razorpay_signature','X-Razorpay-Signature','X-Razorpay-Event-Id'] as $needle) $this->assertStringContainsString($needle,$controller);
        foreach(['payment_order_id','fee_obligation_id','amount'] as $needle) $this->assertStringContainsString($needle,$migration);
    }

    public function test_accounts_has_reconciliation_and_receipt_paths(): void
    {
        $controller=file_get_contents(base_path('app/Http/Controllers/Tagore/FeeController.php')); $view=file_get_contents(base_path('resources/views/tagore/fees/accounts.blade.php')); $receipt=file_get_contents(base_path('resources/views/tagore/fees/receipt.blade.php'));
        foreach(['public function reconcile','settlement_id','settlement_amount','PAYMENT_RECONCILED','public function receipt','Pdf::loadView'] as $needle) $this->assertStringContainsString($needle,$controller);
        foreach(['pendingReconciliation','Gateway IDs','Reconcile','tagore.fees.reconcile','tagore.fees.receipt'] as $needle) $this->assertStringContainsString($needle,$view);
        foreach(['Fee Payment Receipt','Total Paid','allocations'] as $needle) $this->assertStringContainsString($needle,$receipt);
    }
}
