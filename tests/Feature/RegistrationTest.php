<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Registrasi publik + validasi admin/operator + alur login per status.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    // ── Halaman & pembuatan akun ────────────────────────────────────────────

    public function test_register_page_renders_for_guest(): void
    {
        $this->get('/registrasi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Register'));
    }

    public function test_register_page_redirects_when_logged_in(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/registrasi')->assertRedirect(route('dashboard'));
    }

    public function test_register_creates_pending_user_and_logs_in(): void
    {
        $this->post('/registrasi', [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.go.id',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'budi@example.go.id')->firstOrFail();
        $this->assertSame(User::STATUS_PENDING, $user->status);
        $this->assertSame(User::ROLE_USER, $user->role);
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', ['email' => 'budi@example.go.id']);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dupe@example.go.id']);

        $this->from('/registrasi')->post('/registrasi', [
            'name' => 'Duplikat',
            'email' => 'dupe@example.go.id',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors('email');
    }

    public function test_register_rejects_short_password(): void
    {
        $this->from('/registrasi')->post('/registrasi', [
            'name' => 'Pendek',
            'email' => 'pendek@example.go.id',
            'password' => 'abc',
            'password_confirmation' => 'abc',
        ])->assertSessionHasErrors('password');
    }

    // ── Login per status registrasi ─────────────────────────────────────────

    public function test_pending_user_can_login(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_PENDING]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_rejected_user_cannot_login(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_REJECTED]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_operator_lands_on_registration_review_page(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->post('/login', [
            'email' => $operator->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.registrations.index'));
    }

    // ── Validasi admin/operator ─────────────────────────────────────────────

    public function test_operator_can_approve_registration(): void
    {
        $this->seed(DatabaseSeeder::class);
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        $pending = User::factory()->create(['status' => User::STATUS_PENDING]);

        $this->actingAs($operator)
            ->patch('/admin/registrasi/'.$pending->id.'/status', ['status' => 'approved'])
            ->assertStatus(302)
            ->assertSessionHas('success');

        $this->assertSame(User::STATUS_APPROVED, $pending->fresh()->status);
    }

    public function test_admin_can_reject_registration(): void
    {
        $admin = User::factory()->admin()->create();
        $pending = User::factory()->create(['status' => User::STATUS_PENDING]);

        $this->actingAs($admin)
            ->patch('/admin/registrasi/'.$pending->id.'/status', ['status' => 'rejected'])
            ->assertStatus(302)
            ->assertSessionHas('success');

        $this->assertSame(User::STATUS_REJECTED, $pending->fresh()->status);
    }

    public function test_invalid_status_value_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $pending = User::factory()->create(['status' => User::STATUS_PENDING]);

        $this->actingAs($admin)
            ->patchJson('/admin/registrasi/'.$pending->id.'/status', ['status' => 'ngawur'])
            ->assertStatus(422);

        $this->assertSame(User::STATUS_PENDING, $pending->fresh()->status);
    }

    public function test_reviewer_account_cannot_be_validated_via_page(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch('/admin/registrasi/'.$other->id.'/status', ['status' => 'rejected'])
            ->assertSessionHas('error');

        $this->assertSame(User::STATUS_APPROVED, $other->fresh()->status);
    }

    public function test_regular_user_cannot_access_registration_review(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/registrasi')
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_registration_review(): void
    {
        $this->get('/admin/registrasi')->assertRedirect(route('login'));
    }

    // ── Pembuatan user oleh admin (role operator) ───────────────────────────

    public function test_admin_can_create_operator_user(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/pengaturan/user', [
                'name' => 'Operator Satu',
                'email' => 'op1@example.go.id',
                'password' => 'rahasia123',
                'password_confirmation' => 'rahasia123',
                'role' => User::ROLE_OPERATOR,
            ])
            ->assertStatus(201);

        $user = User::where('email', 'op1@example.go.id')->firstOrFail();
        $this->assertSame(User::ROLE_OPERATOR, $user->role);
        $this->assertSame(User::STATUS_APPROVED, $user->status);
    }

    public function test_admin_can_update_user_role_to_operator(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['role' => User::ROLE_USER]);

        $this->actingAs($admin)
            ->putJson('/admin/pengaturan/user/'.$user->id, ['role' => User::ROLE_OPERATOR])
            ->assertOk();

        $this->assertSame(User::ROLE_OPERATOR, $user->fresh()->role);
    }
}
