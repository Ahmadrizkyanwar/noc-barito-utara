# Dokumentasi API — NOC Barito Utara

Dokumentasi resmi endpoint HTTP aplikasi **Website Monitoring NOC Kabupaten Barito Utara**
(Laravel 12 · Inertia 3 · Vue 3 · MariaDB).

| | |
|---|---|
| Base URL (produksi) | `https://kuma.baritoutarakab.go.id` |
| Base URL (lokal) | `http://localhost:3003` |
| Health check | `GET /up` → `200` |
| Terakhir diperbarui | 4 Oktober 2026 |

---

## 1. Ringkasan & prinsip

Sistem ini **bukan REST API terpisah** — tidak ada `routes/api.php`, tidak ada Sanctum/token.
Seluruh endpoint berada di **`routes/web.php`** dan memakai **autentikasi berbasis sesi (cookie)**
dengan proteksi CSRF. Frontend memakai Inertia.js; sebagian endpoint juga mengembalikan
JSON murni sehingga bisa dipakai integrasi pihak ketiga (script, monitoring, dsb).

Karakteristik:

- **Satu file route**: `routes/web.php` (semua endpoint di bawah).
- **Auth**: sesi `web` guard — cookie `noc-barito-utara-session` (lifetime 1440 menit) + CSRF token.
- **Role**: `admin`, `operator`, `user` — via middleware `role:admin`, `role:admin,operator`.
- **Output**: HTML (halaman Inertia), **redirect 302 + flash**, atau **JSON** tergantung header permintaan (lihat §3).
- **Upload file**: selalu `multipart/form-data`, disimpan ke disk `public` (`storage/app/public`).
- **Notifikasi keluar**: Telegram Bot API (lihat §9).

---

## 2. Autentikasi & CSRF

### 2.1 Login

```http
POST /login
Content-Type: application/json
Accept: application/json
X-Requested-With: XMLHttpRequest
X-XSRF-TOKEN: <token dari cookie XSRF-TOKEN>

{ "email": "admin@noc.baritoutarakab.go.id", "password": "********", "remember": false }
```

Respons sukses: **302** ke `/admin` (admin), `/admin/registrasi` (operator), atau `/dashboard` (user).
Simpan kedua cookie yang dikembalikan: `noc-barito-utara-session` dan `XSRF-TOKEN`.

Gagal: **422** (JSON) / **302 + $errors** (HTML) dengan pesan:

| Kondisi | Pesan (`field: email`) |
|---|---|
| Salah sandi | `Email atau password salah.` |
| Registrasi ditolak | `Registrasi Anda ditolak oleh admin. Silakan hubungi admin untuk informasi.` |
| Email belum diverifikasi | `Email Anda belum diverifikasi. Cek inbox email untuk tautan verifikasi…` |

Catatan: akun berstatus `pending` **boleh login**, tetapi fitur Request VPS tetap terkunci
(gate `canRequestVps()` = status `approved`).

### 2.2 CSRF token

Token dikirim lewat salah satu dari (berlaku untuk **semua** method non-GET, termasuk request JSON):

1. body `_token`
2. header **`X-CSRF-TOKEN`** (nilai dari `<meta name="csrf-token">`)
3. header **`X-XSRF-TOKEN`** = nilai cookie `XSRF-TOKEN` setelah di-URL-decode

Tidak ada pengecualian CSRF di aplikasi ini.

### 2.3 Logout

```http
POST /logout        → 302 → /login  (sesi diinvalide, token CSRF diregenerasi)
```

---

## 3. Content negotiation: HTML vs JSON vs Inertia

| Header yang dikirim | Hasil |
|---|---|
| `Accept: text/html` (default browser) | Halaman HTML (render Inertia) / redirect 302 |
| `Accept: application/json` | **JSON** untuk error (422/401/403/404/419/429) **dan** untuk endpoint yang punya cabang `expectsJson()` |
| `X-Inertia: true` | Objek halaman JSON `{component, props, url, version}` |
| `X-Inertia: true` + versi asset tidak cocok | **409** (build ulang aset) |
| Kombinasi `X-Requested-With: XMLHttpRequest` + `Accept: */*` atau `application/json` | dianggap JSON |

> **Penting:** sebagian endpoint **tidak pernah** mengembalikan JSON sukses
> (mis. `POST /notifications/*` selalu 302, `GET /admin/layanan/{ticket}` adalah halaman Inertia).
> Lihat tabel indeks §5.

### Kode status

| Kode | Kapan | Body (JSON) |
|---|---|---|
| `200` / `201` | Sukses endpoint JSON | `{"ok": true, …}` |
| `302` / `303` | Sukses non-JSON (redirect; Inertia meng-upgrade ke 303 untuk PUT/PATCH/DELETE) | kosong, header `Location` |
| `401` | Belum login, request JSON | `{"message":"Unauthenticated."}` |
| `403` | Role tidak cocok / signature tidak valid / akses bukan pemilik | `{"message":"Anda tidak memiliki akses ke halaman ini."}` |
| `404` | Model tidak ada / path file tidak valid / notifikasi milik orang lain | `{"message":"…"}` |
| `419` | CSRF tidak valid/kedaluwarsa | `{"message":"…"}` |
| `422` | Validasi gagal | `{"message":"…","errors":{"field":["pesan"]}}` |
| `429` | Melebihi rate limit | `{"message":"Too Many Attempts."}` |
| `500` | Exception tak tertangani | `{"message":"Server Error"}` |

Untuk request HTML, kondisi yang sama ditampilkan sebagai halaman error/redirect + flash
(`session('success')` / `session('error')`).

---

## 4. Rate limit

| Endpoint | Limit |
|---|---|
| `POST /registrasi` | 10 / menit |
| `POST /lapor` | 10 / menit |
| `POST /vps` | 10 / menit |
| `POST /email/resend` | 6 / menit |
| `GET /email/verify/{id}/{hash}` | 6 / menit |
| Lainnya | tanpa limit |

Key: per-IP untuk route publik, per-user-id untuk route login.

---

## 5. Indeks endpoint

### 5.1 Publik (tanpa login)

| Method | URI | Nama route | Respons |
|---|---|---|---|
| GET | `/` | `landing` | HTML · komponen `Landing` |
| GET | `/status` | `landing.status` | **JSON selalu** (§6.1) |
| GET | `/lapor` | `lapor.index` | HTML · `Laporan` |
| POST | `/lapor` | `lapor.store` | 302 → `/lapor` + flash (throttle 10/mnt) |
| GET | `/uploads/{path}` | `ticket.photo` | Stream file (guard path-traversal) / 404 |

### 5.2 Auth & registrasi

| Method | URI | Nama route | Auth | Respons |
|---|---|---|---|---|
| GET | `/login` | `login` | guest | HTML · `Login` |
| POST | `/login` | — | guest | 302 → dashboard (lihat §2.1) |
| POST | `/logout` | `logout` | auth | 302 → `/login` |
| GET | `/registrasi` | `register` | guest | HTML · `Register` |
| POST | `/registrasi` | — | guest · throttle 10/mnt | 302 → `/email/verify` |
| GET | `/email/verify` | `verification.notice` | guest | HTML · `VerifyEmail` |
| POST | `/email/resend` | `verification.resend` | guest · throttle 6/mnt | 302 back() |
| GET | `/email/verify/{id}/{hash}` | `verification.verify` | **signed** · throttle 6/mnt | 302 → `/login` |

### 5.3 Semua user login (`auth`)

| Method | URI | Nama route | Respons |
|---|---|---|---|
| GET | `/dashboard` | `dashboard` | HTML · `Dashboard` (bentuk props admin/user berbeda) |
| GET | `/vps` | `vps.index` | HTML · `VpsRequest` (`canRequest`, `accountStatus`, `ports`, `operatingSystems`) |
| POST | `/vps` | `vps.store` | 302 → `/vps` + flash (throttle 10/mnt) · detail §7.3 |
| GET | `/vps/{id}/credentials` | `vps.credentials` | Download biner · pemilik/reviewer saja · 403/404 |
| GET | `/vps/{id}/document` | `vps.document` | Download biner · pemilik/reviewer saja · 403/404 |
| GET | `/domain` | `domain.index` | HTML · `ServiceRegistration` (type `domain`) |
| POST | `/domain` | `domain.store` | 302 → `/domain` + flash (throttle 10/mnt) · §7.3a |
| GET | `/hosting` | `hosting.index` | HTML · `ServiceRegistration` (type `hosting`) |
| POST | `/hosting` | `hosting.store` | 302 → `/hosting` + flash (throttle 10/mnt) · §7.3a |
| GET | `/service/{id}/document` | `service.document` | Download biner dokumen pendukung · pemilik/reviewer · 403/404 |
| POST | `/notifications/{id}/read` | `notifications.read` | **302 back() selalu** · 404 bila bukan milik sendiri |
| POST | `/notifications/read-all` | `notifications.readAll` | **302 back() selalu** |

### 5.4 Admin + Operator (`role:admin,operator`, prefix `/admin`)

| Method | URI | Nama route | Respons |
|---|---|---|---|
| GET | `/admin/registrasi` | `admin.registrations.index` | HTML · `Admin/Registrasi` · `?status=pending\|approved\|rejected`, paginasi 20 |
| PATCH | `/admin/registrasi/{user}/status` | `admin.registrations.status` | 302 back() + flash · body `status` (approved/rejected) |
| GET | `/admin/vps` | `admin.vps.index` | HTML · `Admin/ServiceReview` (halaman gabungan, default `?type=vps`), paginasi 20 |
| PATCH | `/admin/vps/{id}/status` | `admin.vps.status` | 302 back() + flash · body `status`, `admin_note` |
| POST | `/admin/vps/{id}/credentials` | `admin.vps.credentials.upload` | 302 back() + flash · multipart `credential` (harus status approved) |
| GET | `/admin/pendaftaran` | `admin.services.index` | HTML · `Admin/ServiceReview` — **daftar gabungan VPS + Domain + Hosting**, `?type=vps\|domain\|hosting`, `?status=`, paginasi 20 |
| PATCH | `/admin/pendaftaran/{id}/status` | `admin.services.status` | 302 back() + flash · body `status` (approved/rejected), `admin_note` |

### 5.5 Admin saja (`role:admin`, prefix `/admin`)

| Method | URI | Nama route | Respons |
|---|---|---|---|
| GET | `/admin` | `admin.dashboard` | HTML · `Dashboard` (props admin) |
| PATCH | `/admin/dashboard/widgets` | `admin.dashboard.widgets.update` | 302 back() + flash · body `enabled[]` |
| GET | `/admin/jaringan` | `admin.devices.index` | HTML · `Admin/Jaringan` · `?status=`, `?q=` |
| POST | `/admin/jaringan` | `admin.devices.store` | 302 → `admin.devices.show` · §7.5 |
| GET | `/admin/jaringan/{device}` | `admin.devices.show` | HTML · `Admin/Perangkat` (device + 288 metrics terakhir) |
| PUT | `/admin/jaringan/{device}` | `admin.devices.update` | 302 back() + flash · §7.5 |
| DELETE | `/admin/jaringan/{device}` | `admin.devices.destroy` | **JSON** `{ok,message}` / 302 back() |
| POST | `/admin/jaringan/{device}/check` | `admin.devices.check` | **JSON** `{ok,status,device}` / 302 back() |
| GET | `/admin/jaringan/{device}/metrics` | `admin.devices.metrics` | **JSON selalu** (§6.2) · `?hours=1..168` |
| GET | `/admin/jaringan/{device}/trafik` | `admin.devices.traffic` | HTML · `Admin/Trafik` |
| GET | `/admin/jaringan/{device}/interfaces` | `admin.devices.interfaces` | **JSON selalu** (§6.3) · `?hours=1..168` |
| GET | `/admin/layanan` | `admin.tickets.index` | HTML · `Admin/Layanan` · `?status=`, `?q=`, paginasi 15 |
| GET | `/admin/layanan/{ticket}` | `admin.tickets.show` | HTML · `Admin/TiketDetail` (JSON hanya dengan `X-Inertia: true`) |
| PATCH | `/admin/layanan/{ticket}/status` | `admin.tickets.status` | **JSON** `{ok,changed,ticket}` / 302 |
| POST | `/admin/layanan/{ticket}/note` | `admin.tickets.note` | **JSON** `{ok,ticket}` / 302 |
| POST | `/admin/layanan/{ticket}/assign` | `admin.tickets.assign` | **JSON** `{ok,ticket}` / 302 |
| GET | `/admin/pengaturan/user` | `admin.users.index` | HTML · `Admin/Pengguna` |
| POST | `/admin/pengaturan/user` | `admin.users.store` | **201 JSON** `{ok,user}` / 302 |
| PUT | `/admin/pengaturan/user/{user}` | `admin.users.update` | **200 JSON** `{ok,user}` / 302 · 422 self-demotion |
| DELETE | `/admin/pengaturan/user/{user}` | `admin.users.destroy` | **200 JSON** `{ok,message}` / 302 · 422 self-delete |
| GET | `/admin/pengaturan/webhook` | `admin.webhooks.index` | HTML · `Admin/Webhook` (2 entri tetap) |
| PUT | `/admin/pengaturan/webhook/{id}` | `admin.webhooks.update` | **200 JSON** `{ok,webhook}` / 302 · 404 bila id tak dikenal |
| POST | `/admin/pengaturan/webhook/{id}/test` | `admin.webhooks.test` | **JSON selalu** 200/422 (§6.6) |

> `operator` hanya berhak atas 5 route di §5.4; route §5.5 lain → **403**.

---

## 6. Endpoint JSON (payload eksak)

### 6.1 `GET /status` — status ringkas publik

Agregat saja; **tidak membocorkan nama/IP perangkat**.

```json
{
  "total": 12,
  "up": 10,
  "down": 1,
  "unknown": 1,
  "uptime_pct": 83.3,
  "open_tickets": 3,
  "updated_at": "2026-10-04T10:15:00+07:00"
}
```

`uptime_pct` = `null` bila `total = 0`. `open_tickets` = tiket berstatus `open`/`proses`.

### 6.2 `GET /admin/jaringan/{device}/metrics?hours=24`

`hours` dibatasi `1…168`, default `24`. Semua array sejajar & sama panjang.

```json
{
  "labels": ["04/10 09:00", "04/10 09:01"],
  "rtt":    [12.4, 11.8],
  "cpu":    [31, 33],
  "rx":     [12345.0, 13000.5],
  "tx":     [6789.0, 7000.0],
  "status": ["up", "up"]
}
```

| Key | Tipe |
|---|---|
| `labels` | `string[]` format `d/m H:i` |
| `rtt` | `number\|null` (ms, ICMP) |
| `cpu` | `number\|null` (%, SNMP) |
| `rx`, `tx` | `number\|null` (bps) |
| `status` | `string\|null` (`up`/`down`/`unknown`) |

### 6.3 `GET /admin/jaringan/{device}/interfaces?hours=1`

```json
{
  "interfaces": [
    {
      "name": "ether1",
      "oper_status": "up",
      "speed": 1000000000,
      "rx_bps": 1234567.0,
      "tx_bps": 7654321.0,
      "updated_at": "2026-10-04T10:00:00+07:00",
      "total_bps": 8888888.0,
      "share": 42.5,
      "util": 0.8
    }
  ],
  "total": { "rx_bps": 1234567.0, "tx_bps": 7654321.0, "peak_rx_bps": 9999999.0, "peak_tx_bps": 8888888.0 },
  "history": {
    "labels": ["04/10 09:00"],
    "series": [ { "name": "ether1", "rx": [1.0], "tx": [2.0] } ],
    "bucket_seconds": 60
  },
  "spark": { "ether1": [1.0, 2.0] },
  "window_hours": 1,
  "generated_at": "2026-10-04T10:15:00+07:00"
}
```

- `share` = persentase trafik interface terhadap total perangkat (1 desimal).
- `util` = `max(rx,tx)/speed*100` (1 desimal), `null` bila `speed` tidak diketahui.
- Bucket `history`: `≤1j → 60s`, `≤6j → 300s`, `≤24j → 900s`, sisanya `7200s`.
- Top-6 interface menjadi series bernama, sisanya digabung jadi `"Lainnya"`.

### 6.4 `POST /admin/jaringan/{device}/check` & `DELETE /admin/jaringan/{device}`

```json
// POST .../check  → 200
{ "ok": true, "status": "up", "device": { "id": 1, "name": "Router Setda", "host": "10.0.0.1", "...": "…" } }

// DELETE .../{device} → 200
{ "ok": true, "message": "Perangkat \"Router Setda\" dihapus." }
```

Tanpa header JSON → keduanya `302 back()`.

### 6.5 `PATCH /admin/layanan/{ticket}/status` · `note` · `assign`

```json
// PATCH status  body: {"status":"proses","note":"opsional"}  → 200
{ "ok": true, "changed": true, "ticket": { "id": 7, "code": "TKT-20261004-0001", "status": "proses", "activities": [ … ] } }

// POST note     body: {"note":"menunggu teknisi"}            → 200
{ "ok": true, "ticket": { … } }

// POST assign   body: {"assignee_id": 3}  (null = lepas tugas) → 200
{ "ok": true, "ticket": { "assignee": { "id": 3, "name": "…" }, … } }
```

### 6.6 `POST /admin/pengaturan/webhook/{id}/test`

`{id}` ∈ `tiket` | `jaringan`.

```json
// 200
{ "ok": true, "error": null }

// 422
{ "ok": false, "error": "bot token / chat id belum diisi" }
```

Alasan 422: `webhook tidak ditemukan` · `webhook nonaktif — aktifkan dulu` ·
`bot token / chat id belum diisi` · `pengiriman gagal — cek laravel.log (token/chat id)`.

### 6.7 `PUT /admin/pengaturan/webhook/{id}`

```json
// Request (parsial aman — field yang tidak dikirim tidak diubah)
{ "enabled": true, "bot_token": "123456:ABC...", "chat_id": "-100123" }

// 200 — token TIDAK pernah dikembalikan mentah
{ "ok": true, "webhook": {
    "id": "tiket", "label": "Tiket", "description": "Laporan gangguan baru",
    "enabled": true, "bot_token": "••••CDEF", "chat_id": "-100123",
    "created_at": "…", "updated_at": "…" } }
```

`"bot_token": ""` / `"chat_id": ""` pada request = pengosongan yang disengaja.

---

## 7. Endpoint dengan body — aturan validasi

Semua field bertanda **wajib** kecuali ditandai `opsional`.

### 7.1 `POST /lapor` — laporan gangguan (publik, throttle 10/mnt)

| Field | Aturan |
|---|---|
| `title` | wajib, string, ≤200 |
| `category` | wajib, salah satu dari `config('noc.ticket_categories')`: `Jaringan`, `Perangkat`, `Website / Portal`, `Aplikasi`, `Lainnya` |
| `description` | wajib, string, ≤2000 |
| `location` | opsional, string, ≤200 |
| `lat` | opsional, numerik −90…90, wajib bila `lng` ada |
| `lng` | opsional, numerik −180…180, wajib bila `lat` ada |
| `reporter_name` | wajib, string, ≤100 (otomatis diisi nama akun bila login) |
| `reporter_contact` | opsional, string, ≤100 |
| `photo` | opsional, image, ≤2 MB → `storage/app/public/uploads/` |

Respons: **302** → `/lapor`, flash `success` =
`Laporan berhasil dikirim. Kode tiket Anda: TKT-YYYYMMDD-NNNN`.

### 7.2 `POST /registrasi` — daftar akun (publik, throttle 10/mnt)

| Field | Aturan |
|---|---|
| `name` | wajib, string, ≤100 |
| `email` | wajib, email, ≤255, unique · pesan: `Email sudah terdaftar. Silakan login.` |
| `password` | wajib, string, min 8, `confirmed` (`password_confirmation`) |

Respons: **302** → `/email/verify`. Akun dibuat `role=user`, `status=pending`,
`email_verified_at=null`; email verifikasi (signed URL, berlaku 60 menit) dikirim.

### 7.3 `POST /vps` — request VPS (login, throttle 10/mnt)

| Field | Aturan |
|---|---|
| `name` | wajib, string, ≤100 |
| `nip` | wajib, string, ≤30 |
| `jabatan` | wajib, string, ≤100 |
| `instansi` | wajib, string, ≤150 |
| `cores` | wajib, integer, 1…256 |
| `ram_gb` | wajib, integer, 1…1024 |
| `public_ips` | wajib, integer, 1…16 |
| `os` | wajib, salah satu key `config('noc.vps_operating_systems')`: `debian-12`, `ubuntu-2404`, `ubuntu-2204`, `rocky-9`, `almalinux-9`, `centos-stream-9`, `windows-server-2022`, `lainnya` · pesan: `Sistem operasi tidak dikenal.` |
| `os_other` | wajib **hanya bila** `os=lainnya`, string, ≤100 |
| `ports` | wajib **kecuali** `custom_ports` diisi · array, anggota ∈ key `config('noc.vps_ports')`: `22`, `80`, `443`, `21`, `25`, `53`, `3306`, `5432`, `8080`, `20000-20100` |
| `custom_ports` | opsional, string, ≤255, regex `^\d{1,5}(-\d{1,5})?(\s*,\s*\d{1,5}(-\d{1,5})?)*$` · contoh `8443, 9090, 3000-3100` |
| `purpose` | wajib, string, ≤1000 |
| `supporting_document` | opsional, file, `pdf,doc,docx,jpg,jpeg,png`, ≤5 MB → `storage/app/public/uploads/vps/documents/` |

Gate server-side sebelum validasi: akun harus `status=approved`, selain itu
**302** + flash error `Fitur Request VPS terkunci — akun Anda menunggu validasi admin.`
(tidak ada record dibuat).

Respons sukses: **302** → `/vps`, flash `success` =
`Request VPS VPS-YYYYMMDD-NNNN terkirim — menunggu review admin/operator.`
Efek samping: lonceng in-app ke seluruh reviewer + pesan Telegram webhook `tiket` (§9).

Contoh body (multipart bila ada file):

```bash
curl -b cookies.txt -X POST "$BASE/vps" \
  -H "X-XSRF-TOKEN: $TOKEN" -H "Accept: application/json" -H "X-Requested-With: XMLHttpRequest" \
  -F 'name=Budi Santoso' -F 'nip=198701012010011001' -F 'jabatan=Analis Kebijakan' \
  -F 'instansi=Dinas Pendidikan' -F 'cores=4' -F 'ram_gb=8' -F 'public_ips=2' \
  -F 'os=lainnya' -F 'os_other=Proxmox VE 8' \
  -F 'ports[]=22' -F 'ports[]=443' -F 'custom_ports=8443, 9090' \
  -F 'purpose=Portal e-learning' \
  -F 'supporting_document=@surat.pdf'
```

### 7.3a `POST /domain` & `POST /hosting` — pendaftaran domain/hosting (login, throttle 10/mnt)

Gate & respons sama dengan `POST /vps` (akun harus `approved`; sukses 302 + flash).
Field umum: `name`, `nip`, `jabatan`, `instansi`, `purpose` (aturan sama §7.3),
`supporting_document` (opsional, sama §7.3).

| Field | `POST /domain` | `POST /hosting` |
|---|---|---|
| `domain_name` | **wajib**, format domain (`diskominfosandi.go.id`) · pesan: `Format nama domain tidak valid — contoh: diskominfosandi.go.id.` | opsional, format domain sama |
| `hosting_package` | — | **wajib**, key `config('noc.service_registrations.hosting.packages')`: `shared-1gb`, `shared-5gb`, `vps-managed-2gb`, `vps-managed-4gb` |
| `duration` | **wajib** (TAHUN): `1`, `2`, `3`, `5` | **wajib** (BULAN): `1`, `3`, `6`, `12` |

Kode unik: `DOM-YYYYMMDD-NNNN` / `HST-YYYYMMDD-NNNN`. Tabel `service_registrations`
(kolom `type` = `domain`\|`hosting`). Efek samping: lonceng in-app reviewer +
pesan Telegram webhook `tiket` (§9).

### 7.4 Review VPS (admin/operator)

`PATCH /admin/vps/{id}/status`

| Field | Aturan |
|---|---|
| `status` | wajib, `approved` \| `rejected` |
| `admin_note` | opsional, string, ≤1000 |

Efek: `reviewed_by` + `reviewed_at` diisi; notifikasi in-app ke pemilik **hanya bila status berubah**.

`POST /admin/vps/{id}/credentials` — wajib status `approved`, field `credential`
(required, `pdf,jpg,jpeg,png,txt`, ≤5 MB) → mengganti file lama + notifikasi
ke pemilik & seluruh reviewer (kecuali pengunggah).

`PATCH /admin/pendaftaran/{id}/status` — body `status` (`approved`|`rejected`) dan
`admin_note` (opsional ≤1000). Efek: `reviewed_by` + `reviewed_at` diisi; notifikasi
in-app ke pemilik **hanya bila status berubah**. Tipe (`domain`/`hosting`) dibaca dari
baris, bukan dari body.

### 7.5 Perangkat jaringan (admin)

`POST /admin/jaringan` dan `PUT /admin/jaringan/{device}` memakai aturan yang sama:

| Field | Aturan |
|---|---|
| `name` | wajib, string, ≤100 |
| `host` | wajib bila `hosts` tidak diisi, string, ≤255 |
| `hosts` | opsional, array, maks 8 elemen, string ≤255 · wajib minimal 1 host non-kosong |
| `type` | wajib, `router`\|`switch`\|`server`\|`website`\|`lainnya` |
| `location` | opsional, string, ≤150 |
| `enabled` | boolean |
| `use_icmp`, `use_snmp`, `use_routeros` | boolean |
| `snmp_version` | opsional, `1`\|`2c`\|`3` |
| `snmp_community` | opsional, string, ≤128 |
| `snmp_port` | opsional, integer, 1…65535 |
| `routeros_port` | opsional, integer, 1…65535 |
| `routeros_user` | opsional, string, ≤64 |
| `routeros_password` | opsional, string, ≤255 |
| `routeros_timeout` | opsional, integer, 1…60 |

Normalisasi: `hosts` di-dedup/di-trim, `host` selalu = `hosts[0]`, kosong → error
`Minimal satu IP/hostname wajib diisi.`

### 7.6 Manajemen user (admin)

`POST /admin/pengaturan/user` — `name` (≤100), `email` (unique), `password` (min 8, confirmed),
`role` (`admin`|`operator`|`user`). Akun dibuat `status=approved` + email dianggap terverifikasi.

`PUT /admin/pengaturan/user/{user}` — semua field `sometimes`; `password` kosong = sandi lama
tetap dipakai; **422** `Anda tidak bisa menurunkan peran akun sendiri.` bila self-demotion.

`DELETE /admin/pengaturan/user/{user}` — **422** `Anda tidak bisa menghapus akun sendiri.`

### 7.7 Tiket (admin)

`PATCH /admin/layanan/{ticket}/status` — `status` wajib ∈ `open`|`proses`|`selesai`,
`note` opsional ≤1000. Perubahan `selesai` mengisi `resolved_at` dan mencatat
`ticket_activities` (`action: status`).

`POST /admin/layanan/{ticket}/note` — `note` wajib, string, ≤1000.
`POST /admin/layanan/{ticket}/assign` — `assignee_id` opsional, integer, `exists:users,id` (null = lepas).

---

## 8. Unduhan file

| Endpoint | Pengguna | Nama file hasil |
|---|---|---|
| `GET /uploads/{path}` | publik | sesuai MIME asli (foto tiket) |
| `GET /vps/{id}/credentials` | pemilik request ATAU admin/operator | `kredensial-<kode>.<ext>` |
| `GET /vps/{id}/document` | pemilik request ATAU admin/operator | `dokumen-pendukung-<kode>.<ext>` |

Ketiganya memakai guard path-traversal (`realpath` wajib berada di dalam
`storage/app/public`) → path yang menyimpang / file hilang = **404**.
Bukan pemilik & bukan reviewer = **403**.

---

## 9. Integrasi Telegram (notifikasi keluar)

Bukan endpoint masuk — aplikasi **mengirim** pesan ke grup Telegram memakai Bot API
(`POST https://api.telegram.org/bot{token}/sendMessage`, `parse_mode: HTML`,
timeout 10 dtk, retry 2×).

Dua webhook tetap (tabel `telegram_webhooks`, dikelola di **Pengaturan → Webhook**):

| id | Dipakai untuk | Dipicu oleh |
|---|---|---|
| `tiket` | **Semua tiket masuk**: laporan gangguan baru, request VPS baru, **pendaftaran domain/hosting baru** | `POST /lapor`, `POST /vps`, `POST /domain`, `POST /hosting` |
| `jaringan` | Transisi status perangkat `up ↔ down` | scheduler `monitor:poll` / `POST .../check` |

| Method notifier | Isi pesan |
|---|---|
| `sendTicketCreated` | `LAPORAN GANGGUAN BARU`, kode, judul, kategori, pelapor, lokasi, tautan peta, waktu |
| `sendVpsRequestCreated` | `REQUEST VPS BARU`, kode, instansi, pemohon, spek (core/GB/IP), OS, port (preset + tambahan), tujuan (≤200 char), waktu |
| `sendServiceRegistrationCreated` | `PENDAFTARAN DOMAIN BARU` / `PENDAFTARAN HOSTING BARU`, kode, instansi, pemohon, detail (domain/paket/durasi), tujuan, waktu |
| `sendStatusChange` | `PERUBAHAN STATUS JARINGAN`, nama/host perangkat, `ONLINE → DOWN`, waktu |
| `sendTest` | pesan uji kirim — dipakai endpoint §6.6 |

**Fail-closed:** kegagalan (timeout, HTTP ≠ 2xx, `ok:false`, webhook nonaktif/tak dikonfigurasi)
hanya menghasilkan `Log::warning` dan nilai `false` — **tidak pernah** menggagalkan pembuatan
tiket/request atau menghentikan poller. Sukses dicatat sebagai
`Log::info("telegram terkirim", {webhook, message_id})`.

---

## 10. Contoh integrasi (curl)

### 10.1 Status publik tanpa login

```bash
curl -s "$BASE/status" -H "Accept: application/json"
```

### 10.2 Login lalu ambil data terproteksi

```bash
BASE=https://kuma.baritoutarakab.go.id
J="curl -s -c cookies.txt -b cookies.txt"

# 1) ambil cookie sesi + XSRF
$J "$BASE/login" > /dev/null
TOKEN=$(grep XSRF-TOKEN cookies.txt | awk '{print $7}' | python3 -c \
  'import sys,urllib.parse;print(urllib.parse.unquote(sys.stdin.read().strip()))')

# 2) login
$J -X POST "$BASE/login" \
   -H "Content-Type: application/json" -H "Accept: application/json" \
   -H "X-Requested-With: XMLHttpRequest" -H "X-XSRF-TOKEN: $TOKEN" \
   -d '{"email":"admin@noc.baritoutarakab.go.id","password":"***"}' -o /dev/null -w "%{http_code}\n"
# → 302

# 3) refresh token (sesi diregenerasi saat login)
TOKEN=$(grep XSRF-TOKEN cookies.txt | awk '{print $7}' | python3 -c \
  'import sys,urllib.parse;print(urllib.parse.unquote(sys.stdin.read().strip()))')

# 4) endpoint JSON terproteksi
$J "$BASE/admin/jaringan/1/metrics?hours=24" -H "Accept: application/json"
$J "$BASE/admin/jaringan/1/interfaces?hours=1" -H "Accept: application/json"
```

### 10.3 Uji kirim webhook Telegram

```bash
curl -s -X POST "$BASE/admin/pengaturan/webhook/tiket/test" \
  -b cookies.txt -H "X-XSRF-TOKEN: $TOKEN" \
  -H "Accept: application/json" -H "X-Requested-With: XMLHttpRequest"
# → {"ok":true,"error":null}
```

### 10.4 Halaman Inertia sebagai JSON

```bash
curl -s "$BASE/admin/layanan" -b cookies.txt \
  -H "X-Inertia: true" -H "Accept: text/html" -H "X-Requested-With: XMLHttpRequest" \
  | jq '{component, tickets: .props.tickets.data, stats: .props.stats}'
```

Jika aset baru di-build, kirim `X-Inertia-Version` yang cocok (nilai `version` dari
respons sebelumnya) — selain itu server membalas **409**.

---

## 11. Catatan keamanan

1. **Tidak ada API token** — integrasi luar wajib memakai sesi + CSRF seperti browser.
2. `GET /admin/jaringan`, `.../check`, dan `.../metrics` **admin-only** dan mengembalikan
   field sensitif perangkat (`snmp_community`, `routeros_password`) — jangan diekspos ke publik.
3. `GET /status` sengaja hanya berisi angka agregat (tanpa nama/IP perangkat).
4. Token webhook Telegram **tidak pernah** dikembalikan utuh (hanya 4 karakter terakhir).
5. File yang diunduh selalu lewat controller dengan guard path-traversal, bukan langsung `/storage`.
6. Route `signed` (verifikasi email) memakai HMAC + `sha1(email)` dan rate limit 6/menit.
7. Notifikasi `POST /notifications/*` memverifikasi kepemilikan → id milik user lain = **404**.

---

## 12. Konfigurasi terkait

| Hal | Sumber |
|---|---|
| Kategori tiket, status, port VPS, OS VPS | `config/noc.php` |
| Label/paket/durasi pendaftaran Domain & Hosting (`service_registrations`) | `config/noc.php` |
| Disk upload (`public` → `storage/app/public`) | `config/filesystems.php` |
| Nama cookie sesi, lifetime, same-site | `config/session.php` |
| Middleware `role`, prop Inertia bersama | `bootstrap/app.php`, `app/Http/Middleware/` |
| Health check `/up` | `bootstrap/app.php` (`health:`) |
| Scheduler (poll 30 dtk, prune harian) | `routes/console.php` |
