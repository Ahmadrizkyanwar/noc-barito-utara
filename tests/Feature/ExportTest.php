<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Export laporan (PDF/Excel) — akses admin/operator, filter rentang waktu.
 */
class ExportTest extends TestCase
{
    use RefreshDatabase;

    protected function operator(): User
    {
        return User::factory()->create(['role' => User::ROLE_OPERATOR]);
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function makeTicket(string $code): Ticket
    {
        return Ticket::create([
            'code' => $code,
            'title' => 'UJI export tiket',
            'category' => 'Jaringan',
            'description' => 'Deskripsi uji export tiket gangguan.',
            'status' => 'open',
        ]);
    }

    // ── Akses ───────────────────────────────────────────────────────────────

    public function test_guest_cannot_access_export(): void
    {
        $this->get('/admin/export')->assertRedirect(route('login'));
    }

    public function test_regular_user_cannot_access_export(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_APPROVED]);

        $this->actingAs($user)->get('/admin/export')->assertForbidden();
        $this->actingAs($user)->get('/admin/export/download')->assertForbidden();
    }

    public function test_operator_and_admin_can_open_export_page(): void
    {
        $this->actingAs($this->operator())
            ->get('/admin/export')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Export')
                ->where('formats.excel', 'Excel (XLSX)')
                ->where('datasets.hosting', 'Pendaftaran Hosting'));

        $this->actingAs($this->admin())->get('/admin/export')->assertOk();
    }

    // ── Filter rentang waktu ────────────────────────────────────────────────

    public function test_preview_counts_only_selected_range(): void
    {
        $this->makeTicket('TKT-TEST-0001');
        $old = $this->makeTicket('TKT-TEST-0002');
        $old->created_at = now()->subDays(10);
        $old->save();

        $admin = $this->admin();

        // Harian → hanya tiket hari ini
        $this->actingAs($admin)
            ->getJson('/admin/export/preview?dataset=tiket&format=pdf&periode=harian')
            ->assertOk()
            ->assertJsonPath('count', 1);

        // Mingguan (7 hari) → tiket 10 hari lalu tidak ikut
        $this->actingAs($admin)
            ->getJson('/admin/export/preview?dataset=tiket&format=pdf&periode=mingguan')
            ->assertOk()
            ->assertJsonPath('count', 1);

        // Rentang tanggal lebar → keduanya ikut
        $this->actingAs($admin)
            ->getJson('/admin/export/preview?dataset=tiket&format=pdf&periode=custom'
                .'&dari='.now()->subDays(12)->toDateString()
                .'&sampai='.now()->toDateString())
            ->assertOk()
            ->assertJsonPath('count', 2);
    }

    public function test_preview_combines_all_datasets_when_selected(): void
    {
        $this->makeTicket('TKT-TEST-0101');

        $user = User::factory()->create(['status' => User::STATUS_APPROVED]);
        $this->actingAs($user)->post('/vps', [
            'name' => 'Budi',
            'nip' => '198701012010011001',
            'jabatan' => 'Analis',
            'instansi' => 'Diskominfosandi',
            'cores' => 2,
            'ram_gb' => 4,
            'public_ips' => 1,
            'os' => 'debian-12',
            'ports' => ['22'],
            'purpose' => 'Uji export.',
        ])->assertSessionHas('success');

        $this->makeService('domain');

        $admin = $this->admin();

        $this->actingAs($admin)
            ->getJson('/admin/export/preview?dataset=semua&format=pdf&periode=harian')
            ->assertOk()
            ->assertJsonPath('count', 3);

        $this->actingAs($admin)
            ->getJson('/admin/export/preview?dataset=domain&format=excel&periode=harian')
            ->assertOk()
            ->assertJsonPath('count', 1);
    }

    // ── Unduhan ─────────────────────────────────────────────────────────────

    public function test_excel_download_returns_xlsx_file(): void
    {
        $this->makeTicket('TKT-TEST-0201');

        $this->actingAs($this->admin())
            ->get('/admin/export/download?dataset=tiket&format=excel&periode=harian')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertDownload();
    }

    public function test_pdf_download_returns_pdf_file(): void
    {
        $this->makeTicket('TKT-TEST-0301');

        $this->actingAs($this->operator())
            ->get('/admin/export/download?dataset=tiket&format=pdf&periode=harian')
            ->assertOk()
            ->assertDownload();
    }

    // ── Validasi ────────────────────────────────────────────────────────────

    public function test_export_rejects_unknown_format_and_dataset(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from('/admin/export')
            ->get('/admin/export/download?dataset=tiket&format=csv&periode=harian')
            ->assertRedirect('/admin/export')
            ->assertSessionHasErrors('format');

        $this->actingAs($admin)
            ->from('/admin/export')
            ->get('/admin/export/download?dataset=forum&format=pdf&periode=harian')
            ->assertSessionHasErrors('dataset');
    }

    public function test_custom_range_requires_both_dates_and_valid_order(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from('/admin/export')
            ->get('/admin/export/download?dataset=tiket&format=pdf&periode=custom')
            ->assertSessionHasErrors(['dari', 'sampai']);

        $this->actingAs($admin)
            ->from('/admin/export')
            ->get('/admin/export/download?dataset=tiket&format=pdf&periode=custom'
                .'&dari='.now()->toDateString()
                .'&sampai='.now()->subDays(3)->toDateString())
            ->assertSessionHasErrors('sampai');
    }

    // ── Helper ──────────────────────────────────────────────────────────────

    protected function makeService(string $type): void
    {
        User::factory()->create(['status' => User::STATUS_APPROVED])
            ->serviceRegistrations()->create([
                'type' => $type,
                'code' => strtoupper(substr($type, 0, 3)).'-TEST-0001',
                'name' => 'Budi Santoso',
                'nip' => '198701012010011001',
                'jabatan' => 'Analis',
                'instansi' => 'Diskominfosandi',
                'domain_name' => $type === 'domain' ? 'contoh.go.id' : null,
                'hosting_package' => $type === 'hosting' ? 'shared-1gb' : null,
                'duration' => 3,
                'purpose' => 'Uji export.',
            ]);
    }
}
