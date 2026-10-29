<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Tagore\FeeVaultArchiveNotifier;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TagoreFeeArchiveDeliveryTest extends TestCase
{
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

    public function test_closed_year_archive_is_emailed_encrypted_to_the_configured_owner_and_delivery_is_recorded(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        config([
            'tagore.fee_vault_owner_user_id' => (int) $owner->id,
            'tagore.mail_profiles_json' => '',
            'mail.default' => 'array',
        ]);

        $school = DB::table('schools')->whereNull('deleted_at')->orderBy('id')->first();
        $institution = DB::table('tagore_institutions')->where('school_id', $school->id)->where('status', 'active')->first();
        $this->assertNotNull($institution);

        $yearId = DB::table('academic_years')->insertGetId([
            'school_id' => $school->id,
            'name' => 'QA Archive Delivery ' . bin2hex(random_bytes(4)),
            'start_date' => now()->subYear()->startOfYear()->toDateTimeString(),
            'end_date' => now()->subYear()->endOfYear()->toDateTimeString(),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $archivePath = 'tagore-fee-vault/' . $institution->id . '/' . $yearId . '/qa-' . bin2hex(random_bytes(6)) . '.json';
        $plainArchive = json_encode(['academic_year_id' => $yearId, 'secret' => 'must remain encrypted'], JSON_THROW_ON_ERROR);
        $ciphertext = Crypt::encryptString($plainArchive);
        Storage::disk('local')->put($archivePath, $ciphertext);

        try {
            $closureId = DB::table('tagore_fee_year_closures')->insertGetId([
                'academic_year_id' => $yearId,
                'institution_id' => $institution->id,
                'closed_at' => now(),
                'closed_by' => $owner->id,
                'archive_path' => $archivePath,
                'archive_sha256' => hash('sha256', $ciphertext),
                'student_count' => 2,
                'payment_count' => 3,
                'transaction_count' => 4,
                'status' => 'archived',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->assertTrue(app(FeeVaultArchiveNotifier::class)->send($closureId));

            $this->assertDatabaseHas('tagore_fee_archive_deliveries', [
                'closure_id' => $closureId,
                'recipient_user_id' => $owner->id,
                'recipient_email' => $owner->email,
                'status' => 'sent',
                'error' => null,
            ]);

            $this->assertSame(
                1,
                DB::table('tagore_fee_archive_deliveries')->where('closure_id', $closureId)->count(),
                'Re-running archive delivery must use the same idempotent delivery record.'
            );

            $this->assertTrue(Storage::disk('local')->exists($archivePath));
            $storedCiphertext = Storage::disk('local')->get($archivePath);
            $this->assertStringNotContainsString('must remain encrypted', $storedCiphertext);
            $this->assertSame($plainArchive, Crypt::decryptString($storedCiphertext));
        } finally {
            Storage::disk('local')->delete($archivePath);
        }
    }

    public function test_archive_integrity_failure_prevents_email_delivery(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        config([
            'tagore.fee_vault_owner_user_id' => (int) $owner->id,
            'tagore.mail_profiles_json' => '',
            'mail.default' => 'array',
        ]);

        $school = DB::table('schools')->whereNull('deleted_at')->orderBy('id')->first();
        $institution = DB::table('tagore_institutions')->where('school_id', $school->id)->where('status', 'active')->first();
        $yearId = DB::table('academic_years')->insertGetId([
            'school_id' => $school->id,
            'name' => 'QA Archive Integrity ' . bin2hex(random_bytes(4)),
            'start_date' => now()->subYear()->startOfYear()->toDateTimeString(),
            'end_date' => now()->subYear()->endOfYear()->toDateTimeString(),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $archivePath = 'tagore-fee-vault/' . $institution->id . '/' . $yearId . '/qa-invalid-' . bin2hex(random_bytes(6)) . '.json';
        Storage::disk('local')->put($archivePath, Crypt::encryptString('tampered archive'));

        try {
            $closureId = DB::table('tagore_fee_year_closures')->insertGetId([
                'academic_year_id' => $yearId,
                'institution_id' => $institution->id,
                'closed_at' => now(),
                'closed_by' => $owner->id,
                'archive_path' => $archivePath,
                'archive_sha256' => str_repeat('0', 64),
                'student_count' => 0,
                'payment_count' => 0,
                'transaction_count' => 0,
                'status' => 'archived',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->assertFalse(app(FeeVaultArchiveNotifier::class)->send($closureId));
            $this->assertDatabaseHas('tagore_fee_archive_deliveries', [
                'closure_id' => $closureId,
                'status' => 'failed',
            ]);
            $this->assertDatabaseMissing('tagore_fee_archive_deliveries', [
                'closure_id' => $closureId,
                'status' => 'sent',
            ]);
        } finally {
            Storage::disk('local')->delete($archivePath);
        }
    }
}
