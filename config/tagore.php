<?php

return [
    'name' => env('TAGORE_APP_NAME', 'TagoreK12'),
    'group_code' => env('TAGORE_GROUP_CODE', 'TAGORE'),
    'prototype' => (bool) env('TAGORE_PROTOTYPE', true),
    'payment_gateway' => env('TAGORE_PAYMENT_GATEWAY', null),
    'api_prefix' => 'tagore/v1',

    'performance' => [
        'log_slow_requests' => (bool) env('TAGORE_LOG_SLOW_REQUESTS', false),
        'slow_request_ms' => (int) env('TAGORE_SLOW_REQUEST_MS', 500),
    ],
];
