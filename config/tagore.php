<?php

return [
    'name' => env('TAGORE_APP_NAME', 'TagoreK12'),
    'group_code' => env('TAGORE_GROUP_CODE', 'TAGORE'),
    'prototype' => (bool) env('TAGORE_PROTOTYPE', true),
    'payment_gateway' => env('TAGORE_PAYMENT_GATEWAY', null),
    'api_prefix' => 'tagore/v1',
];
