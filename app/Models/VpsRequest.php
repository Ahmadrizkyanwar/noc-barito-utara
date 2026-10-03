<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Request VPS dari user terdaftar — direview admin/operator.
 */
class VpsRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** @var list<string> */
    protected $fillable = [
        'code',
        'user_id',
        'name',
        'nip',
        'jabatan',
        'instansi',
        'cores',
        'ram_gb',
        'public_ips',
        'ports',
        'purpose',
        'status',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
        'credential_file',
        'credential_uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'ports' => 'array',
            'reviewed_at' => 'datetime',
            'credential_uploaded_at' => 'datetime',
            'cores' => 'integer',
            'ram_gb' => 'integer',
            'public_ips' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function hasCredentials(): bool
    {
        return $this->credential_file !== null;
    }
}
