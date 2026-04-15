<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'monitoring' => [
        'failure_retry_delay_seconds' => env('MONITORING_FAILURE_RETRY_DELAY_SECONDS', 3),
        'default_request_headers' => [
            'Accept' => env('MONITORING_REQUEST_ACCEPT', 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8'),
            'Accept-Language' => env('MONITORING_REQUEST_ACCEPT_LANGUAGE', 'en-GB,en-US;q=0.9,en;q=0.8'),
            'Cache-Control' => env('MONITORING_REQUEST_CACHE_CONTROL', 'no-cache'),
            'Pragma' => env('MONITORING_REQUEST_PRAGMA', 'no-cache'),
            'Upgrade-Insecure-Requests' => env('MONITORING_REQUEST_UPGRADE_INSECURE_REQUESTS', '1'),
            'User-Agent' => env('MONITORING_REQUEST_USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36'),
        ],
        'schedule_jitter_max_seconds' => env('MONITORING_SCHEDULE_JITTER_MAX_SECONDS', 10),
        'browser_profile_directory' => env('MONITORING_BROWSER_PROFILE_DIRECTORY', 'app/monitoring-browser-profiles'),
        'browser_settle_seconds' => env('MONITORING_BROWSER_SETTLE_SECONDS', 10),
        'downtime_screenshot_disk' => env('MONITORING_DOWNTIME_SCREENSHOT_DISK', 'public'),
        'downtime_screenshot_directory' => env('MONITORING_DOWNTIME_SCREENSHOT_DIRECTORY', 'downtime-screenshots'),
        'latest_service_screenshot_directory' => env('MONITORING_LATEST_SERVICE_SCREENSHOT_DIRECTORY', 'service-screenshots'),
        'downtime_history_retention_days' => env('MONITORING_DOWNTIME_HISTORY_RETENTION_DAYS', 90),
    ],

];
