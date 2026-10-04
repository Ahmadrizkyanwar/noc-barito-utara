# Website Monitoring NOC — Kabupaten Barito Utara

Aplikasi monitoring jaringan **Network Operation Center (NOC)** Diskominfosandi
Kabupaten Barito Utara. Dibangun dengan **Laravel 12 + Inertia + Vue 3 + Tailwind 4**.

## Struktur Halaman

| Halaman | Route | Akses |
|---|---|---|
| Landing (hero + status live) | `/` | publik |
| Login | `/login` | publik (guest) |
| Laporan Gangguan | `/lapor` | publik |
| 404 | semua route tak dikenal | publik |
| **User Dashboard** (lapor + tiket saya) | `/dashboard` | login `role:user` |
| Pendaftaran VPS | `/vps` | login (akun approved) |
| Pendaftaran Domain | `/domain` | login (akun approved) |
| Pendaftaran Hosting | `/hosting` | login (akun approved) |
| **Admin Dashboard** (ringkasan + grafik) | `/admin` | login `role:admin` |

### Menu admin

- **Jaringan** `/admin/jaringan` — CRUD perangkat, status SNMP/ICMP/RouterOS,
  cek manual, detail + grafik (CPU, RTT, RX/TX) per rentang.
- **Layanan** `/admin/layanan` — ticketing laporan gangguan **+** pendaftaran
  VPS/domain/hosting (filter, detail, ubah status, catatan, foto).
- **Review Pendaftaran** `/admin/pendaftaran` — SATU halaman untuk review
  Request VPS, Domain & Hosting (filter tipe/status, approve/tolak + catatan,
  dokumen pendukung, upload kredensial VPS). URL lama `/admin/vps` tetap ada
  (terfilter ke VPS).
- **Pengaturan → Pengguna** `/admin/pengaturan/user` — CRUD user (admin/user).
- **Pengaturan → Webhook Telegram** `/admin/pengaturan/webhook` — 2 entri tetap
  (Tiket & Jaringan): toggle, bot token, chat id, uji kirim.
- **Dashboard** `/admin` — **Sesuaikan Widget**: admin memilih infografik yang
  tampil (8 widget tetap di tabel `dashboard_widgets`): kartu status, tren
  trafik 24 jam, grafik CPU/RTT, donut status, peringkat trafik perangkat,
  top interface, daftar perangkat, tiket terbaru.

## Fitur Pemantauan

| Metode | Implementasi | Data |
|---|---|---|
| **ICMP** | binary `ping` (iputils) via Process facade, tanpa shell | keterjangkauan + RTT |
| **SNMP** | ekstensi PHP `snmp` (v1/v2c/v3) | CPU, uptime, counter trafik → bps |
| **RouterOS API** | `evilfreelancer/routeros-api-php` (ext-sockets) | CPU, uptime, board |

- **Trafik per interface** → tabel `interface_metrics`: SNMP ifTable
  (ifDescr/ifOperStatus/ifSpeed/ifInOctets/ifOutOctets), fallback RouterOS
  `/interface/print` (rx-byte/tx-byte). Halaman **Infografik Trafik**
  (`/admin/jaringan/{id}/trafik`): hero RX/TX + peak, donut share,
  area chart bertumpuk per top-interface (1j/6j/24j/7h), kartu per interface
  (util% vs kecepatan link + sparkline SVG), tabel ranking — auto-refresh 15 dtk.
- Poller `monitor:poll` tiap **30 detik** (`POLL_INTERVAL`) — scheduler via
  `schedule:work` (tanpa cron host).
- **Notifikasi Telegram** saat transisi status up↔down (poll pertama senyap).
- Retensi metrics **30 hari** (`METRICS_RETENTION_DAYS`) — `metrics:prune` harian 03:00.
- Semua probe **gagal-tertutup**: error dikembalikan sebagai data, tidak
  menghentikan siklus poller.

## Tech Stack

- Laravel 12 · Inertia 3 · Vue 3 · Tailwind 4 (Vite 7) · Chart.js · Ziggy
- MariaDB (container existing `mikrotik-monitor-db`, database `noc_barutara`)
- Docker: 1 container = nginx + php-fpm + `schedule:work` → port **3003**

## Menjalankan

```bash
cp .env.example .env          # isi APP_KEY (php artisan key:generate),
                              # DB_PASSWORD, WEB_USERNAME/WEB_PASSWORD
docker compose up -d --build  # → http://localhost:3003
```

Migrasi + seed jalan otomatis di entrypoint (admin awal dari `WEB_USERNAME`/`WEB_PASSWORD`).

Login default: `admin@noc.baritoutarakab.go.id` (atau `${WEB_USERNAME}@noc.baritoutarakab.go.id`)
+ password sesuai `WEB_PASSWORD`.

### Development (tanpa Docker)

```bash
composer install && npm install && npm run build
php artisan migrate --seed
php artisan serve             # atau: composer run dev
php artisan test              # 178 tests
```

## Konfigurasi Environment

| Var | Default | Keterangan |
|---|---|---|
| `DB_*` | — | MariaDB `noc_barutara` (container `mariadb`) |
| `POLL_INTERVAL` | `30` | interval poll (detik) |
| `METRICS_RETENTION_DAYS` | `30` | retensi `device_metrics` |
| `INTERFACE_RETENTION_DAYS` | `7` | retensi `interface_metrics` (per interface) |
| `SNMP_VERSION/COMMUNITY/PORT/TIMEOUT` | `2c/public/161/3000` | default SNMP (bisa ditimpa per-perangkat) |
| `ICMP_TIMEOUT` | `2` | timeout ping (detik) |
| `WEB_USERNAME` / `WEB_PASSWORD` | — | seed admin awal (password min. 8) |
| `TELEGRAM_TIKET_*` / `TELEGRAM_JARINGAN_*` | — | seed awal webhook (bisa diedit dari UI) |
| `SESSION_SECURE_COOKIE` | `false` | `true` hanya bila diakses via HTTPS |
| `TRUST_PROXIES` | `192.168.101.42` | IP NPM terpercaya — **wajib** agar skema https terbaca |

## Akses via Domain

Aplikasi dipublikasikan lewat **Nginx Proxy Manager** (`192.168.101.42`):

```
https://kuma.baritoutarakab.go.id  →  http://192.168.101.110:3003
```

Wajib di `.env`:

```
APP_URL=https://kuma.baritoutarakab.go.id
TRUST_PROXIES=192.168.101.42      # tanpa ini asset dirender http:// → diblokir
                                   # browser (mixed content) = halaman gagal
SESSION_SECURE_COOKIE=false       # true hanya bila semua akses sudah https
```

Setelah mengubah `.env`: `docker compose up -d --force-recreate`.

### Gagal dari jaringan lokal (LAN)?

`*.baritoutarakab.go.id` resolve ke IP publik `36.67.17.203`. Bila dari LAN
port 80/443 tidak merespons (diuji: `traceroute` sampai, TCP timeout), itu
**NAT loopback/hairpin di router** — bukan aplikasi. Solusi:

1. **DNS lokal** (server DNS kantor / Pi-hole): A record
   `kuma.baritoutarakab.go.id → 192.168.101.42`, atau
2. **File hosts** di komputer client:
   `192.168.101.42 kuma.baritoutarakab.go.id` (Linux: `/etc/hosts`,
   Windows: `C:\Windows\System32\drivers\etc\hosts`), atau
3. Tambahkan rule **NAT hairpin** di router untuk IP `36.67.17.203` (port 80/443).

Cara 1/2 sudah teruji: NPM menjawab benar dengan SNI `kuma.…` dari IP lokal.

## Pengujian

```bash
php artisan test
# 178 tests, 925 assertions — auth, guard role, tiket, VPS, domain/hosting, device, webhook, telegram, probe
```

Probe ICMP diuji dengan stub deterministik (image test tidak punya binary
`ping` + `cap_net_raw`); jalur SNMP fail-closed diuji di image tanpa ekstensi.

## Dokumentasi API

Endpoint HTTP lengkap (publik, auth, user, admin/operator, JSON payload,
validasi, rate limit, integrasi Telegram) ada di **[`docs/API.md`](docs/API.md)**.

Ringkas:

| Kebutuhan | Endpoint |
|---|---|
| Status ringkas (publik, JSON) | `GET /status` |
| Laporan gangguan | `POST /lapor` |
| Request VPS | `POST /vps` |
| Grafik metrik perangkat (JSON) | `GET /admin/jaringan/{id}/metrics?hours=24` |
| Infografik trafik per interface (JSON) | `GET /admin/jaringan/{id}/interfaces?hours=1` |
| Uji kirim webhook Telegram | `POST /admin/pengaturan/webhook/{tiket\|jaringan}/test` |

## Struktur Kode

```
app/
├── Console/Commands/{MonitorPoll,MetricsPrune}.php
├── Http/
│   ├── Controllers/{Landing,Auth,Dashboard,Report}Controller.php
│   ├── Controllers/Admin/{Device,Ticket,User,Webhook}Controller.php
│   └── Middleware/{HandleInertiaRequests,EnsureRole}.php
├── Models/{User,Device,DeviceMetric,Ticket,TicketActivity,TelegramWebhook}.php
└── Services/
    ├── Network/{PingProbe,SnmpProbe,RouterOsProbe,MonitorPoller}.php
    ├── Telegram/Notifier.php
    └── Tickets/TicketService.php
resources/js/
├── Layouts/{PublicLayout,AdminLayout,UserLayout}.vue
└── Pages/{Landing,Login,Laporan,Dashboard}.vue + Pages/Admin/*.vue (Jaringan, Trafik, …)
docker/{nginx.conf, entrypoint.sh}
```
