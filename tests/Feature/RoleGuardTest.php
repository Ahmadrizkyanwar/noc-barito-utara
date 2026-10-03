<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_role_cannot_access_admin_pages(): void
    {
        $user = User::factory()->create(); // role default: user

        $this->actingAs($user)->get('/admin')->assertForbidden();
        $this->actingAs($user)->get('/admin/jaringan')->assertForbidden();
        $this->actingAs($user)->get('/admin/layanan')->assertForbidden();
        $this->actingAs($user)->get('/admin/pengaturan/user')->assertForbidden();
        $this->actingAs($user)->get('/admin/pengaturan/webhook')->assertForbidden();
    }

    public function test_admin_can_access_admin_pages(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/admin/jaringan')->assertOk();
        $this->actingAs($admin)->get('/admin/layanan')->assertOk();
        $this->actingAs($admin)->get('/admin/pengaturan/user')->assertOk();
        $this->actingAs($admin)->get('/admin/pengaturan/webhook')->assertOk();
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->deleteJson('/admin/pengaturan/user/'.$admin->id)
            ->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_cannot_demote_own_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->putJson('/admin/pengaturan/user/'.$admin->id, ['role' => 'user'])
            ->assertStatus(422);

        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_landing_is_public(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->component('Landing'));
    }

    public function test_status_endpoint_is_public_json(): void
    {
        Device::create(['name' => 'R1', 'host' => '192.0.2.1', 'status' => 'up']);

        $this->getJson('/status')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('up', 1)
            ->assertJsonPath('down', 0);
    }

    public function test_status_endpoint_hides_device_identity(): void
    {
        Device::create(['name' => 'Rahasia', 'host' => '10.0.0.1', 'status' => 'down']);

        $response = $this->getJson('/status');
        $response->assertOk();

        $this->assertStringNotContainsString('Rahasia', $response->getContent());
        $this->assertStringNotContainsString('10.0.0.1', $response->getContent());
    }

    public function test_unknown_route_renders_404(): void
    {
        $this->get('/halaman-tidak-ada')->assertNotFound();
    }
}
