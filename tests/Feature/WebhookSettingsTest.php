<?php

namespace Tests\Feature;

use App\Models\TelegramWebhook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        TelegramWebhook::firstOrCreate(
            ['id' => TelegramWebhook::ID_TIKET],
            ['label' => 'Tiket', 'description' => 'Laporan gangguan baru']
        );
        TelegramWebhook::firstOrCreate(
            ['id' => TelegramWebhook::ID_JARINGAN],
            ['label' => 'Jaringan', 'description' => 'Transisi status']
        );
    }

    public function test_admin_sees_both_fixed_webhooks(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/pengaturan/webhook')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Webhook')
                ->has('webhooks', 2)
                // orderBy('id') → 'jaringan' sebelum 'tiket' (alfabetis)
                ->where('webhooks.0.id', 'jaringan')
                ->where('webhooks.1.id', 'tiket'));
    }

    public function test_admin_can_update_webhook(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->putJson('/admin/pengaturan/webhook/tiket', [
                'enabled' => true,
                'bot_token' => '111:AAA',
                'chat_id' => '-100111',
            ])
            ->assertOk()
            ->assertJsonPath('webhook.enabled', true)
            ->assertJsonPath('webhook.chat_id', '-100111');

        $this->assertDatabaseHas('telegram_webhooks', [
            'id' => 'tiket', 'enabled' => 1, 'chat_id' => '-100111',
        ]);
    }

    public function test_unknown_webhook_returns_404(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->putJson('/admin/pengaturan/webhook/ngawur', ['enabled' => true])
            ->assertNotFound();
    }

    public function test_test_send_uses_webhook_token_and_chat(): void
    {
        $admin = User::factory()->admin()->create();
        TelegramWebhook::find('tiket')->update([
            'enabled' => true,
            'bot_token' => '777:ZZZ',
            'chat_id' => '-100777',
        ]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->actingAs($admin)
            ->postJson('/admin/pengaturan/webhook/tiket/test')
            ->assertOk()
            ->assertJsonPath('ok', true);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'bot777:ZZZ/sendMessage') && $r['chat_id'] === '-100777');
    }

    public function test_test_send_fails_when_disabled(): void
    {
        $admin = User::factory()->admin()->create();
        Http::fake();

        $this->actingAs($admin)
            ->postJson('/admin/pengaturan/webhook/tiket/test')
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_test_send_fails_on_telegram_api_error(): void
    {
        $admin = User::factory()->admin()->create();
        TelegramWebhook::find('tiket')->update([
            'enabled' => true,
            'bot_token' => 'bad',
            'chat_id' => '1',
        ]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 400)]);

        $this->actingAs($admin)
            ->postJson('/admin/pengaturan/webhook/tiket/test')
            ->assertStatus(422);
    }

    public function test_guest_cannot_access_webhook_settings(): void
    {
        $this->get('/admin/pengaturan/webhook')->assertRedirect(route('login'));
    }

    public function test_user_role_cannot_access_webhook_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/pengaturan/webhook')->assertForbidden();
    }

    /**
     * Form UI memakai Inertia (Accept html) → wajib redirect, bukan JSON
     * (dulu memicu error "All Inertia requests must receive a valid
     * Inertia response").
     */
    public function test_update_redirects_for_inertia_style_requests(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put('/admin/pengaturan/webhook/tiket', [
                'enabled' => true,
                'bot_token' => '111:AAA',
                'chat_id' => '-100222',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('telegram_webhooks', [
            'id' => 'tiket', 'enabled' => 1, 'chat_id' => '-100222',
        ]);
    }

    public function test_json_response_masks_bot_token(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->putJson('/admin/pengaturan/webhook/tiket', [
                'enabled' => true,
                'bot_token' => '111:AAASECRET',
                'chat_id' => '-100333',
            ])
            ->assertOk()
            ->assertJsonPath('webhook.bot_token', '••••CRET');

        $this->assertStringNotContainsString(
            'AAASECRET',
            $this->actingAs($admin)->putJson('/admin/pengaturan/webhook/tiket', ['enabled' => true])->getContent()
        );
    }

    /** Permintaan parsial (tanpa bot_token/chat_id) tidak boleh menghapus config. */
    public function test_partial_update_preserves_bot_token_and_chat_id(): void
    {
        $admin = User::factory()->admin()->create();
        TelegramWebhook::find('tiket')->update([
            'bot_token' => '123456:ABCDEF',
            'chat_id' => '-100555',
        ]);

        $this->actingAs($admin)
            ->putJson('/admin/pengaturan/webhook/tiket', ['enabled' => false])
            ->assertOk();

        $wh = TelegramWebhook::find('tiket');
        $this->assertFalse($wh->enabled);
        $this->assertSame('123456:ABCDEF', $wh->bot_token); // tidak terhapus
        $this->assertSame('-100555', $wh->chat_id);          // tidak terhapus

        // Field terkirim kosong = pengosongan yang disengaja (dilakukan form UI)
        $this->actingAs($admin)
            ->putJson('/admin/pengaturan/webhook/tiket', [
                'enabled' => true,
                'bot_token' => '',
                'chat_id' => '',
            ])
            ->assertOk();

        $wh = TelegramWebhook::find('tiket');
        $this->assertSame('', $wh->bot_token);
        $this->assertSame('', $wh->chat_id);
    }
}
