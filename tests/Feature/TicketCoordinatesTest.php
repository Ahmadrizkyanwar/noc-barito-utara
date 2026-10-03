<?php

namespace Tests\Feature;

use App\Models\TelegramWebhook;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Titik koordinat laporan: opsional, berpasangan (lat+lng), link peta Telegram.
 */
class TicketCoordinatesTest extends TestCase
{
    use RefreshDatabase;

    protected function validPayload(): array
    {
        return [
            'title' => 'Gangguan uji',
            'category' => 'Jaringan',
            'description' => 'Deskripsi gangguan untuk pengujian.',
            'reporter_name' => 'Pelapor Uji',
        ];
    }

    protected function configureTicketWebhook(): void
    {
        TelegramWebhook::firstOrCreate(
            ['id' => TelegramWebhook::ID_TIKET],
            ['label' => 'Tiket']
        );
        TelegramWebhook::find(TelegramWebhook::ID_TIKET)->update([
            'enabled' => true,
            'bot_token' => '42:tok',
            'chat_id' => '-1009',
        ]);
    }

    // ── Penyimpanan ─────────────────────────────────────────────────────────

    public function test_coordinates_are_stored_when_provided(): void
    {
        $this->post('/lapor', [
            ...$this->validPayload(),
            'lat' => -1.535214,
            'lng' => 114.861892,
        ])->assertSessionHas('success');

        $ticket = Ticket::first();
        $this->assertNotNull($ticket);
        $this->assertEqualsWithDelta(-1.535214, (float) $ticket->lat, 1e-6);
        $this->assertEqualsWithDelta(114.861892, (float) $ticket->lng, 1e-6);
    }

    public function test_report_can_be_created_without_coordinates(): void
    {
        $this->post('/lapor', $this->validPayload())->assertSessionHas('success');

        $ticket = Ticket::first();
        $this->assertNotNull($ticket);
        $this->assertNull($ticket->lat);
        $this->assertNull($ticket->lng);
        $this->assertFalse($ticket->hasCoords());
        $this->assertNull($ticket->mapUrl());

        // Form browser mengirim string kosong (bukan null) → tetap dianggap kosong.
        $this->post('/lapor', [
            ...$this->validPayload(),
            'lat' => '',
            'lng' => '',
        ])->assertSessionHas('success');

        $last = Ticket::latest('id')->first();
        $this->assertNull($last->lat);
        $this->assertNull($last->lng);
    }

    // ── Validasi pasangan & rentang ─────────────────────────────────────────

    public function test_latitude_without_longitude_is_rejected(): void
    {
        $this->from('/lapor')
            ->post('/lapor', [...$this->validPayload(), 'lat' => -1.5])
            ->assertSessionHasErrors('lng');

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_longitude_without_latitude_is_rejected(): void
    {
        $this->from('/lapor')
            ->post('/lapor', [...$this->validPayload(), 'lng' => 114.8])
            ->assertSessionHasErrors('lat');

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_out_of_range_coordinates_are_rejected(): void
    {
        $this->from('/lapor')
            ->post('/lapor', [
                ...$this->validPayload(),
                'lat' => 91,
                'lng' => 114.8,
            ])
            ->assertSessionHasErrors('lat');

        $this->from('/lapor')
            ->post('/lapor', [
                ...$this->validPayload(),
                'lat' => -1.5,
                'lng' => 181,
            ])
            ->assertSessionHasErrors('lng');
    }

    // ── Notifikasi Telegram ─────────────────────────────────────────────────

    public function test_telegram_notification_contains_map_link(): void
    {
        $this->configureTicketWebhook();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->post('/lapor', [
            ...$this->validPayload(),
            'lat' => -1.535214,
            'lng' => 114.861892,
        ])->assertSessionHas('success');

        Http::assertSent(function ($request) {
            $text = json_decode($request->body(), true)['text'] ?? '';

            return str_contains($request->url(), 'api.telegram.org')
                && str_contains($text, 'https://www.google.com/maps?q=-1.535214,114.861892');
        });
    }

    public function test_telegram_notification_omits_map_link_without_coordinates(): void
    {
        $this->configureTicketWebhook();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->post('/lapor', $this->validPayload())->assertSessionHas('success');

        Http::assertSent(function ($request) {
            $text = json_decode($request->body(), true)['text'] ?? '';

            return str_contains($request->url(), 'api.telegram.org')
                && $text !== ''
                && ! str_contains($text, 'maps?q=');
        });
    }

    // ── Detail tiket admin ──────────────────────────────────────────────────

    public function test_admin_ticket_detail_returns_coordinates(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::create([
            'code' => 'TKT-20261003-9001',
            'title' => 'Ada titik',
            'description' => 'Deskripsi.',
            'reporter_name' => 'Pelapor',
            'lat' => -1.535214,
            'lng' => 114.861892,
        ]);

        $this->actingAs($admin)
            ->get('/admin/layanan/'.$ticket->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/TiketDetail')
                ->where('ticket.id', $ticket->id)
                ->where('ticket.lat', fn ($v) => abs((float) $v - (-1.535214)) < 1e-6)
                ->where('ticket.lng', fn ($v) => abs((float) $v - 114.861892) < 1e-6));
    }
}
