<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Polling jaringan
    |--------------------------------------------------------------------------
    | Interval scheduler `monitor:poll` (detik). 30 = keputusan user 2026-10.
    */
    'poll_interval' => (int) env('POLL_INTERVAL', 30),

    /*
    |--------------------------------------------------------------------------
    | Retensi metrics (hari)
    |--------------------------------------------------------------------------
    */
    'retention_days' => (int) env('METRICS_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Retensi metrics PER INTERFACE (hari)
    |--------------------------------------------------------------------------
    | Lebih pendek dari device_metrics karena satu poll menghasilkan banyak
    | baris (per interface). 7 hari cukup untuk grafik 1j/6j/24j/7h.
    */
    'interface_retention_days' => (int) env('INTERFACE_RETENTION_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | SNMP default (bisa ditimpa per-perangkat)
    |--------------------------------------------------------------------------
    */
    'snmp' => [
        'version' => env('SNMP_VERSION', '2c'),
        'community' => env('SNMP_COMMUNITY', 'public'),
        'port' => (int) env('SNMP_PORT', 161),
        'timeout_ms' => (int) env('SNMP_TIMEOUT', 3000),
        'retries' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | ICMP
    |--------------------------------------------------------------------------
    | `timeout` = -W ping (detik), `binary` = path ping.
    */
    'icmp' => [
        'timeout' => (int) env('ICMP_TIMEOUT', 2),
        'binary' => env('ICMP_BINARY', 'ping'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Kategori tiket (menu dropdown Lapor Gangguan)
    |--------------------------------------------------------------------------
    */
    'ticket_categories' => [
        'Jaringan',
        'Perangkat',
        'Website / Portal',
        'Aplikasi',
        'Lainnya',
    ],

    'ticket_statuses' => [
        'open' => 'Baru',
        'proses' => 'Diproses',
        'selesai' => 'Selesai',
    ],
];
