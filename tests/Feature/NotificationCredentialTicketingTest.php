<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VpsRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Notifikasi in-app, kode VPS pada sistem ticketing, dokumen kredensial.
 */
class NotificationCredentialTicketingTest extends TestCase
{
    use RefreshDatabase;

    protected function approvedUser(): User
    {
        return User::factory()->create(['status' => User::STATUS_APPROVED]);
    }

    protected function validPayload(): array
    {
        return [
            'name' => 'Budi Santoso',
            'nip' => '198701012010011001',
            'jabatan' => 'Analis Kebijakan',
            'instansi' => 'Dinas Pendidikan',
            'cores' => 4,
            'ram_gb' => 8,
            'public_ips' => 2,
            'ports' => ['22', '443'],
            'purpose' => 'Portal e-learning instansi.',
        ];
    }

    protected function createRequestAsApprovedUser(?User $user = null): VpsRequest
    {
        $user ??= $this->approvedUser();

        $this->actingAs($user)->post('/vps', $this->validPayload())->assertSessionHas('success');

        return VpsRequest::latest('id')->firstOrFail();
    }

    protected function approve(VpsRequest $req, User $admin): void
    {
        $this->actingAs($admin)
            ->patch('/admin/vps/'.$req->id.'/status', ['status' => 'approved'])
            ->assertStatus(302);
    }

    /** Jumlah notifikasi unread dengan kind tertentu. */
    protected function unreadKind(User $user, string $kind): int
    {
        return $user->refresh()
            ->unreadNotifications()
            ->get()
            ->filter(fn ($n) => ($n->data['kind'] ?? null) === $kind)
            ->count();
    }

    // ── Kode VPS (sistem ticketing) ─────────────────────────────────────────

    public function test_vps_request_gets_vps_prefixed_code_with_daily_sequence(): void
    {
        $user = $this->approvedUser();

        $this->actingAs($user)->post('/vps', $this->validPayload())->assertSessionHas('success');
        $this->actingAs($user)->post('/vps', $this->validPayload())->assertSessionHas('success');

        $codes = VpsRequest::orderBy('id')->pluck('code')->all();

        $this->assertSame(
            ['VPS-'.now()->format('Ymd').'-0001', 'VPS-'.now()->format('Ymd').'-0002'],
            $codes
        );
        $this->assertDoesNotMatchRegularExpression('/^TKT-/', $codes[0]);
    }

    public function test_vps_requests_appear_in_layanan_ticketing_with_own_code(): void
    {
        $admin = User::factory()->admin()->create();
        $req = $this->createRequestAsApprovedUser();

        $this->actingAs($admin)
            ->get('/admin/layanan')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Layanan')
                ->where('tickets.data.0.kind', 'vps')
                ->where('tickets.data.0.code', $req->code)
                ->where('tickets.data.0.category', 'VPS')
                ->where('allStatuses.pending', 'Menunggu Review'));
    }

    // ── Notifikasi in-app ───────────────────────────────────────────────────

    public function test_registration_notifies_admins_and_operators_only(): void
    {
        $admin = User::factory()->admin()->create();
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        $plain = User::factory()->create();

        $this->post('/registrasi', [
            'name' => 'Pendaftar Baru',
            'email' => 'baru@example.go.id',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertRedirect(route('verification.notice'));

        $this->assertSame(1, $admin->refresh()->unreadNotifications()->count());
        $this->assertSame(1, $operator->refresh()->unreadNotifications()->count());
        $this->assertSame(0, $plain->refresh()->unreadNotifications()->count());

        $data = $admin->notifications()->first()->data;
        $this->assertSame('register', $data['kind']);
        $this->assertStringContainsString('Pendaftar Baru', $data['body']);
    }

    public function test_new_vps_request_notifies_reviewers_not_owner(): void
    {
        $admin = User::factory()->admin()->create();
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        $owner = $this->approvedUser();

        $req = $this->createRequestAsApprovedUser($owner);

        $this->assertSame(1, $admin->refresh()->unreadNotifications()->count());
        $this->assertSame(1, $operator->refresh()->unreadNotifications()->count());
        $this->assertSame(0, $owner->refresh()->unreadNotifications()->count());
        $this->assertSame('vps_request', $admin->notifications()->first()->data['kind']);
        $this->assertStringContainsString($req->code, $admin->notifications()->first()->data['body']);
    }

    public function test_status_change_notifies_request_owner(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = $this->approvedUser();
        $req = $this->createRequestAsApprovedUser($owner);

        $this->approve($req, $admin);

        $this->assertSame(1, $owner->refresh()->unreadNotifications()->count());

        $data = $owner->notifications()->first()->data;
        $this->assertSame('vps_status', $data['kind']);
        $this->assertStringContainsString('Disetujui', $data['title']);
    }

    public function test_dashboard_shares_unread_notification_count(): void
    {
        $admin = User::factory()->admin()->create();
        $this->createRequestAsApprovedUser();

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('notifications.unread', 1));
    }

    // ── Dokumen kredensial ──────────────────────────────────────────────────

    public function test_credentials_upload_notifies_owner_and_other_reviewers(): void
    {
        Storage::fake('public');

        $uploader = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        $owner = $this->approvedUser();
        $req = $this->createRequestAsApprovedUser($owner);
        $this->approve($req, $uploader);

        $this->actingAs($uploader)
            ->post('/admin/vps/'.$req->id.'/credentials', [
                'credential' => UploadedFile::fake()->createWithContent('kred.txt', "root:rahasia\nip:10.0.0.1\n"),
            ])
            ->assertStatus(302)
            ->assertSessionHas('success');

        $fresh = $req->fresh();
        $this->assertNotNull($fresh->credential_file);
        $this->assertNotNull($fresh->credential_uploaded_at);
        Storage::disk('public')->assertExists($fresh->credential_file);

        // Pemilik + reviewer lain (kecuali pengunggah) menerima notifikasi kredensial
        $this->assertSame(1, $this->unreadKind($owner, 'vps_credentials'));
        $this->assertSame(1, $this->unreadKind($operator, 'vps_credentials'));
        $this->assertSame(1, $this->unreadKind($otherAdmin, 'vps_credentials'));
        $this->assertSame(0, $this->unreadKind($uploader, 'vps_credentials'));
    }

    public function test_pending_request_cannot_upload_credentials(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $req = $this->createRequestAsApprovedUser(); // masih pending

        $this->actingAs($admin)
            ->post('/admin/vps/'.$req->id.'/credentials', [
                'credential' => UploadedFile::fake()->createWithContent('kred.txt', 'isi'),
            ])
            ->assertSessionHas('error');

        $this->assertNull($req->fresh()->credential_file);
        $this->assertSame(0, $req->user->refresh()->unreadNotifications()->count());
    }

    public function test_credentials_download_is_restricted_to_owner_and_reviewers(): void
    {
        Storage::fake('public');

        // Tamu → redirect login (dicek SEBELUM actingAs menempel di test berikutnya)
        $this->get('/vps/1/credentials')->assertRedirect(route('login'));

        $admin = User::factory()->admin()->create();
        $owner = $this->approvedUser();
        $stranger = User::factory()->create();
        $req = $this->createRequestAsApprovedUser($owner);
        $this->approve($req, $admin);

        $this->actingAs($admin)
            ->post('/admin/vps/'.$req->id.'/credentials', [
                'credential' => UploadedFile::fake()->createWithContent('kred.txt', 'isi kredensial'),
            ])
            ->assertStatus(302);

        // Pemilik boleh unduh
        $this->actingAs($owner)
            ->get(route('vps.credentials', $req))
            ->assertOk()
            ->assertDownload('kredensial-'.$req->code.'.txt');

        // Admin (reviewer) boleh unduh
        $this->actingAs($admin)
            ->get(route('vps.credentials', $req))
            ->assertOk();

        // User lain ditolak
        $this->actingAs($stranger)
            ->get(route('vps.credentials', $req))
            ->assertForbidden();
    }

    // ── Tandai dibaca ───────────────────────────────────────────────────────

    public function test_mark_read_only_for_own_notification(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = $this->approvedUser();
        $req = $this->createRequestAsApprovedUser($owner);
        $this->approve($req, $admin);

        $notification = $owner->notifications()->first();
        $this->assertNotNull($notification);

        $this->actingAs($owner)
            ->post('/notifications/'.$notification->id.'/read')
            ->assertStatus(302);

        $this->assertNotNull($notification->fresh()->read_at);

        // Notifikasi milik user lain tidak bisa ditandai
        $foreign = $admin->notifications()->first();
        $this->assertNotNull($foreign);

        $this->actingAs($owner)
            ->post('/notifications/'.$foreign->id.'/read')
            ->assertNotFound();

        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_read_all_marks_every_unread_notification(): void
    {
        $admin = User::factory()->admin()->create();
        $this->createRequestAsApprovedUser();
        $this->createRequestAsApprovedUser();

        $this->assertSame(2, $admin->refresh()->unreadNotifications()->count());

        $this->actingAs($admin)
            ->post('/notifications/read-all')
            ->assertStatus(302);

        $this->assertSame(0, $admin->refresh()->unreadNotifications()->count());
    }

    public function test_notification_links_are_relative_paths(): void
    {
        $admin = User::factory()->admin()->create();
        $this->createRequestAsApprovedUser();

        $link = $admin->notifications()->first()->data['link'] ?? '';

        // Path relatif agar tidak menunjuk domain lain bila dibuka via IP/LAN
        $this->assertStringStartsWith('/', $link);
        $this->assertStringNotContainsString('http', $link);
    }

    public function test_missing_credential_file_is_hidden_in_dashboard_list(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $owner = $this->approvedUser();
        $req = $this->createRequestAsApprovedUser($owner);
        $this->approve($req, $admin);

        $this->actingAs($admin)
            ->post('/admin/vps/'.$req->id.'/credentials', [
                'credential' => UploadedFile::fake()->createWithContent('kred.txt', 'isi'),
            ])
            ->assertStatus(302);

        $this->assertNotNull($req->fresh()->credential_file);

        // Simulasi file hilang (mis. storage pernah ter-reset tanpa volume)
        Storage::disk('public')->delete($req->fresh()->credential_file);

        $this->actingAs($owner)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('vpsRequests.0.credential_file', null));
    }
}
