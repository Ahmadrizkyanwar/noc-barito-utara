<?php

namespace Tests\Feature;

use App\Models\DashboardWidget;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function seedWidgets(): void
    {
        // Widget di-seed tanpa syarat (tidak tergantung WEB_PASSWORD)
        $this->seed(DatabaseSeeder::class);
    }

    // ── Seeder ─────────────────────────────────────────────────────────────

    public function test_widget_rows_are_seeded_with_defaults(): void
    {
        $this->seedWidgets();

        $this->assertSame(8, DashboardWidget::count());

        $on = DashboardWidget::where('enabled', true)->orderBy('sort_order')->pluck('id')->all();
        $this->assertSame(['status_ringkas', 'trend_trafik', 'cpu_rtt', 'status_perangkat', 'tiket_terbuka'], $on);

        $off = DashboardWidget::where('enabled', false)->orderBy('sort_order')->pluck('id')->all();
        $this->assertSame(['donut_status', 'top_perangkat', 'top_interface'], $off);
    }

    public function test_seeding_is_idempotent(): void
    {
        $this->seedWidgets();
        $this->seedWidgets();

        $this->assertSame(8, DashboardWidget::count());
    }

    // ── Props dashboard ────────────────────────────────────────────────────

    public function test_admin_dashboard_exposes_widget_selection_and_data(): void
    {
        $this->seedWidgets();

        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('view', 'admin')
                ->has('widgetOptions', 8)
                ->where('widgets', ['status_ringkas', 'trend_trafik', 'cpu_rtt', 'status_perangkat', 'tiket_terbuka'])
                ->has('trend.labels', 24)
                ->has('trend.rx', 24)
                ->has('trend.tx', 24)
                ->has('topDevices')
                ->has('topInterfaces'));
    }

    public function test_unseeded_dashboard_still_renders_with_empty_widgets(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('widgets', [])
                ->has('widgetOptions', 0));
    }

    // ── Update pilihan ─────────────────────────────────────────────────────

    public function test_admin_can_save_widget_selection(): void
    {
        $this->seedWidgets();
        $admin = $this->admin();

        $chosen = ['donut_status', 'top_interface', 'trend_trafik'];

        $this->actingAs($admin)
            ->from('/admin')
            ->patch('/admin/dashboard/widgets', ['enabled' => $chosen])
            ->assertRedirect('/admin')
            ->assertSessionHas('success');

        $enabled = DashboardWidget::where('enabled', true)->pluck('id')->sort()->values()->all();
        sort($chosen);
        $this->assertSame($chosen, $enabled);

        // Pilihan terbaca kembali di props
        $this->actingAs($admin)
            ->get('/admin')
            ->assertInertia(fn ($page) => $page->where('widgets', ['trend_trafik', 'donut_status', 'top_interface']));
    }

    public function test_saving_empty_selection_disables_all_widgets(): void
    {
        $this->seedWidgets();

        $this->actingAs($this->admin())
            ->patch('/admin/dashboard/widgets', ['enabled' => []])
            ->assertSessionHas('success');

        $this->assertSame(0, DashboardWidget::where('enabled', true)->count());
    }

    public function test_unknown_widget_id_is_rejected(): void
    {
        $this->seedWidgets();

        $this->actingAs($this->admin())
            ->patchJson('/admin/dashboard/widgets', ['enabled' => ['trend_trafik', 'widget-ngawur']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('enabled.1');

        // Tidak ada perubahan tersimpan
        $this->assertSame(5, DashboardWidget::where('enabled', true)->count());
    }

    public function test_enabled_must_be_array(): void
    {
        $this->seedWidgets();

        $this->actingAs($this->admin())
            ->patchJson('/admin/dashboard/widgets', ['enabled' => 'status_ringkas'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('enabled');
    }

    // ── Guard ──────────────────────────────────────────────────────────────

    public function test_user_role_cannot_update_widgets(): void
    {
        $this->seedWidgets();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/admin/dashboard/widgets', ['enabled' => []])
            ->assertForbidden();
    }

    public function test_guest_cannot_update_widgets(): void
    {
        $this->seedWidgets();

        $this->patch('/admin/dashboard/widgets', ['enabled' => []])
            ->assertRedirect(route('login'));
    }

    public function test_widget_update_is_admin_route_only(): void
    {
        // Tidak ada route publik serupa — memastikan nama route terdaftar di grup admin
        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk();

        $this->assertTrue(route('admin.dashboard.widgets.update') !== '');
    }
}
