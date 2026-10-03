<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TelegramWebhook;
use App\Services\Telegram\Notifier;
use Illuminate\Http\JsonResponse;
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

    public function update(Request $request, string $id): JsonResponse
    {
        $wh = TelegramWebhook::find($id);

        if ($wh === null) {
            return response()->json(['ok' => false, 'error' => 'Webhook tidak ditemukan.'], 404);
        }

        $data = $request->validate([
            'enabled' => ['boolean'],
            'bot_token' => ['nullable', 'string', 'max:255'],
            'chat_id' => ['nullable', 'string', 'max:64'],
        ]);

        // ConvertEmptyStringsToNull mengubah "" → null; kolom NOT NULL →
        // dipaksa kembali ke string kosong.
        $wh->fill([
            'enabled' => (bool) ($data['enabled'] ?? $wh->enabled),
            'bot_token' => (string) ($data['bot_token'] ?? ''),
            'chat_id' => (string) ($data['chat_id'] ?? ''),
        ])->save();

        return response()->json(['ok' => true, 'webhook' => $wh]);
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
