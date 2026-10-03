<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $fillable = [
        'code',
        'title',
        'category',
        'description',
        'location',
        'lat',
        'lng',
        'photo',
        'status',
        'priority',
        'user_id',
        'reporter_name',
        'reporter_contact',
        'assignee_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'resolved_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TicketActivity::class)->orderByDesc('created_at');
    }

    public function isOpen(): bool
    {
        return $this->status !== 'selesai';
    }

    public function hasCoords(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }

    /**
     * Link peta ke titik laporan (Google Maps `q=` — tanpa API key).
     */
    public function mapUrl(): ?string
    {
        if (! $this->hasCoords()) {
            return null;
        }

        return 'https://www.google.com/maps?q='.$this->lat.','.$this->lng;
    }
}
