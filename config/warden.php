<?php

return [
    'app_name' => env('WARDEN_APP_NAME', config('app.name')),

    'webhook_url' => env('WARDEN_WEBHOOK_URL'),
    'email_recipients' => env('WARDEN_EMAIL_RECIPIENTS'),

    'notifications' => [
        'slack' => [
            'webhook_url' => env('WARDEN_SLACK_WEBHOOK_URL', env('WARDEN_WEBHOOK_URL')),
        ],
        'discord' => [
            'webhook_url' => env('WARDEN_DISCORD_WEBHOOK_URL'),
        ],
        'teams' => [
            'webhook_url' => env('WARDEN_TEAMS_WEBHOOK_URL'),
        ],
        'email' => [
            'recipients' => env('WARDEN_EMAIL_RECIPIENTS'),
            'from_address' => env('WARDEN_EMAIL_FROM', config('mail.from.address')),
            'from_name' => env('WARDEN_EMAIL_FROM_NAME', 'Warden Security'),
        ],
    ],

    'cache' => [
        'enabled' => env('WARDEN_CACHE_ENABLED', true),
        'duration' => (int) env('WARDEN_CACHE_DURATION', 3600),
    ],

    'audits' => [
        'parallel_execution' => env('WARDEN_PARALLEL_EXECUTION', true),
        'timeout' => (int) env('WARDEN_AUDIT_TIMEOUT', 300),
        'php_syntax' => [
            'enabled' => env('WARDEN_PHP_SYNTAX_AUDIT_ENABLED', false),
            'exclude' => [
                'vendor',
                'node_modules',
                'storage',
                'bootstrap/cache',
                '.git',
            ],
        ],
    ],

    'custom_audits' => [],

    'schedule' => [
        'enabled' => env('WARDEN_SCHEDULE_ENABLED', false),
        'frequency' => env('WARDEN_SCHEDULE_FREQUENCY', 'daily'),
        'time' => env('WARDEN_SCHEDULE_TIME', '03:00'),
        'timezone' => env('WARDEN_SCHEDULE_TIMEZONE', config('app.timezone')),
    ],

    'history' => [
        'enabled' => env('WARDEN_HISTORY_ENABLED', false),
        'table' => env('WARDEN_HISTORY_TABLE', 'warden_audit_history'),
        'retention_days' => (int) env('WARDEN_HISTORY_RETENTION_DAYS', 90),
    ],

    'sensitive_keys' => [
        'APP_KEY',
    ],
];
