<?php

return [
    'base_url' => rtrim(env('KOBO_BASE_URL', 'https://kf.kobotoolbox.org'), '/'),
    'api_token' => env('KOBO_API_TOKEN'),
    'asset_uid' => env('KOBO_ASSET_UID'),
    'timeout' => (int) env('KOBO_TIMEOUT', 30),
];