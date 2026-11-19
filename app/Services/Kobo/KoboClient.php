<?php

namespace App\Services\Kobo;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class KoboClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $token,
        private readonly int $timeout = 30,
    ) {}

    public static function configured(): self
    {
        return new self(
            config('kobo.base_url'),
            config('kobo.api_token'),
            config('kobo.timeout', 30),
        );
    }

    public function submissions(string $assetUid, ?string $nextUrl = null): array
    {
        if (!$this->token) {
            throw new RuntimeException('KOBO_API_TOKEN is not configured.');
        }

        $url = $nextUrl ?: $this->baseUrl.'/api/v2/assets/'.rawurlencode($assetUid).'/data/';

        $response = Http::withToken($this->token, 'Token')
            ->acceptJson()
            ->timeout($this->timeout)
            ->get($url);

        $response->throw();

        return $response->json();
    }
}