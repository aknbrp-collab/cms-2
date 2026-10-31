<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin Domain
    |--------------------------------------------------------------------------
    |
    | Host that serves the CMS admin panel and authentication routes. When
    | null, admin routes are registered without a domain constraint.
    |
    */

    'admin_domain' => env('ADMIN_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Sites
    |--------------------------------------------------------------------------
    |
    | Default definition of each public website, keyed by App\Enums\SiteKey.
    | These values seed the "sites" table; the database is the source of
    | truth afterwards. "modules" lists the content modules enabled for
    | that site so menus, routes and permissions only appear where relevant.
    |
    */

    'sites' => [
        'pondok' => [
            'name' => 'Pondok Pesantren Darul Amanah',
            'domain' => env('SITE_PONDOK_DOMAIN', 'pondok.test'),
            'modules' => [
                'pages', 'posts', 'events', 'galleries', 'faqs',
                'admissions', 'leaders', 'facilities', 'achievements', 'extracurriculars',
            ],
        ],
        'mahad' => [
            'name' => "Ma'had Aly",
            'domain' => env('SITE_MAHAD_DOMAIN', 'mahad.test'),
            'modules' => [
                'pages', 'posts', 'events', 'galleries', 'admissions', 'leaders',
                'lecturers', 'programs', 'curricula', 'publications', 'journals',
                'organizations', 'legal_questions',
            ],
        ],
    ],

    // The media library disk is set by MEDIA_DISK in config/media-library.php:
    // "s3" (any S3-compatible storage) in production, "public" locally.

];
