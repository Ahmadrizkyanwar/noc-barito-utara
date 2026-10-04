<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pendaftaran Domain / Hosting dari user terdaftar — direview admin/operator.
 *
 * Dua tipe dalam satu tabel: `domain` dan `hosting` (lihat config
 * `noc.service_registrations`), mengikuti pola VpsRequest.
 */
class ServiceRegistration extends Model
{
    public const TYPE_DOMAIN = 'domain';

    public const TYPE_HOSTING = 'hosting';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'type',
        'code',
        'name',
        'nip',
        'jabatan',
        'instansi',
        'domain_name',
        'hosting_package',
        'duration',
        'purpose',
        'supporting_document',
        'supporting_document_uploaded_at',
        'status',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'duration' => 'integer',
            'supporting_document_uploaded_at' => 'datetime',
            'reviewed_at' => 'datetime',
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

    public function hasSupportingDocument(): bool
    {
        return $this->supporting_document !== null;
    }

    /**
     * Konfigurasi tipe dari config('noc.service_registrations').
     *
     * @return array<string, mixed>
     */
    public function typeConfig(): array
    {
        return (array) config('noc.service_registrations.'.$this->type, []);
    }

    /**
     * Label tipe ("Pendaftaran Domain" / "Pendaftaran Hosting").
     */
    public function typeLabel(): string
    {
        return (string) ($this->typeConfig()['label'] ?? $this->type);
    }

    /**
     * Ringkasan spesifikasi untuk tampilan admin / pesan Telegram.
     * Contoh: "diskominfosandi.go.id · 3 tahun" / "Shared Hosting 1 GB · 12 bulan".
     */
    public function specSummary(): string
    {
        $parts = [];

        if ($this->domain_name !== null && $this->domain_name !== '') {
            $parts[] = $this->domain_name;
        }

        if ($this->hosting_package !== null && $this->hosting_package !== '') {
            $pkg = (array) config('noc.service_registrations.hosting.packages', []);
            $parts[] = $pkg[$this->hosting_package] ?? $this->hosting_package;
        }

        if ($this->duration !== null) {
            $durations = (array) ($this->typeConfig()['durations'] ?? []);
            $parts[] = $durations[$this->duration] ?? $this->duration.($this->type === self::TYPE_DOMAIN ? ' tahun' : ' bulan');
        }

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }
}
