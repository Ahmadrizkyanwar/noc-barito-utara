<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceMetric extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'device_id',
        'checked_at',
        'status',
        'icmp_ok',
        'icmp_rtt_ms',
        'icmp_error',
        'snmp_ok',
        'cpu',
        'uptime_sec',
        'board_name',
        'rx_bps',
        'tx_bps',
        'snmp_error',
        'routeros_ok',
        'routeros_cpu',
        'routeros_error',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'icmp_ok' => 'boolean',
            'snmp_ok' => 'boolean',
            'routeros_ok' => 'boolean',
            'icmp_rtt_ms' => 'float',
            'rx_bps' => 'float',
            'tx_bps' => 'float',
            'cpu' => 'integer',
            'routeros_cpu' => 'integer',
            'uptime_sec' => 'integer',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
