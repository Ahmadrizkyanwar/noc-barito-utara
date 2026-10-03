<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VpsRequest;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertSame(['22', '443', '80'], $row->ports);
        $this->assertSame(VpsRequest::STATUS_PENDING, $row->status);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->approvedUser())
            ->from('/vps')
            ->post('/vps', ['name' => ''])
            ->assertSessionHasErrors([
                'name', 'nip', 'jabatan', 'instansi',
                'cores', 'ram_gb', 'public_ips', 'ports', 'purpose',
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
