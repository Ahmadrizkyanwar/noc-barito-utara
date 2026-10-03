<?php

namespace Tests\Feature;

use App\Models\TelegramWebhook;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TelegramWebhook::firstOrCreate(
            ['id' => TelegramWebhook::ID_TIKET],
            ['label' => 'Tiket', 'enabled' => false]
        );
    }

    public function test_public_report_page_can_be_rendered(): void
    {
        $this->get('/lapor')->assertOk()->assertInertia(fn ($page) => $page->component('Laporan'));
    }

    public function test_guest_can_create_ticket(): void
    {
        $response = $this->post('/lapor', [
            'title' => 'Internet kantor mati',
            'category' => 'Jaringan',
            'description' => 'Tidak bisa akses sama sekali sejak pagi.',
            'location' => 'Ruang Setda',
            'reporter_name' => 'Budi',
            'reporter_contact' => '08123456789',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('tickets', [
            'title' => 'Internet kantor mati',
            'reporter_name' => 'Budi',
            'user_id' => null,
            'status' => 'open',
        ]);

        $ticket = Ticket::first();
        $this->assertMatchesRegularExpression('/^TKT-\d{8}-\d{4}$/', $ticket->code);
    }

    public function test_ticket_code_is_sequential_per_day(): void
    {
        $this->post('/lapor', $this->validPayload());
        $this->post('/lapor', $this->validPayload());

        $codes = Ticket::orderBy('id')->pluck('code')->all();

        $this->assertCount(2, $codes);
        $this->assertNotSame($codes[0], $codes[1]);
        $this->assertSame(substr($codes[0], -4), '0001');
        $this->assertSame(substr($codes[1], -4), '0002');
    }

    public function test_ticket_creation_requires_title_and_description(): void
    {
        $this->from('/lapor')
            ->post('/lapor', ['reporter_name' => 'X'])
            ->assertSessionHasErrors(['title', 'description']);
    }

    public function test_invalid_category_is_rejected(): void
    {
        $payload = $this->validPayload();
        $payload['category'] = 'Kategori Ngawur';

        $this->from('/lapor')
            ->post('/lapor', $payload)
            ->assertSessionHasErrors('category');
    }

    public function test_logged_in_user_ticket_is_linked_to_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/lapor', $this->validPayload());

        $this->assertDatabaseHas('tickets', ['user_id' => $user->id]);
    }

    public function test_photo_upload_is_stored(): void
    {
        Storage::fake('public');

        // PNG 1x1 valid — `fake()->image()` butuh ekstensi GD yang tidak ada di image test.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        $this->post('/lapor', [
            ...$this->validPayload(),
            'photo' => UploadedFile::fake()->createWithContent('bukti.png', $png),
        ])->assertSessionHas('success');

        $path = Ticket::first()->photo;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_new_ticket_sends_telegram_notification(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        TelegramWebhook::find(TelegramWebhook::ID_TIKET)->update([
            'enabled' => true,
            'bot_token' => '123:token',
            'chat_id' => '-1001',
        ]);

        $this->post('/lapor', $this->validPayload());

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.telegram.org/bot123:token/sendMessage'));
    }

    public function test_notification_failure_does_not_break_ticket_creation(): void
    {
        TelegramWebhook::find(TelegramWebhook::ID_TIKET)->update([
            'enabled' => true,
            'bot_token' => '123:token',
            'chat_id' => '-1001',
        ]);
        Http::fake(['api.telegram.org/*' => Http::response('nope', 500)]);

        $this->post('/lapor', $this->validPayload())->assertSessionHas('success');

        $this->assertSame(1, Ticket::count());
    }

    public function test_admin_can_change_ticket_status_with_activity_trail(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::create([
            'code' => 'TKT-20261003-0001',
            'title' => 'Uji',
            'category' => 'Jaringan',
            'description' => 'Uji status',
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->patchJson('/admin/layanan/'.$ticket->id.'/status', ['status' => 'proses'])
            ->assertOk()
            ->assertJsonPath('ticket.status', 'proses');

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'action' => 'status',
            'old_value' => 'open',
            'new_value' => 'proses',
        ]);
    }

    public function test_resolving_ticket_sets_resolved_at(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::create([
            'code' => 'TKT-20261003-0002',
            'title' => 'Uji',
            'category' => 'Jaringan',
            'description' => 'Uji status',
            'status' => 'proses',
        ]);

        $this->actingAs($admin)
            ->patchJson('/admin/layanan/'.$ticket->id.'/status', ['status' => 'selesai'])
            ->assertOk();

        $this->assertNotNull($ticket->fresh()->resolved_at);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::create([
            'code' => 'TKT-20261003-0003',
            'title' => 'Uji',
            'category' => 'Jaringan',
            'description' => 'Uji',
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->patchJson('/admin/layanan/'.$ticket->id.'/status', ['status' => 'ngawur'])
            ->assertStatus(422);
    }

    public function test_user_dashboard_lists_own_tickets_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Ticket::create([
            'code' => 'TKT-20261003-0010', 'title' => 'Punya saya',
            'category' => 'Jaringan', 'description' => 'x', 'user_id' => $user->id,
        ]);
        Ticket::create([
            'code' => 'TKT-20261003-0011', 'title' => 'Punya orang lain',
            'category' => 'Jaringan', 'description' => 'x', 'user_id' => $other->id,
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('view', 'user')
                ->has('tickets', 1)
                ->where('tickets.0.title', 'Punya saya'));
    }

    public function test_admin_dashboard_shows_admin_view(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('view', 'admin')
                ->has('stats')
                ->has('devices'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function validPayload(): array
    {
        return [
            'title' => 'Gangguan uji',
            'category' => 'Jaringan',
            'description' => 'Deskripsi gangguan untuk pengujian.',
            'reporter_name' => 'Pelapor Uji',
        ];
    }
}
