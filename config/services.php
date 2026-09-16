<?php

return [

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
    ],
    'ses' => [
        'key' => env('SES_KEY'),
        'secret' => env('SES_SECRET'),
        'region' => env('SES_REGION', 'us-east-1'),
    ],
    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],
    'stripe' => [
        'model' => App\Models\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],
    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'key' => env('TWILIO_KEY'),
        'secret' => env('TWILIO_SECRET'),
    ],
    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
    ],
    'razorpay' => [
        'key' => env('RAZORPAY_KEY_ID'),
        'secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],

];
