<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VpsRequest;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Fitur Request VPS: terkunci untuk akun pending, form, review admin/operator.
 */
class VpsRequestTest extends TestCase
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
            'os' => 'ubuntu-2404',
            'ports' => ['22', '443', '80'],
            'purpose' => 'Portal e-learning instansi.',
        ];
    }

    // ── Akses & terkunci ────────────────────────────────────────────────────

    public function test_vps_page_requires_login(): void
    {
        $this->get('/vps')->assertRedirect(route('login'));
    }

    public function test_pending_user_sees_locked_page(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_PENDING]);

        $this->actingAs($user)
            ->get('/vps')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('VpsRequest')
                ->where('canRequest', false));
    }

    public function test_pending_user_cannot_store_request(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_PENDING]);

        $this->actingAs($user)
            ->post('/vps', $this->validPayload())
            ->assertSessionHas('error');

        $this->assertDatabaseCount('vps_requests', 0);
    }

    public function test_approved_user_sees_open_form(): void
    {
        $this->actingAs($this->approvedUser())
            ->get('/vps')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('VpsRequest')
                ->where('canRequest', true));
    }

    // ── Penyimpanan ─────────────────────────────────────────────────────────

    public function test_approved_user_can_store_request(): void
    {
        $user = $this->approvedUser();

        $this->actingAs($user)
            ->post('/vps', $this->validPayload())
            ->assertRedirect(route('vps.index'))
            ->assertSessionHas('success');

        $row = VpsRequest::first();
        $this->assertNotNull($row);
        $this->assertSame($user->id, $row->user_id);
        $this->assertSame(4, $row->cores);
        $this->assertSame(8, $row->ram_gb);
        $this->assertSame(2, $row->public_ips);
        $this->assertSame('ubuntu-2404', $row->os);
        $this->assertSame(['22', '443', '80'], $row->ports);
        $this->assertSame(VpsRequest::STATUS_PENDING, $row->status);
    }

    public function test_store_rejects_unknown_os(): void
    {
        $payload = $this->validPayload();
        $payload['os'] = 'ms-dos';

        $this->actingAs($this->approvedUser())
            ->from('/vps')
            ->post('/vps', $payload)
            ->assertSessionHasErrors('os');

        $this->assertDatabaseCount('vps_requests', 0);
    }

    public function test_store_requires_os_other_when_os_is_lainnya(): void
    {
        $payload = $this->validPayload();
        $payload['os'] = 'lainnya';

        $this->actingAs($this->approvedUser())
            ->from('/vps')
            ->post('/vps', $payload)
            ->assertSessionHasErrors('os_other');

        $this->assertDatabaseCount('vps_requests', 0);
    }

    public function test_store_keeps_typed_os_when_os_is_lainnya(): void
    {
        $user = $this->approvedUser();
        $payload = $this->validPayload();
        $payload['os'] = 'lainnya';
        $payload['os_other'] = 'Proxmox VE 8';

        $this->actingAs($user)
            ->post('/vps', $payload)
            ->assertRedirect(route('vps.index'))
            ->assertSessionHas('success');

        $row = VpsRequest::first();
        $this->assertSame('lainnya', $row->os);
        $this->assertSame('Proxmox VE 8', $row->os_other);
    }

    public function test_store_clears_os_other_when_os_is_not_lainnya(): void
    {
        $user = $this->approvedUser();
        $payload = $this->validPayload();
        $payload['os_other'] = 'Harus Dibuang';

        $this->actingAs($user)
            ->post('/vps', $payload)
            ->assertSessionHas('success');

        $this->assertNull(VpsRequest::first()->os_other);
    }

    public function test_store_accepts_custom_service_ports(): void
    {
        $user = $this->approvedUser();
        $payload = $this->validPayload();
        $payload['custom_ports'] = '8443, 9090, 3000-3100';

        $this->actingAs($user)
            ->post('/vps', $payload)
            ->assertRedirect(route('vps.index'))
            ->assertSessionHas('success');

        $this->assertSame('8443, 9090, 3000-3100', VpsRequest::first()->custom_ports);
    }

    public function test_store_rejects_invalid_custom_service_ports(): void
    {
        $payload = $this->validPayload();
        $payload['custom_ports'] = 'port-tua, 8080';

        $this->actingAs($this->approvedUser())
            ->from('/vps')
            ->post('/vps', $payload)
            ->assertSessionHasErrors('custom_ports');

        $this->assertDatabaseCount('vps_requests', 0);
    }

    public function test_store_accepts_only_custom_service_ports(): void
    {
        $user = $this->approvedUser();
        $payload = $this->validPayload();
        $payload['ports'] = [];
        $payload['custom_ports'] = '8443, 9090';

        $this->actingAs($user)
            ->post('/vps', $payload)
            ->assertRedirect(route('vps.index'))
            ->assertSessionHas('success');

        $row = VpsRequest::first();
        $this->assertSame([], $row->ports);
        $this->assertSame('8443, 9090', $row->custom_ports);
    }

    public function test_store_accepts_supporting_document_and_owner_can_download_it(): void
    {
        Storage::fake('public');

        $user = $this->approvedUser();
        $stranger = User::factory()->create();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        $payload = $this->validPayload();
        $payload['supporting_document'] = UploadedFile::fake()->createWithContent('surat.png', $png);

        $this->actingAs($user)
            ->post('/vps', $payload)
            ->assertRedirect(route('vps.index'))
            ->assertSessionHas('success');

        $row = VpsRequest::first();
        $this->assertNotNull($row->supporting_document);
        $this->assertNotNull($row->supporting_document_uploaded_at);
        Storage::disk('public')->assertExists($row->supporting_document);

        // Pemilik boleh unduh
        $this->actingAs($user)
            ->get(route('vps.document', $row))
            ->assertOk()
            ->assertDownload('dokumen-pendukung-'.$row->code.'.png');

        // User lain ditolak
        $this->actingAs($stranger)
            ->get(route('vps.document', $row))
            ->assertForbidden();
    }

    public function test_store_rejects_unsupported_supporting_document(): void
    {
        Storage::fake('public');

        $payload = $this->validPayload();
        $payload['supporting_document'] = UploadedFile::fake()->createWithContent('script.php', '<?php echo 1;');

        $this->actingAs($this->approvedUser())
            ->from('/vps')
            ->post('/vps', $payload)
            ->assertSessionHasErrors('supporting_document');

        $this->assertDatabaseCount('vps_requests', 0);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->approvedUser())
            ->from('/vps')
            ->post('/vps', ['name' => ''])
            ->assertSessionHasErrors([
                'name', 'nip', 'jabatan', 'instansi',
                'cores', 'ram_gb', 'public_ips', 'os', 'ports', 'purpose',
            ]);

        $this->assertDatabaseCount('vps_requests', 0);
    }

    public function test_store_rejects_unknown_port(): void
    {
        $payload = $this->validPayload();
        $payload['ports'] = ['99999'];

        $this->actingAs($this->approvedUser())
            ->from('/vps')
            ->post('/vps', $payload)
            ->assertSessionHasErrors('ports.0');

        $this->assertDatabaseCount('vps_requests', 0);
    }

    public function test_store_requires_at_least_one_port(): void
    {
        $payload = $this->validPayload();
        $payload['ports'] = [];

        $this->actingAs($this->approvedUser())
            ->from('/vps')
            ->post('/vps', $payload)
            ->assertSessionHasErrors('ports');
    }

    public function test_request_history_shows_on_dashboard_not_on_form_page(): void
    {
        $user = $this->approvedUser();
        $this->actingAs($user)->post('/vps', $this->validPayload())->assertSessionHas('success');
        $req = VpsRequest::latest('id')->firstOrFail();

        // Riwayat ada di Dashboard
        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->has('vpsRequests', 1)
                ->where('vpsRequests.0.code', $req->code)
                ->where('vpsStatuses.pending', 'Menunggu Review'));

        // Halaman form TIDAK lagi memuat daftar riwayat
        $this->actingAs($user)
            ->get('/vps')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('VpsRequest')
                ->missing('requests')
                ->where('canRequest', true));
    }

    // ── Review admin/operator ───────────────────────────────────────────────

    public function test_admin_approves_request_with_note(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->admin()->create();
        $user = $this->approvedUser();
        $req = $user->vpsRequests()->create($this->validPayload());

        $this->actingAs($admin)
            ->patch('/admin/vps/'.$req->id.'/status', [
                'status' => 'approved',
                'admin_note' => 'Disetujui, hubungi NOC.',
            ])
            ->assertStatus(302)
            ->assertSessionHas('success');

        $fresh = $req->fresh();
        $this->assertSame(VpsRequest::STATUS_APPROVED, $fresh->status);
        $this->assertSame('Disetujui, hubungi NOC.', $fresh->admin_note);
        $this->assertSame($admin->id, $fresh->reviewed_by);
        $this->assertNotNull($fresh->reviewed_at);
    }

    public function test_operator_can_access_review_pages(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->actingAs($operator)->get('/admin/registrasi')->assertOk();
        $this->actingAs($operator)->get('/admin/vps')->assertOk();
    }

    public function test_operator_cannot_access_admin_only_pages(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->actingAs($operator)->get('/admin/jaringan')->assertForbidden();
        $this->actingAs($operator)->get('/admin/pengaturan/webhook')->assertForbidden();
    }

    public function test_guest_cannot_review(): void
    {
        $this->get('/admin/vps')->assertRedirect(route('login'));
    }
}
