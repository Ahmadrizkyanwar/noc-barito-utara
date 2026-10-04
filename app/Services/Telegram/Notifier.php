<?php

namespace App\Services\Telegram;

use App\Models\Device;
use App\Models\TelegramWebhook;
use App\Models\Ticket;
use App\Models\VpsRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pengirim pesan Telegram via Bot API.
 *
 * Dua webhook TETAP per fungsi (keputusan desain):
 *   - `tiket`    → tiket masuk APA SAJA: laporan gangguan baru + request VPS baru
 *   - `jaringan` → transisi status perangkat
 *
 * Gagal-tertutup: timeout/HTTP error → log warning, TIDAK mengganggu siklus
 * poller atau pembuatan tiket.
 */
class Notifier
{
    /**
     * Kirim pesan ke webhook tertentu. False = gagal (sudah di-log).
     */
    public function send(string $webhookId, string $text): bool
    {
        $wh = TelegramWebhook::find($webhookId);

        if ($wh === null || ! $wh->isConfigured()) {
            return false;
        }

        try {
            $response = Http::timeout(10)
                ->retry(2, 500, throw: false)
                ->post('https://api.telegram.org/bot'.$wh->bot_token.'/sendMessage', [
                    'chat_id' => $wh->chat_id,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]);
        } catch (\Throwable $e) {
            Log::warning('telegram kirim gagal', ['webhook' => $webhookId, 'error' => $e->getMessage()]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('telegram HTTP error', [
                'webhook' => $webhookId,
                'status' => $response->status(),
            ]);

            return false;
        }

        $body = $response->json();
        if (! is_array($body) || ($body['ok'] ?? false) !== true) {
            Log::warning('telegram API ok=false', ['webhook' => $webhookId]);

            return false;
        }

        // Jejak audit: pesan benar-benar diterima Telegram (dipakai verifikasi live).
        Log::info('telegram terkirim', [
            'webhook' => $webhookId,
            'message_id' => $body['result']['message_id'] ?? null,
        ]);

        return true;
    }

    /**
     * Notifikasi tiket baru (webhook `tiket`).
     */
    public function sendTicketCreated(Ticket $ticket): bool
    {
        $pelapor = $ticket->reporter_name
            ?? $ticket->reporter?->name
            ?? '-';

        $lines = [
            '<b>LAPORAN GANGGUAN BARU</b>',
            '',
            'Kode: <b>'.$this->e($ticket->code).'</b>',
            'Judul: '.$this->e($ticket->title),
            'Kategori: '.$this->e($ticket->category),
            'Pelapor: '.$this->e($pelapor),
        ];

        if ($ticket->location !== null && $ticket->location !== '') {
            $lines[] = 'Lokasi: '.$this->e($ticket->location);
        }

        if ($ticket->mapUrl() !== null) {
            $lines[] = 'Titik: <a href="'.$this->e($ticket->mapUrl()).'">buka di peta</a>';
        }

        $lines[] = 'Waktu: '.$ticket->created_at->format('d M Y H:i');

        return $this->send(TelegramWebhook::ID_TIKET, implode("\n", $lines));
    }

    /**
     * Notifikasi transisi status perangkat (webhook `jaringan`).
     *
     * @param  string  $from  up|down|unknown
     * @param  string  $to  up|down|unknown
     */
    public function sendStatusChange(Device $device, string $from, string $to): bool
    {
        $icon = $to === 'up' ? '🟢' : '🔴';
        $label = $to === 'up' ? 'ONLINE' : 'DOWN';

        $lines = [
            $icon.' <b>PERUBAHAN STATUS JARINGAN</b>',
            '',
            'Perangkat: '.$this->e($device->name),
            'Host: '.$this->e($device->host),
            'Status: '.($from === 'up' ? 'ONLINE' : 'DOWN').' → <b>'.$label.'</b>',
            'Waktu: '.now()->format('d M Y H:i:s'),
        ];

        return $this->send(TelegramWebhook::ID_JARINGAN, implode("\n", $lines));
    }

    /**
     * Notifikasi request VPS baru (webhook `tiket` — kanal tiket yang sama).
     */
    public function sendVpsRequestCreated(VpsRequest $vps): bool
    {
        $preset = (array) config('noc.vps_ports', []);
        $ports = [];

        foreach ($vps->ports ?? [] as $p) {
            $ports[] = $p.(isset($preset[$p]) ? ' · '.$preset[$p] : '');
        }

        if ($vps->custom_ports !== null && $vps->custom_ports !== '') {
            $ports[] = $vps->custom_ports.' · tambahan';
        }

        $os = $vps->os;

        if ($os === 'lainnya') {
            $os = $vps->os_other !== null && $vps->os_other !== '' ? $vps->os_other : 'Lainnya';
        } else {
            $osList = (array) config('noc.vps_operating_systems', []);
            $os = $osList[$os] ?? $os ?? '-';
        }

        $lines = [
            '<b>REQUEST VPS BARU</b>',
            '',
            'Kode: <b>'.$this->e($vps->code).'</b>',
            'Instansi: '.$this->e($vps->instansi),
            'Pemohon: '.$this->e($vps->name).' — '.$this->e($vps->jabatan),
            'Spek: '.$vps->cores.' core / '.$vps->ram_gb.' GB / '.$vps->public_ips.' IP publik',
            'OS: '.$this->e($os),
            'Port: '.$this->e($ports !== [] ? implode(', ', $ports) : '-'),
        ];

        $purpose = trim((string) $vps->purpose);

        if ($purpose !== '') {
            $lines[] = 'Tujuan: '.$this->e(mb_substr($purpose, 0, 200));
        }

        $lines[] = 'Waktu: '.$vps->created_at->format('d M Y H:i');

        return $this->send(TelegramWebhook::ID_TIKET, implode("\n", $lines));
    }

    /**
     * Uji kirim (dipakai tombol "Uji kirim" di Pengaturan → Webhook).
     *
     * @return array{ok: bool, error: string|null}
     */
    public function sendTest(string $webhookId): array
    {
        $wh = TelegramWebhook::find($webhookId);

        if ($wh === null) {
            return ['ok' => false, 'error' => 'webhook tidak ditemukan'];
        }
        if (! $wh->enabled) {
            return ['ok' => false, 'error' => 'webhook nonaktif — aktifkan dulu'];
        }
        if ($wh->bot_token === '' || $wh->chat_id === '') {
            return ['ok' => false, 'error' => 'bot token / chat id belum diisi'];
        }

        $ok = $this->send($webhookId, '✅ <b>Uji kirim berhasil</b>'."\n".'Aplikasi: '.config('app.name').' — '.now()->format('d M Y H:i:s'));

        return $ok
            ? ['ok' => true, 'error' => null]
            : ['ok' => false, 'error' => 'pengiriman gagal — cek laravel.log (token/chat id)'];
    }

    protected function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
