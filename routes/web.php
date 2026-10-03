<?php

use App\Http\Controllers\Admin\DeviceController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WebhookController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ReportController;
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
