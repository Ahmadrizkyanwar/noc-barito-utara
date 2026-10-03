<?php

use App\Http\Controllers\Admin\DeviceController;
use App\Http\Controllers\Admin\RegistrationController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VpsReviewController;
use App\Http\Controllers\Admin\WebhookController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\VpsRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Halaman publik
|--------------------------------------------------------------------------
*/
Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::get('/status', [LandingController::class, 'status'])->name('landing.status'); // JSON live

Route::get('/login', [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Registrasi user publik — divalidasi admin/operator (status default pending)
Route::get('/registrasi', [AuthController::class, 'showRegister'])->name('register')->middleware('guest');
Route::post('/registrasi', [AuthController::class, 'register'])
    ->middleware(['guest', 'throttle:10,1']);

// Verifikasi email WAJIB sebelum login — endpoint tujuan link bersifat
// `signed` (HMAC) + hash sha1(email) → TANPA login, kepemilikan email
// dibuktikan oleh link yang diterima lewat inbox.
Route::get('/email/verify', [AuthController::class, 'showVerifyNotice'])
    ->name('verification.notice')
    ->middleware('guest');
Route::post('/email/resend', [AuthController::class, 'resendVerification'])
    ->name('verification.resend')
    ->middleware(['guest', 'throttle:6,1']);
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->name('verification.verify')
    ->middleware(['signed', 'throttle:6,1']);

// Laporan gangguan publik (tanpa login)
Route::get('/lapor', [ReportController::class, 'create'])->name('lapor.index');
Route::post('/lapor', [ReportController::class, 'store'])
    ->name('lapor.store')
    ->middleware('throttle:10,1');

// Foto tiket (publik, path-diguard)
Route::get('/uploads/{path}', [ReportController::class, 'photo'])
    ->where('path', '.*')
    ->name('ticket.photo');

/*
|--------------------------------------------------------------------------
| Semua user login (admin + user) — role dibedakan di halaman Vue
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Request VPS — terkunci sampai akun disetujui (dicek di controller)
    Route::get('/vps', [VpsRequestController::class, 'index'])->name('vps.index');
    Route::post('/vps', [VpsRequestController::class, 'store'])
        ->name('vps.store')
        ->middleware('throttle:10,1');
    // Unduh kredensial (pemilik request ATAU reviewer)
    Route::get('/vps/{vpsRequest}/credentials', [VpsRequestController::class, 'credentials'])
        ->name('vps.credentials');

    // Lonceng notifikasi
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])
        ->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])
        ->name('notifications.readAll');
});

/*
|--------------------------------------------------------------------------
| Admin + Operator — validasi registrasi & review request VPS
|--------------------------------------------------------------------------
| Operator HANYA mendapat akses ke dua halaman ini (halaman admin lain
| tetap dijaga `role:admin`).
*/
Route::middleware(['auth', 'role:admin,operator'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/registrasi', [RegistrationController::class, 'index'])->name('registrations.index');
    Route::patch('/registrasi/{user}/status', [RegistrationController::class, 'updateStatus'])->name('registrations.status');

    Route::get('/vps', [VpsReviewController::class, 'index'])->name('vps.index');
    Route::patch('/vps/{vpsRequest}/status', [VpsReviewController::class, 'updateStatus'])->name('vps.status');
    // Upload dokumen kredensial SETELAH disetujui
    Route::post('/vps/{vpsRequest}/credentials', [VpsReviewController::class, 'uploadCredentials'])
        ->name('vps.credentials.upload');
});

/*
|--------------------------------------------------------------------------
| Admin saja
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard admin (view terpisah dari /dashboard user)
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    // Pilihan widget infografik yang tampil di dashboard
    Route::patch('/dashboard/widgets', [DashboardController::class, 'updateWidgets'])
        ->name('dashboard.widgets.update');

    // Jaringan
    Route::get('/jaringan', [DeviceController::class, 'index'])->name('devices.index');
    Route::post('/jaringan', [DeviceController::class, 'store'])->name('devices.store');
    Route::get('/jaringan/{device}', [DeviceController::class, 'show'])->name('devices.show');
    Route::put('/jaringan/{device}', [DeviceController::class, 'update'])->name('devices.update');
    Route::delete('/jaringan/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');
    Route::post('/jaringan/{device}/check', [DeviceController::class, 'check'])->name('devices.check');
    Route::get('/jaringan/{device}/metrics', [DeviceController::class, 'metrics'])->name('devices.metrics');

    // Infografik trafik per interface
    Route::get('/jaringan/{device}/trafik', [DeviceController::class, 'traffic'])->name('devices.traffic');
    Route::get('/jaringan/{device}/interfaces', [DeviceController::class, 'interfaces'])->name('devices.interfaces');

    // Layanan — ticketing
    Route::get('/layanan', [AdminTicketController::class, 'index'])->name('tickets.index');
    Route::get('/layanan/{ticket}', [AdminTicketController::class, 'show'])->name('tickets.show');
    Route::patch('/layanan/{ticket}/status', [AdminTicketController::class, 'updateStatus'])->name('tickets.status');
    Route::post('/layanan/{ticket}/note', [AdminTicketController::class, 'addNote'])->name('tickets.note');
    Route::post('/layanan/{ticket}/assign', [AdminTicketController::class, 'assign'])->name('tickets.assign');

    // Pengaturan — user
    Route::get('/pengaturan/user', [UserController::class, 'index'])->name('users.index');
    Route::post('/pengaturan/user', [UserController::class, 'store'])->name('users.store');
    Route::put('/pengaturan/user/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/pengaturan/user/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    // Pengaturan — webhook Telegram
    Route::get('/pengaturan/webhook', [WebhookController::class, 'index'])->name('webhooks.index');
    Route::put('/pengaturan/webhook/{id}', [WebhookController::class, 'update'])->name('webhooks.update');
    Route::post('/pengaturan/webhook/{id}/test', [WebhookController::class, 'test'])->name('webhooks.test');
});
