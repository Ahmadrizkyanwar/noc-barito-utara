<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailIndo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Verifikasi email wajib sebelum login:
 * registrasi → link signed (60 menit) → verified → tetap butuh validasi admin.
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function unverifiedUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'email_verified_at' => null,
        ], $overrides));
    }

    protected function verifyUrl(User $user, ?string $hash = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), [
            'id' => $user->id,
            'hash' => $hash ?? sha1($user->email),
        ]);
    }

    public function test_verification_notice_page_renders_for_guest(): void
    {
        $this->get('/email/verify')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('VerifyEmail'));
    }

    public function test_resend_sends_new_verification_link(): void
    {
        Notification::fake();
        $user = $this->unverifiedUser();

        $this->from('/email/verify')
            ->post('/email/resend', ['email' => $user->email])
            ->assertRedirect('/email/verify')
            ->assertSessionHas('success');

        Notification::assertSentTo($user, VerifyEmailIndo::class);
        $this->assertGuest();
    }

    public function test_resend_with_unknown_email_shows_generic_success(): void
    {
        Notification::fake();

        $this->post('/email/resend', ['email' => 'tidak-ada@example.com'])
            ->assertSessionHas('success');

        Notification::assertNothingSent(); // tidak membocorkan email terdaftar
    }

    public function test_signed_link_marks_email_verified(): void
    {
        $user = $this->unverifiedUser(['status' => User::STATUS_PENDING]);

        $this->get($this->verifyUrl($user))
            ->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_link_with_wrong_email_hash_is_rejected(): void
    {
        $user = $this->unverifiedUser();

        $this->get($this->verifyUrl($user, sha1('salah@example.com')))
            ->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_unsigned_verification_url_is_rejected(): void
    {
        $user = $this->unverifiedUser();

        $this->get('/email/verify/'.$user->id.'/'.sha1($user->email))
            ->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    /**
     * E2E: daftar → login ditolak → verifikasi → BISA login tapi Request VPS
     * terkunci → admin setujui → Request VPS terbuka.
     */
    public function test_full_flow_register_verify_approve_then_vps_unlocks(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        // 1. Registrasi
        $this->post('/registrasi', [
            'name' => 'Warga Baru',
            'email' => 'warga@example.go.id',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertRedirect(route('verification.notice'));

        // 2. Belum verifikasi → login ditolak
        $this->post('/login', ['email' => 'warga@example.go.id', 'password' => 'rahasia123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        // 3. Ambil URL verifikasi dari email yang terkirim
        $user = User::where('email', 'warga@example.go.id')->firstOrFail();
        $url = null;
        Notification::assertSentTo($user, VerifyEmailIndo::class, function ($n) use (&$url, $user) {
            $url = $n->toMail($user)->actionUrl;

            return true;
        });
        $this->assertNotNull($url);

        $this->get($url)->assertRedirect(route('login'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        // 4. Verified + PENDING → BISA login…
        $this->post('/login', ['email' => 'warga@example.go.id', 'password' => 'rahasia123'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user->fresh());

        // …tapi Request VPS masih terkunci (form + guard server-side)
        $this->get('/vps')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canRequest', false));

        $this->post('/vps', [
            'name' => 'Warga Baru',
            'nip' => '198701012010011001',
            'jabatan' => 'Staf',
            'instansi' => 'Dinas Contoh',
            'cores' => 2,
            'ram_gb' => 4,
            'public_ips' => 1,
            'ports' => ['22', '443'],
            'purpose' => 'Portal desa.',
        ])->assertSessionHas('error');
        $this->assertDatabaseCount('vps_requests', 0);

        // 5. Admin setujui
        $this->app['auth']->forgetGuards();
        $this->actingAs($admin)
            ->patch('/admin/registrasi/'.$user->id.'/status', ['status' => 'approved'])
            ->assertStatus(302);
        $this->app['auth']->forgetGuards();

        // 6. Setelah disetujui → Request VPS terbuka
        $this->actingAs($user->fresh())
            ->get('/vps')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canRequest', true));
    }
}
