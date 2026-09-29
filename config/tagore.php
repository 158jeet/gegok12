<?php

return [
    'name' => env('TAGORE_APP_NAME', 'TagoreK12'),
    'group_code' => env('TAGORE_GROUP_CODE', 'TAGORE'),
    'prototype' => (bool) env('TAGORE_PROTOTYPE', true),
    'payment_gateway' => env('TAGORE_PAYMENT_GATEWAY', null),
    'mail_profiles_json' => env('TAGORE_MAIL_PROFILES_JSON', ''),
    'sms_enabled' => (bool) env('TAGORE_SMS_ENABLED', false),
    'whatsapp_enabled' => (bool) env('TAGORE_WHATSAPP_ENABLED', false),
    'api_prefix' => 'tagore/v1',
    'fee_vault_owner_user_id' => (int) env('TAGORE_FEE_VAULT_OWNER_USER_ID', 0),

    'performance' => [
        'log_slow_requests' => (bool) env('TAGORE_LOG_SLOW_REQUESTS', false),
        'slow_request_ms' => (int) env('TAGORE_SLOW_REQUEST_MS', 500),
    ],
];
