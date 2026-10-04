<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TelegramWebhook;
use App\Services\Telegram\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengaturan → Webhook Telegram.
 * Dua entri TETAP (tiket & jaringan) — admin hanya mengedit, bukan CRUD.
 */
class WebhookController extends Controller
{
    public function __construct(protected Notifier $notifier) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Webhook', [
            'webhooks' => TelegramWebhook::orderBy('id')->get(),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $wh = TelegramWebhook::find($id);

        if ($wh === null) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => 'Webhook tidak ditemukan.'], 404);
            }

            abort(404);
        }

        $data = $request->validate([
            'enabled' => ['boolean'],
            'bot_token' => ['nullable', 'string', 'max:255'],
            'chat_id' => ['nullable', 'string', 'max:64'],
        ]);

        $wh->enabled = (bool) ($data['enabled'] ?? $wh->enabled);

        // Hanya timpa bila field IKUT DIKIRIM — permintaan parsial (tanpa
        // bot_token/chat_id) tidak boleh menghapus konfigurasi yang ada.
        // Field terkirim kosong ("" → null) = pengosongan yang disengaja.
        if (array_key_exists('chat_id', $data)) {
            $wh->chat_id = (string) $data['chat_id'];
        }
        if (array_key_exists('bot_token', $data)) {
            $wh->bot_token = (string) $data['bot_token'];
        }

        $wh->save();

        // Request Inertia (form UI) → redirect; JSON (API/tes) → JSON.
        // Token TIDAK dikirim mentah balik — hanya 4 karakter terakhir.
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'webhook' => [
                    'id' => $wh->id,
                    'label' => $wh->label,
                    'description' => $wh->description,
                    'enabled' => $wh->enabled,
                    'bot_token' => $wh->bot_token !== '' ? '••••'.substr($wh->bot_token, -4) : '',
                    'chat_id' => $wh->chat_id,
                    'created_at' => $wh->created_at,
                    'updated_at' => $wh->updated_at,
                ],
            ]);
        }

        // Pesan sukses ditampilkan per-kartu oleh halaman Webhook.vue.
        return back();
    }

    /**
     * Uji kirim pesan memakai token/chat id webhook itu sendiri.
     */
    public function test(string $id): JsonResponse
    {
        $result = $this->notifier->sendTest($id);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }
}
