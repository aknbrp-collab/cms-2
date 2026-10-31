<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SIAKAD Integration
    |--------------------------------------------------------------------------
    |
    | The CMS and SIAKAD talk over HTTPS with JSON. See docs/siakad-api-spec.md.
    |
    | driver:          "http" calls the real SIAKAD API, "fake" pretends every
    |                  call succeeds (local development and tests).
    | base_url:        SIAKAD API root, e.g. https://siakad.example.ac.id/api/cms
    | api_key:         Bearer token the CMS sends to SIAKAD.
    | webhook_secret:  Shared secret SIAKAD uses to sign webhooks to the CMS
    |                  (and the CMS uses to sign its requests to SIAKAD).
    | tolerance:       Maximum age in seconds of a signed request.
    | site:            Site key that receives SIAKAD articles and questions.
    |
    */

    'driver' => env('SIAKAD_DRIVER', 'fake'),

    'base_url' => rtrim((string) env('SIAKAD_BASE_URL', ''), '/'),

    'api_key' => env('SIAKAD_API_KEY'),

    'webhook_secret' => env('SIAKAD_WEBHOOK_SECRET'),

    'tolerance' => (int) env('SIAKAD_SIGNATURE_TOLERANCE', 300),

    'timeout' => (int) env('SIAKAD_TIMEOUT', 15),

    'site' => env('SIAKAD_SITE', 'mahad'),

];
