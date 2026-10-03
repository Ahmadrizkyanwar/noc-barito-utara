<?php

namespace App\Models;

use App\Notifications\VerifyEmailIndo;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, MustVerifyEmail, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_OPERATOR = 'operator';

    public const ROLE_USER = 'user';

    /** Registrasi menunggu validasi admin/operator. */
    public const STATUS_PENDING = 'pending';

    /** Akun aktif — semua fitur terbuka. */
    public const STATUS_APPROVED = 'approved';

    /** Registrasi ditolak — login diblokir. */
    public const STATUS_REJECTED = 'rejected';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'email_verified_at',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'string',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isOperator(): bool
    {
        return $this->role === self::ROLE_OPERATOR;
    }

    /** Boleh memvalidasi registrasi & review request VPS. */
    public function canReview(): bool
    {
        return $this->isAdmin() || $this->isOperator();
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /** Fitur Request VPS hanya untuk akun yang sudah disetujui. */
    public function canRequestVps(): bool
    {
        return $this->isApproved();
    }

    /**
     * Kirim email verifikasi (templat Indonesia, URL signed 60 menit).
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailIndo);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'user_id');
    }

    public function vpsRequests(): HasMany
    {
        return $this->hasMany(VpsRequest::class);
    }
}
