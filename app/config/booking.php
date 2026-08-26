<?php

return [

    // These seed the `settings` table on first migrate/seed. Once seeded, the
    // Administration -> Settings UI is authoritative — these env values are
    // not re-read on every request.
    'defaults' => [
        'site_name' => env('APP_NAME', 'Resource Booking'),
        'timezone' => env('APP_TIMEZONE', 'Australia/Brisbane'),
        'min_auto_approval_lead_hours' => (int) env('BOOKING_MIN_AUTO_APPROVAL_LEAD_HOURS', 6),
        'school_day_start' => env('BOOKING_SCHOOL_DAY_START', '07:00'),
        'school_day_finish' => env('BOOKING_SCHOOL_DAY_FINISH', '17:00'),
        'allow_weekends' => filter_var(env('BOOKING_ALLOW_WEEKENDS', false), FILTER_VALIDATE_BOOL),
        'weekend_requires_approval' => true,
        'out_of_hours_requires_approval' => true,
        'reference_prefix' => env('BOOKING_REFERENCE_PREFIX', 'EX'),
        'mail_from_address' => env('MAIL_FROM_ADDRESS'),
        'mail_from_name' => env('APP_NAME', 'Resource Booking'),
        'it_notification_address' => env('IT_NOTIFICATION_ADDRESS'),
        'helpdesk_reply_to_address' => env('HELPDESK_REPLY_TO_ADDRESS'),
        'admin_seed_emails' => env('ADMIN_SEED_EMAILS', ''),

        // Iframe embedding. Off by default: the app sends
        // `X-Frame-Options: SAMEORIGIN` + `frame-ancestors 'self'` and can only
        // be framed by itself. Turn it on and list the parent origins (one per
        // line, or comma-separated, scheme + host, e.g. https://intranet.example.edu)
        // to allow those sites to embed it — see README § "Embedding".
        'embedding_enabled' => filter_var(env('BOOKING_EMBEDDING_ENABLED', false), FILTER_VALIDATE_BOOL),
        'embedding_allowed_origins' => env('BOOKING_EMBEDDING_ALLOWED_ORIGINS', ''),
    ],
];
