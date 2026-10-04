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

    /*
    |--------------------------------------------------------------------------
    | Request VPS
    |--------------------------------------------------------------------------
    */

    // Opsi checkbox "Service PORT yang dibuka" pada form request VPS
    // (key = nilai yang disimpan, label = tampilan di form).
    'vps_ports' => [
        '22' => 'SSH',
        '80' => 'HTTP',
        '443' => 'HTTPS',
        '21' => 'FTP',
        '25' => 'SMTP',
        '53' => 'DNS',
        '3306' => 'MySQL',
        '5432' => 'PostgreSQL',
        '8080' => 'HTTP-Alt',
        '20000-20100' => 'Passive FTP',
    ],

    // Opsi dropdown "Pilihan Sistem Operasi" pada form request VPS
    // (key = nilai yang disimpan, label = tampilan di form).
    'vps_operating_systems' => [
        'debian-12' => 'Debian 12',
        'ubuntu-2404' => 'Ubuntu 24.04 LTS',
        'ubuntu-2204' => 'Ubuntu 22.04 LTS',
        'rocky-9' => 'Rocky Linux 9',
        'almalinux-9' => 'AlmaLinux 9',
        'centos-stream-9' => 'CentOS Stream 9',
        'windows-server-2022' => 'Windows Server 2022',
        'lainnya' => 'Lainnya',
    ],

    'vps_statuses' => [
        'pending' => 'Menunggu Review',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
    ],

    'registration_statuses' => [
        'pending' => 'Menunggu Validasi',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
    ],
];
