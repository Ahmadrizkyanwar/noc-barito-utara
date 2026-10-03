<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterfaceMetric extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'device_id',
        'checked_at',
        'if_name',
        'oper_status',
        'speed',
        'rx_bps',
        'tx_bps',
        'rx_bytes',
        'tx_bytes',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'speed' => 'integer',
            'rx_bps' => 'float',
            'tx_bps' => 'float',
            'rx_bytes' => 'integer',
            'tx_bytes' => 'integer',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
