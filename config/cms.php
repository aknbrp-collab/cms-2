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
    | Alert Email
    |--------------------------------------------------------------------------
    |
    | Receives operational alerts: failed or unhealthy backups and long
    | Horizon queue waits. Leave empty to disable the emails.
    |
    */

    'alert_email' => env('CMS_ALERT_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Proxies and HTTPS
    |--------------------------------------------------------------------------
    |
    | "trusted_proxies": comma separated IPs/CIDRs (or "*") of a load balancer
    | or CDN in front of nginx, so client IPs and HTTPS are detected. Leave
    | empty when nginx talks to PHP directly. "hsts_max_age": seconds browsers
    | must use HTTPS once they have seen it (0 disables the header).
    |
    */

    'trusted_proxies' => env('TRUSTED_PROXIES'),

    'hsts_max_age' => (int) env('HSTS_MAX_AGE', 31536000),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Days to keep operational records before they are pruned nightly.
    | Audit logs are append-only and never pruned.
    |
    */

    'retention' => [
        'integration_logs' => (int) env('CMS_RETAIN_INTEGRATION_LOGS_DAYS', 180),
        // Uploaded admission documents, counted from the period's closing date. Empty = keep.
        'admission_documents' => env('CMS_ADMISSION_DOCUMENTS_RETENTION_DAYS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sites
    |--------------------------------------------------------------------------
    |
    | Default definition of each public website, keyed by App\Enums\SiteKey.
    | These values seed the "sites" table; the database is the source of
    | truth afterwards. "theme" holds the default brand colours of the site's
    | public theme. "modules" lists the content modules enabled for
    | that site so menus, routes and permissions only appear where relevant.
    |
    */

    'sites' => [
        'pondok' => [
            'name' => 'Pondok Pesantren Darul Amanah',
            'domain' => env('SITE_PONDOK_DOMAIN', 'pondok.test'),
            'theme' => ['primary' => '#005D42', 'primary_container' => '#047857', 'secondary' => '#EAB308'],
            'modules' => [
                'pages', 'posts', 'events', 'galleries', 'faqs',
                'admissions', 'leaders', 'facilities', 'achievements', 'extracurriculars',
                'education_units', 'alumni', 'testimonials',
            ],
        ],
        'mahad' => [
            'name' => "Ma'had Aly",
            'domain' => env('SITE_MAHAD_DOMAIN', 'mahad.test'),
            'theme' => ['primary' => '#003426', 'primary_container' => '#0F4C3A', 'secondary' => '#7B5900'],
            'modules' => [
                'pages', 'posts', 'events', 'galleries', 'faqs', 'admissions', 'leaders',
                'lecturers', 'programs', 'curricula', 'publications', 'journals',
                'organizations', 'legal_questions', 'hero_slides',
            ],
        ],
    ],

    // The media library disk is set by MEDIA_DISK in config/media-library.php:
    // "s3" (any S3-compatible storage) in production, "public" locally.

];
