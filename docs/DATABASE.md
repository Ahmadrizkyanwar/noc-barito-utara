# Struktur Database — NOC Barito Utara

Sumber: `information_schema` database produksi **`noc_barutara`** (MariaDB,
container `mariadb`). Diambil 4 Oktober 2026 — **19 tabel**, ±387,435 baris
(terbesar: `interface_metrics` dan `device_metrics` dari hasil poller).

> Dokumen ini dihasilkan dari struktur DB aktual (bukan dari migration saja),
> sehingga mencakup kolom, tipe, index, dan foreign key yang benar-benar dipakai.
> Perbarui setelah menambah migration baru.

## Ikhtisar tabel

| Tabel | Peran | Baris |
|---|---|---|
| `users` | Akun (admin/operator/user) + status registrasi | 3 |
| `sessions` / `cache` / `cache_locks` | Sesi & cache (driver database) | 171 / 26 / 1 |
| `devices` | Perangkat jaringan yang dipantau | 12 |
| `device_metrics` | Sampel ICMP/SNMP/RouterOS per perangkat (retensi 30 hari) | 23,345 |
| `interface_metrics` | Sampel trafik per interface (retensi 7 hari) | 363,809 |
| `tickets` + `ticket_activities` | Laporan gangguan + riwayat aktivitas | 4 / 8 |
| `vps_requests` | Pendaftaran VPS (review admin) | 3 |
| `service_registrations` | Pendaftaran Domain & Hosting (kolom `type`) | 6 |
| `notifications` | Lonceng in-app (kind: register / vps_* / service_*) | 17 |
| `telegram_webhooks` | 2 webhook tetap: `tiket`, `jaringan` | 2 |
| `dashboard_widgets` | Pilihan widget dashboard admin | 8 |
| `migrations` / `jobs` / `job_batches` / `failed_jobs` / `password_reset_tokens` | Bawaan framework Laravel | — |

## Relasi inti

```
users 1─N tickets               (tickets.user_id, tickets.assignee_id → users)
users 1─N vps_requests          (vps_requests.user_id, reviewed_by → users)
users 1─N service_registrations (user_id, reviewed_by → users)
users 1─N notifications         (notifiable morph → users)
users 1─N ticket_activities     (ticket_activities.user_id)
tickets 1─N ticket_activities   (ticket_id)
devices 1─N device_metrics      (device_id)
devices 1─N interface_metrics   (device_id)
```

FK `user_id` memakai `cascadeOnDelete`, `reviewed_by`/`assignee_id` memakai
`nullOnDelete`. Kode unik per hari: `TKT-`/`VPS-`/`DOM-`/`HST-YYYYMMDD-NNNN` (UNIQUE).

## Struktur per tabel

### cache — 26 baris

| Kolom | Tipe | Null | Key | Default/Extra |
|---|---|---|---|---|
| `key` | varchar(255) | no | PRI | — |
| `value` | mediumtext | no | — | — |
| `expiration` | int(11) | no | MUL | — |

Index: cache_expiration_index (expiration) · PRIMARY KEY (key)

### cache_locks — 1 baris

| Kolom | Tipe | Null | Key | Default/Extra |
|---|---|---|---|---|
| `key` | varchar(255) | no | PRI | — |
| `owner` | varchar(255) | no | — | — |
| `expiration` | int(11) | no | MUL | — |

Index: cache_locks_expiration_index (expiration) · PRIMARY KEY (key)

### dashboard_widgets — 8 baris

| Kolom | Tipe | Null | Key | Default/Extra |
|---|---|---|---|---|
| `id` | varchar(32) | no | PRI | — |
| `label` | varchar(100) | no | — | — |
| `description` | varchar(255) | YES | — | NULL |
| `enabled` | tinyint(1) | no | — | '0' |
| `sort_order` | smallint(5) unsigned | no | — | '0' |
| `created_at` | timestamp | YES | — | NULL |
| `updated_at` | timestamp | YES | — | NULL |

Index: PRIMARY KEY (id)

### devices — 12 baris

| Kolom | Tipe | Null | Key | Default/Extra |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PRI | — auto_increment |
| `name` | varchar(100) | no | — | — |
| `host` | varchar(255) | no | — | — |
| `hosts` | longtext | YES | — | NULL |
| `type` | varchar(32) | no | — | 'router' |
| `location` | varchar(150) | YES | — | NULL |
| `enabled` | tinyint(1) | no | MUL | '1' |
| `use_icmp` | tinyint(1) | no | — | '1' |
| `use_snmp` | tinyint(1) | no | — | '0' |
| `use_routeros` | tinyint(1) | no | — | '0' |
| `snmp_version` | varchar(4) | YES | — | NULL |
| `snmp_community` | varchar(128) | YES | — | NULL |
| `snmp_port` | int(11) | YES | — | NULL |
| `routeros_port` | int(11) | no | — | '8728' |
| `routeros_user` | varchar(64) | no | — | 'admin' |
| `routeros_password` | varchar(255) | no | — | '' |
| `routeros_timeout` | int(11) | no | — | '5' |
| `status` | varchar(16) | no | — | 'unknown' |
| `last_checked_at` | timestamp | YES | — | NULL |
| `last_rtt_ms` | double | YES | — | NULL |
| `last_cpu` | int(11) | YES | — | NULL |
| `last_uptime_sec` | bigint(20) unsigned | YES | — | NULL |
| `created_at` | timestamp | YES | — | NULL |
| `updated_at` | timestamp | YES | — | NULL |

Index: devices_enabled_index (enabled) · PRIMARY KEY (id)

### device_metrics — 23345 baris

| Kolom | Tipe | Null | Key | Default/Extra |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PRI | — auto_increment |
| `device_id` | bigint(20) unsigned | no | MUL | — |
| `checked_at` | timestamp | no | MUL | — |
| `status` | varchar(16) | no | — | 'unknown' |
| `icmp_ok` | tinyint(1) | YES | — | NULL |
| `icmp_rtt_ms` | double | YES | — | NULL |
| `icmp_error` | varchar(255) | YES | — | NULL |
| `snmp_ok` | tinyint(1) | YES | — | NULL |
| `cpu` | int(11) | YES | — | NULL |
| `uptime_sec` | bigint(20) unsigned | YES | — | NULL |
| `board_name` | varchar(100) | YES | — | NULL |
| `rx_bps` | double | YES | — | NULL |
| `tx_bps` | double | YES | — | NULL |
| `snmp_error` | varchar(255) | YES | — | NULL |
| `routeros_ok` | tinyint(1) | YES | — | NULL |
| `routeros_cpu` | int(11) | YES | — | NULL |
| `routeros_error` | varchar(255) | YES | — | NULL |

Index: device_metrics_checked_at_index (checked_at) · device_metrics_device_id_checked_at_index (device_id, checked_at) · PRIMARY KEY (id)

### failed_jobs — 0 baris

| Kolom | Tipe | Null | Key | Default/Extra |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PRI | — auto_increment |
| `uuid` | varchar(255) | no | UNI | — |
| `connection` | text | no | — | — |
| `queue` | text | no | — | — |
| `payload` | longtext | no | — | — |
| `exception` | longtext | no | — | — |
| `failed_at` | timestamp | no | — | 'current_timestamp()' |

Index: UNIQUE failed_jobs_uuid_unique (uuid) · PRIMARY KEY (id)

### interface_metrics — 363809 baris

| Kolom | Tipe | Null | Key | Default/Extra |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PRI | — auto_increment |
| `device_id` | bigint(20) unsigned | no | MUL | — |
| `checked_at` | timestamp | no | MUL | — |
| `if_name` | varchar(64) | no | — | — |
| `oper_status` | varchar(16) | YES | — | NULL |
| `speed` | bigint(20) unsigned | YES | — | NULL |
| `rx_bps` | double | YES | — | NULL |
| `tx_bps` | double | YES | — | NULL |
| `rx_bytes` | bigint(20) unsigned | YES | — | NULL |
| `tx_bytes` | bigint(20) unsigned | YES | — | NULL |

Index: interface_metrics_checked_at_index (checked_at) · interface_metrics_device_id_checked_at_index (device_id, checked_at) · interface_metrics_device_id_if_name_checked_at_index (device_id, if_name, checked_at) · PRIMARY KEY (id)

### jobs — 0 baris

| Kolom | Tipe | Null | Key | Default/Extra |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PRI | — auto_increment |
| `queue` | varchar(255) | no | MUL | — |
| `payload` | longtext | no | — | — |
| `attempts` | tinyint(3) unsigned | no | — | — |
| `reserved_at` | int(10) unsigned | YES | — | NULL |
| `available_at` | int(10) unsigned | no | — | — |
| `created_at` | int(10) unsigned | no | — | — |

Index: jobs_queue_index (queue) · PRIMARY KEY (id)

### job_batches — 0 baris

| Kolom | Tipe | Null | Key | Default/Extra |
|---|---|---|---|---|
| `id` | varchar(255) | no | PRI | — |
| `name` | varchar(255) | no | — | — |
| `total_jobs` | int(11) | no | — | — |
| `pending_jobs` | int(11) | no | — | — |
| `failed_jobs` | int(11) | no | — | — |
| `failed_job_ids` | longtext | no | — | — |
| `options` | mediumtext | YES | — | NULL |
| `cancelled_at` | int(11) | YES | — | NULL |
| `created_at` | int(11) | no | — | — |
| `finished_at` | int(11) | YES | — | NULL |

Index: PRIMARY KEY (id)

### migrations — 20 baris

| Kolom | Tipe | Null | Key | Default/Extra |
|---|---|---|---|---|
| `id` | int(10) unsigned | no | PRI | — auto_increment |
| `migration` | varchar(255) | no | — | — |
| `batch` | int(11) | no | — | — |

Index: PRIMARY KEY (id)

### notifications — 17 baris

| Kolom | Tipe | Null | Key | Default/Extra |
|---|---|---|---|---|
| `id` | uuid | no | PRI | — |
| `type` | varchar(255) | no | — | — |
| `notifiable_type` | varchar(255) | no | MUL | — |
| `notifiable_id` | bigint(20) unsigned | no | — | — |
| `data` | text | no | — | — |
| `read_at` | timestamp | YES | — | NULL |
| `created_at` | timestamp | YES | — | NULL |
| `updated_at` | timestamp | YES | — | NULL |

Index: notifications_notifiable_type_notifiable_id_index (notifiable_type, notifiable_id) · notifications_notifiable_type_notifiable_id_read_at_index (notifiable_type, notifiable_id, read_at) · PRIMARY KEY (id)

### password_reset_tokens — 0 baris

| Kolom | Tipe | Null | Key | Default/Extra |
|---|---|---|---|---|
| `email` | varchar(255) | no | PRI | — |
| `token` | varchar(255) | no | — | — |
| `created_at` | timestamp | YES | — | NULL |

Index: PRIMARY KEY (email)

### service_registrations — 6 baris

| Kolom | Tipe | Null | Key | Default/Extra |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PRI | — auto_increment |
| `user_id` | bigint(20) unsigned | no | MUL | — |
| `type` | varchar(16) | no | MUL | — |
| `code` | varchar(24) | YES | UNI | NULL |
| `name` | varchar(100) | no | — | — |
| `nip` | varchar(30) | no | — | — |
| `jabatan` | varchar(100) | no | — | — |
| `instansi` | varchar(150) | no | — | — |
| `domain_name` | varchar(255) | YES | — | NULL |
| `hosting_package` | varchar(64) | YES | — | NULL |
| `duration` | smallint(5) unsigned | YES | — | NULL |
| `purpose` | text | no | — | — |
| `supporting_document` | varchar(255) | YES | — | NULL |
| `supporting_document_uploaded_at` | timestamp | YES | — | NULL |
| `status` | varchar(16) | no | MUL | 'pending' |
| `admin_note` | text | YES | — | NULL |
| `reviewed_by` | bigint(20) unsigned | YES | MUL | NULL |
| `reviewed_at` | timestamp | YES | — | NULL |
| `created_at` | timestamp | YES | MUL | NULL |
| `updated_at` | timestamp | YES | — | NULL |

Index: PRIMARY KEY (id) · UNIQUE service_registrations_code_unique (code) · service_registrations_created_at_index (created_at) · service_registrations_reviewed_by_foreign (reviewed_by) · service_registrations_status_index (status) · service_registrations_type_status_index (type, status) · service_registrations_user_id_foreign (user_id)

### sessions — 171 baris

| Kolom | Tipe | Null | Key | Default/Extra |
|---|---|---|---|---|
| `id` | varchar(255) | no | PRI | — |
| `user_id` | bigint(20) unsigned | YES | MUL | NULL |
| `ip_address` | varchar(45) | YES | — | NULL |
| `user_agent` | text | YES | — | NULL |
| `payload` | longtext | no | — | — |
| `last_activity` | int(11) | no | MUL | — |

Index: PRIMARY KEY (id) · sessions_last_activity_index (last_activity) · sessions_user_id_index (user_id)

### telegram_webhooks — 2 baris

| Kolom | Tipe | Null | Key | Default/Extra |
|---|---|---|---|---|
| `id` | varchar(16) | no | PRI | — |
| `label` | varchar(50) | no | — | — |
| `description` | varchar(255) | YES | — | NULL |
| `enabled` | tinyint(1) | no | — | '0' |
| `bot_token` | varchar(255) | no | — | '' |
| `chat_id` | varchar(64) | no | — | '' |
| `created_at` | timestamp | YES | — | NULL |
| `updated_at` | timestamp | YES | — | NULL |

Index: PRIMARY KEY (id)

### tickets — 4 baris

| Kolom | Tipe | Null | Key | Default/Extra |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PRI | — auto_increment |
| `code` | varchar(24) | no | UNI | — |
| `title` | varchar(200) | no | — | — |
| `category` | varchar(50) | no | — | 'Jaringan' |
| `description` | text | no | — | — |
| `location` | varchar(200) | YES | — | NULL |
| `lat` | decimal(10,7) | YES | — | NULL |
| `lng` | decimal(10,7) | YES | — | NULL |
| `photo` | varchar(255) | YES | — | NULL |
| `status` | varchar(16) | no | MUL | 'open' |
| `priority` | varchar(16) | no | — | 'normal' |
| `user_id` | bigint(20) unsigned | YES | MUL | NULL |
| `reporter_name` | varchar(100) | YES | — | NULL |
| `reporter_contact` | varchar(100) | YES | — | NULL |
| `assignee_id` | bigint(20) unsigned | YES | MUL | NULL |
| `resolved_at` | timestamp | YES | — | NULL |
| `created_at` | timestamp | YES | MUL | NULL |
| `updated_at` | timestamp | YES | — | NULL |

Index: PRIMARY KEY (id) · tickets_assignee_id_foreign (assignee_id) · UNIQUE tickets_code_unique (code) · tickets_created_at_index (created_at) · tickets_status_index (status) · tickets_user_id_foreign (user_id)

### ticket_activities — 8 baris

| Kolom | Tipe | Null | Key | Default/Extra |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PRI | — auto_increment |
| `ticket_id` | bigint(20) unsigned | no | MUL | — |
| `user_id` | bigint(20) unsigned | YES | MUL | NULL |
| `action` | varchar(32) | no | — | — |
| `old_value` | varchar(50) | YES | — | NULL |
| `new_value` | varchar(50) | YES | — | NULL |
| `note` | text | YES | — | NULL |
| `created_at` | timestamp | no | — | 'current_timestamp()' |

Index: PRIMARY KEY (id) · ticket_activities_ticket_id_index (ticket_id) · ticket_activities_user_id_foreign (user_id)

### users — 3 baris

| Kolom | Tipe | Null | Key | Default/Extra |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PRI | — auto_increment |
| `name` | varchar(255) | no | — | — |
| `email` | varchar(255) | no | UNI | — |
| `email_verified_at` | timestamp | YES | — | NULL |
| `password` | varchar(255) | no | — | — |
| `role` | varchar(16) | no | — | 'user' |
| `status` | varchar(16) | no | — | 'approved' |
| `remember_token` | varchar(100) | YES | — | NULL |
| `created_at` | timestamp | YES | — | NULL |
| `updated_at` | timestamp | YES | — | NULL |

Index: PRIMARY KEY (id) · UNIQUE users_email_unique (email)

### vps_requests — 3 baris

| Kolom | Tipe | Null | Key | Default/Extra |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PRI | — auto_increment |
| `code` | varchar(24) | YES | UNI | NULL |
| `user_id` | bigint(20) unsigned | no | MUL | — |
| `name` | varchar(100) | no | — | — |
| `nip` | varchar(30) | no | — | — |
| `jabatan` | varchar(100) | no | — | — |
| `instansi` | varchar(150) | no | — | — |
| `cores` | smallint(5) unsigned | no | — | — |
| `ram_gb` | smallint(5) unsigned | no | — | — |
| `public_ips` | tinyint(3) unsigned | no | — | — |
| `os` | varchar(50) | YES | — | NULL |
| `os_other` | varchar(100) | YES | — | NULL |
| `ports` | longtext | no | — | — |
| `custom_ports` | varchar(255) | YES | — | NULL |
| `purpose` | text | no | — | — |
| `supporting_document` | varchar(255) | YES | — | NULL |
| `supporting_document_uploaded_at` | timestamp | YES | — | NULL |
| `status` | varchar(16) | no | MUL | 'pending' |
| `admin_note` | text | YES | — | NULL |
| `reviewed_by` | bigint(20) unsigned | YES | MUL | NULL |
| `reviewed_at` | timestamp | YES | — | NULL |
| `credential_file` | varchar(255) | YES | — | NULL |
| `credential_uploaded_at` | timestamp | YES | — | NULL |
| `created_at` | timestamp | YES | MUL | NULL |
| `updated_at` | timestamp | YES | — | NULL |

Index: PRIMARY KEY (id) · UNIQUE vps_requests_code_unique (code) · vps_requests_created_at_index (created_at) · vps_requests_reviewed_by_foreign (reviewed_by) · vps_requests_status_index (status) · vps_requests_user_id_foreign (user_id)
