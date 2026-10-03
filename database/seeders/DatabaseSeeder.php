<?php

namespace Database\Seeders;

use App\Models\DashboardWidget;
use App\Models\TelegramWebhook;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Widget dashboard admin — baris TETAP (selalu di-seed) ──
        foreach (DashboardWidget::defaults() as $widget) {
            DashboardWidget::firstOrCreate(['id' => $widget['id']], $widget);
        }

        // ── Admin awal dari WEB_USERNAME / WEB_PASSWORD ──
        $username = (string) env('WEB_USERNAME', 'admin');
        $password = (string) env('WEB_PASSWORD', '');

        if ($password === '') {
            return;
        }

        User::firstOrCreate(
            ['email' => $username.'@noc.baritoutarakab.go.id'],
            [
                'name' => ucfirst($username),
                'password' => $password, // cast `hashed`
                'role' => User::ROLE_ADMIN,
            ]
        );

        // ── 2 webhook Telegram TETAP (di-seed barisnya, admin hanya edit) ──
        TelegramWebhook::firstOrCreate(
            ['id' => TelegramWebhook::ID_TIKET],
            [
                'label' => 'Tiket',
                'description' => 'Laporan gangguan baru dari form Lapor Gangguan.',
                'enabled' => false,
                'bot_token' => (string) env('TELEGRAM_TIKET_TOKEN', ''),
                'chat_id' => (string) env('TELEGRAM_TIKET_CHAT_ID', ''),
            ]
        );

        TelegramWebhook::firstOrCreate(
            ['id' => TelegramWebhook::ID_JARINGAN],
            [
                'label' => 'Jaringan',
                'description' => 'Transisi status perangkat online/down dari poller.',
                'enabled' => false,
                'bot_token' => (string) env('TELEGRAM_JARINGAN_TOKEN', ''),
                'chat_id' => (string) env('TELEGRAM_JARINGAN_CHAT_ID', ''),
            ]
        );
    }
}
