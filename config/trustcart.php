<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI
    |--------------------------------------------------------------------------
    |
    | The model interprets language and writes narrative. It never decides a
    | constraint. Providers are interchangeable and every failure degrades to
    | the deterministic extractor so the demo cannot be blocked by a key.
    |
    */
    'ai' => [
        'provider' => env('TRUSTCART_AI_PROVIDER', 'gemini'),
        'fallback' => env('TRUSTCART_AI_FALLBACK', 'deepseek'),
        'timeout' => (int) env('TRUSTCART_AI_TIMEOUT', 25),
        'gemini' => [
            'key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-3.5-flash'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        ],
        'deepseek' => [
            'key' => env('DEEPSEEK_API_KEY'),
            'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
            'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | PayPal
    |--------------------------------------------------------------------------
    |
    | Sandbox only. Live credentials are deliberately not supported by this
    | prototype: the checkout service refuses to run against anything but the
    | sandbox host unless explicitly unlocked for a production deployment.
    |
    */
    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'base_url' => env('PAYPAL_BASE_URL', 'https://api-m.sandbox.paypal.com'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        'currency' => env('PAYPAL_CURRENCY', 'USD'),
        'timeout' => (int) env('PAYPAL_TIMEOUT', 25),
    ],

    'approval_ttl_minutes' => (int) env('TRUSTCART_APPROVAL_TTL', 15),

    'demo_mode' => (bool) env('TRUSTCART_DEMO_MODE', true),
];
