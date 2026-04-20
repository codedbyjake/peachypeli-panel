<?php

return [

    'curseforge' => [
        'api_key' => env('CURSEFORGE_API_KEY'),
    ],

    'rustmaps' => [
        'key' => env('RUSTMAPS_API_KEY'),
    ],

    'whmcs' => [
        'url'        => env('WHMCS_URL'),
        'identifier' => env('WHMCS_API_IDENTIFIER'),
        'secret'     => env('WHMCS_API_SECRET'),
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

];
