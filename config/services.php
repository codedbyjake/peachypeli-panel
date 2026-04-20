<?php

return [

    'curseforge' => [
        'api_key' => env('CURSEFORGE_API_KEY'),
    ],

    'rustmaps' => [
        'key' => env('RUSTMAPS_API_KEY'),
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

];
