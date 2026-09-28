<?php

return [
    'navigation' => [
        'group' => 'Portal',
        'icon' => 'heroicon-o-camera',
        'sort' => 80,
        'label' => 'Snapshots',
        'schedules_label' => 'Snapshot schedules',
        'cluster' => null,
    ],
    'queue' => env('PORTAL_SNAPSHOT_QUEUE', 'default'),
    'queue_connection' => env('PORTAL_SNAPSHOT_QUEUE_CONNECTION'),
    'allow_restore_environments' => ['local', 'testing', 'staging', 'demo'],
    'restore_confirmation_phrase' => 'RESTORE',
    'preserve_plugin_tables' => true,
    'compress' => env('PORTAL_SNAPSHOT_COMPRESS', true),
    'default_connection' => env('PORTAL_SNAPSHOT_CONNECTION'),
    'excluded_tables' => [],
    'keep' => env('PORTAL_SNAPSHOT_KEEP', 14),
    'remote' => [
        'enabled' => env('PORTAL_SNAPSHOT_REMOTE_ENABLED', false),
        'disks' => array_values(array_filter(explode(',', (string) env('PORTAL_SNAPSHOT_REMOTE_DISKS', '')))),
        'directory' => env('PORTAL_SNAPSHOT_REMOTE_DIRECTORY', 'portal-snapshots'),
        'copy_on_create' => env('PORTAL_SNAPSHOT_REMOTE_COPY_ON_CREATE', false),
    ],
    'notifications' => [
        'database' => true,
        'mail' => [
            'enabled' => env('PORTAL_SNAPSHOT_MAIL', true),
            'queue' => env('PORTAL_SNAPSHOT_MAIL_QUEUE', 'default'),
            'recipients' => array_values(array_filter(explode(',', (string) env('PORTAL_SNAPSHOT_MAIL_RECIPIENTS', '')))),
        ],
    ],
    'polling_interval' => '8s',
    'authorize' => true,
];
