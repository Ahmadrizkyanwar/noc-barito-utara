<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_can_be_rendered(): void
    {
        $this->get('/login')->assertOk()->assertInertia(fn ($page) => $page->component('Login'));
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->admin()->create(['email' => 'admin@example.test']);

        $response = $this->post('/login', [
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        User::factory()->create(['email' => 'user@example.test']);

        $response = $this->from('/login')->post('/login', [
            'email' => 'user@example.test',
            'password' => 'salah-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_non_admin_is_redirected_to_user_dashboard(): void
    {
        $user = User::factory()->create(['email' => 'viewer@example.test']);

        $this->actingAs($user)
            ->post('/login', ['email' => 'viewer@example.test', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_guest_cannot_access_admin(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }
}
