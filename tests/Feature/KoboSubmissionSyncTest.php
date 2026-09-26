<?php

namespace Tests\Feature;

use App\Models\KoboSubmission;
use App\Services\Kobo\KoboClient;
use App\Services\Kobo\KoboSubmissionSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KoboSubmissionSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_imports_paginated_submissions_idempotently(): void
    {
        Http::fake([
            'https://kf.kobotoolbox.org/api/v2/assets/abc123/data/' => Http::response([
                'results' => [[
                    '__id' => 'uuid-1',
                    '_id' => 101,
                    '_submission_time' => '2026-09-26T05:00:00Z',
                    '_device_id' => 'device-1',
                    '_submitted_by' => 'collector-1',
                    'student_name' => 'Test Student',
                ]],
                'next' => 'https://kf.kobotoolbox.org/api/v2/assets/abc123/data/?start=1',
            ]),
            'https://kf.kobotoolbox.org/api/v2/assets/abc123/data/?start=1' => Http::response([
                'results' => [[
                    '__id' => 'uuid-2',
                    '_id' => 102,
                    '_submission_time' => '2026-09-26T05:05:00Z',
                    '_device_id' => 'device-2',
                    '_submitted_by' => 'collector-2',
                    'student_name' => 'Second Student',
                ]],
                'next' => null,
            ]),
        ]);

        $client = new KoboClient('https://kf.kobotoolbox.org', 'token', 30);
        $sync = new KoboSubmissionSync($client);

        $this->assertSame(2, $sync->sync('abc123'));
        $this->assertSame(2, KoboSubmission::count());

        $this->assertSame(2, $sync->sync('abc123'));
        $this->assertSame(2, KoboSubmission::count());

        $first = KoboSubmission::where('submission_uid', 'uuid-1')->firstOrFail();
        $this->assertSame('Test Student', $first->data['student_name']);
        $this->assertNotNull($first->synced_at);

        Http::assertSentCount(4);
    }

    public function test_sync_requires_an_asset_uid(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('KOBO_ASSET_UID is not configured.');

        $client = new KoboClient('https://kf.kobotoolbox.org', 'token', 30);
        (new KoboSubmissionSync($client))->sync();
    }
}
