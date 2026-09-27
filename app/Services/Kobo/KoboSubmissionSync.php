<?php

namespace App\Services\Kobo;

use App\Models\KoboSubmission;
use Illuminate\Support\Carbon;

class KoboSubmissionSync
{
    public function __construct(private readonly KoboClient $client) {}

    public function sync(?string $assetUid = null): int
    {
        $assetUid ??= config('kobo.asset_uid');

        if (!$assetUid) {
            throw new \RuntimeException('KOBO_ASSET_UID is not configured.');
        }

        $next = null;
        $count = 0;

        do {
            $page = $this->client->submissions($assetUid, $next);

            foreach (($page['results'] ?? []) as $submission) {
                $rootUuid = $submission['__id'] ?? $submission['_uuid'] ?? $submission['meta/instanceID'] ?? null;
                $submittedAt = $submission['_submission_time'] ?? $submission['_submitted_at'] ?? null;

                KoboSubmission::updateOrCreate(
                    [
                        'asset_uid' => $assetUid,
                        'submission_uid' => $rootUuid ?: (string) ($submission['_id'] ?? ''),
                    ],
                    [
                        'submission_id' => isset($submission['_id']) ? (int) $submission['_id'] : null,
                        'submitted_at' => $submittedAt ? Carbon::parse($submittedAt) : null,
                        'device_id' => $submission['_device_id'] ?? null,
                        'submitted_by' => $submission['_submitted_by'] ?? null,
                        'data' => $submission,
                        'synced_at' => now(),
                    ]
                );

                $count++;
            }

            $next = $page['next'] ?? null;
        } while ($next);

        return $count;
    }
}