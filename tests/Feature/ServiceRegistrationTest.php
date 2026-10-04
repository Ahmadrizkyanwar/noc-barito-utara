<?php

namespace Tests\Feature;

use App\Models\ServiceRegistration;
use App\Models\TelegramWebhook;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pendaftaran Domain & Hosting: terkunci untuk akun pending, form,
 * review admin/operator, lonceng + Telegram webhook `tiket`.
 */
class ServiceRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function approvedUser(): User
    {
        return User::factory()->create(['status' => User::STATUS_APPROVED]);
    }

    protected function domainPayload(): array
    {
        return [
            'name' => 'Budi Santoso',
            'nip' => '198701012010011001',
            'jabatan' => 'Analis Kebijakan',
            'instansi' => 'Dinas Pendidikan',
            'domain_name' => 'diskominfosandi.go.id',
            'duration' => 3,
            'purpose' => 'Portal layanan publik instansi.',
        ];
    }

    protected function hostingPayload(): array
    {
        return [
            'name' => 'Budi Santoso',
            'nip' => '198701012010011001',
            'jabatan' => 'Analis Kebijakan',
            'instansi' => 'Dinas Pendidikan',
            'hosting_package' => 'shared-1gb',
            'domain_name' => 'portal.baritoutarakab.go.id',
            'duration' => 12,
            'purpose' => 'Hosting portal berita daerah.',
        ];
    }

    protected function tiketWebhook(): TelegramWebhook
    {
        $wh = TelegramWebhook::firstOrCreate(
            ['id' => TelegramWebhook::ID_TIKET],
            ['label' => 'Tiket', 'enabled' => false]
        );

        $wh->update(['enabled' => true, 'bot_token' => '123:token', 'chat_id' => '-1001']);

        return $wh->refresh();
    }

    // ── Akses & terkunci ────────────────────────────────────────────────────

    public function test_domain_page_requires_login(): void
    {
        $this->get('/domain')->assertRedirect(route('login'));
        $this->get('/hosting')->assertRedirect(route('login'));
    }

    public function test_pending_user_sees_locked_domain_page(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_PENDING]);

        $this->actingAs($user)
            ->get('/domain')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ServiceRegistration')
                ->where('type', 'domain')
                ->where('canRequest', false));
    }

    public function test_pending_user_cannot_store_registration(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_PENDING]);

        $this->actingAs($user)
            ->post('/domain', $this->domainPayload())
            ->assertSessionHas('error');

        $this->assertDatabaseCount('service_registrations', 0);
    }

    public function test_approved_user_sees_open_hosting_form(): void
    {
        $this->actingAs($this->approvedUser())
            ->get('/hosting')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ServiceRegistration')
                ->where('type', 'hosting')
                ->where('canRequest', true));
    }

    // ── Penyimpanan ─────────────────────────────────────────────────────────

    public function test_approved_user_can_store_domain_registration(): void
    {
        $user = $this->approvedUser();

        $this->actingAs($user)
            ->post('/domain', $this->domainPayload())
            ->assertRedirect(route('domain.index'))
            ->assertSessionHas('success');

        $row = ServiceRegistration::first();
        $this->assertNotNull($row);
        $this->assertSame(ServiceRegistration::TYPE_DOMAIN, $row->type);
        $this->assertMatchesRegularExpression('/^DOM-\d{8}-0001$/', $row->code);
        $this->assertSame('diskominfosandi.go.id', $row->domain_name);
        $this->assertSame(3, $row->duration);
        $this->assertSame(ServiceRegistration::STATUS_PENDING, $row->status);
        $this->assertSame($user->id, $row->user_id);
    }

    public function test_approved_user_can_store_hosting_registration(): void
    {
        $this->actingAs($this->approvedUser())
            ->post('/hosting', $this->hostingPayload())
            ->assertRedirect(route('hosting.index'))
            ->assertSessionHas('success');

        $row = ServiceRegistration::first();
        $this->assertSame(ServiceRegistration::TYPE_HOSTING, $row->type);
        $this->assertMatchesRegularExpression('/^HST-\d{8}-0001$/', $row->code);
        $this->assertSame('shared-1gb', $row->hosting_package);
        $this->assertSame(12, $row->duration);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->approvedUser())
            ->from('/domain')
            ->post('/domain', ['name' => ''])
            ->assertSessionHasErrors([
                'name', 'nip', 'jabatan', 'instansi',
                'domain_name', 'duration', 'purpose',
            ]);

        $this->assertDatabaseCount('service_registrations', 0);
    }

    public function test_store_rejects_invalid_domain_name(): void
    {
        $payload = $this->domainPayload();
        $payload['domain_name'] = 'bukan-domain';

        $this->actingAs($this->approvedUser())
            ->from('/domain')
            ->post('/domain', $payload)
            ->assertSessionHasErrors('domain_name');

        $this->assertDatabaseCount('service_registrations', 0);
    }

    public function test_store_rejects_unknown_hosting_package(): void
    {
        $payload = $this->hostingPayload();
        $payload['hosting_package'] = 'paket-tidak-ada';

        $this->actingAs($this->approvedUser())
            ->from('/hosting')
            ->post('/hosting', $payload)
            ->assertSessionHasErrors('hosting_package');
    }

    public function test_store_rejects_unavailable_duration(): void
    {
        $payload = $this->domainPayload();
        $payload['duration'] = 4; // tidak ada di pilihan (1,2,3,5)

        $this->actingAs($this->approvedUser())
            ->from('/domain')
            ->post('/domain', $payload)
            ->assertSessionHasErrors('duration');
    }

    public function test_store_accepts_supporting_document_and_owner_can_download_it(): void
    {
        Storage::fake('public');

        $user = $this->approvedUser();
        $stranger = User::factory()->create();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        $payload = $this->domainPayload();
        $payload['supporting_document'] = UploadedFile::fake()->createWithContent('surat.png', $png);

        $this->actingAs($user)
            ->post('/domain', $payload)
            ->assertSessionHas('success');

        $row = ServiceRegistration::first();
        $this->assertNotNull($row->supporting_document);
        $this->assertNotNull($row->supporting_document_uploaded_at);
        Storage::disk('public')->assertExists($row->supporting_document);

        $this->actingAs($user)
            ->get(route('service.document', $row))
            ->assertOk()
            ->assertDownload('dokumen-'.$row->code.'.png');

        $this->actingAs($stranger)
            ->get(route('service.document', $row))
            ->assertForbidden();
    }

    // ── Notifikasi ──────────────────────────────────────────────────────────

    public function test_new_registration_notifies_reviewers(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->admin()->create();

        $this->actingAs($this->approvedUser())->post('/domain', $this->domainPayload());

        $this->assertSame(1, $admin->notifications()->where('data->kind', 'service_request')->count());
    }

    public function test_new_registration_sends_telegram_notification(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        $this->tiketWebhook();

        $this->actingAs($this->approvedUser())
            ->post('/hosting', $this->hostingPayload())
            ->assertSessionHas('success');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.telegram.org/bot123:token/sendMessage')
            && str_contains($request['text'] ?? '', 'PENDAFTARAN HOSTING BARU'));
    }

    public function test_telegram_failure_does_not_break_registration(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response('nope', 500)]);
        $this->tiketWebhook();

        $this->actingAs($this->approvedUser())
            ->post('/domain', $this->domainPayload())
            ->assertSessionHas('success');

        $this->assertDatabaseCount('service_registrations', 1);
    }

    // ── Review admin/operator ───────────────────────────────────────────────

    public function test_admin_approves_registration_with_note(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->admin()->create();
        $user = $this->approvedUser();
        $this->actingAs($user)->post('/domain', $this->domainPayload());
        $reg = ServiceRegistration::firstOrFail();

        $this->actingAs($admin)
            ->patch('/admin/pendaftaran/'.$reg->id.'/status', [
                'status' => 'approved',
                'admin_note' => 'Domain disiapkan NOC.',
            ])
            ->assertStatus(302)
            ->assertSessionHas('success');

        $fresh = $reg->fresh();
        $this->assertSame(ServiceRegistration::STATUS_APPROVED, $fresh->status);
        $this->assertSame('Domain disiapkan NOC.', $fresh->admin_note);
        $this->assertSame($admin->id, $fresh->reviewed_by);
        $this->assertNotNull($fresh->reviewed_at);

        // Lonceng untuk pemilik
        $this->assertSame(1, $user->notifications()->where('data->kind', 'service_status')->count());
    }

    public function test_operator_can_access_review_pages(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->actingAs($operator)
            ->get('/admin/pendaftaran')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/ServiceReview'));

        // Filter tipe & status
        $this->actingAs($operator)->get('/admin/pendaftaran?type=hosting&status=pending')->assertOk();
    }

    public function test_user_role_cannot_access_review_page(): void
    {
        $user = $this->approvedUser();

        $this->actingAs($user)->get('/admin/pendaftaran')->assertForbidden();
    }

    public function test_guest_cannot_review(): void
    {
        $this->get('/admin/pendaftaran')->assertRedirect(route('login'));
    }

    // ── Halaman tiket (Layanan) ─────────────────────────────────────────────

    public function test_registrations_appear_in_layanan_ticketing(): void
    {
        $user = $this->approvedUser();
        $this->actingAs($user)->post('/domain', $this->domainPayload())->assertSessionHas('success');
        $this->actingAs($user)->post('/hosting', $this->hostingPayload())->assertSessionHas('success');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/layanan')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Layanan')
                ->where('tickets.data.0.kind', 'service')
                ->where('stats.service_pending', 2));

        // Filter status review (pending) tetap menampilkan pendaftaran
        $this->actingAs($admin)
            ->get('/admin/layanan?status=pending')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('tickets.data', 2));
    }

    // ── Dashboard ───────────────────────────────────────────────────────────

    public function test_dashboard_shows_registration_summary_and_history(): void
    {
        $user = $this->approvedUser();
        $this->actingAs($user)->post('/domain', $this->domainPayload())->assertSessionHas('success');
        $this->actingAs($user)->post('/hosting', $this->hostingPayload())->assertSessionHas('success');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('serviceSummary.domain.total', 1)
                ->where('serviceSummary.hosting.total', 1)
                ->where('serviceSummary.domain.pending', 1)
                ->has('serviceRequests', 2)
                ->where('serviceRequests.0.type', 'hosting'));
    }
}
