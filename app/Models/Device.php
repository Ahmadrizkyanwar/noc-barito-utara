<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Device extends Model
{
    protected $fillable = [
        'name',
        'host',
        'type',
        'location',
        'enabled',
        'use_icmp',
        'use_snmp',
        'use_routeros',
        'snmp_version',
        'snmp_community',
        'snmp_port',
        'routeros_port',
        'routeros_user',
        'routeros_password',
        'routeros_timeout',
        'status',
        'last_checked_at',
        'last_rtt_ms',
        'last_cpu',
        'last_uptime_sec',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'use_icmp' => 'boolean',
            'use_snmp' => 'boolean',
            'use_routeros' => 'boolean',
            'last_checked_at' => 'datetime',
            'last_rtt_ms' => 'float',
            'last_cpu' => 'integer',
            'last_uptime_sec' => 'integer',
            'snmp_port' => 'integer',
            'routeros_port' => 'integer',
            'routeros_timeout' => 'integer',
        ];
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(DeviceMetric::class);
    }

    public function interfaceMetrics(): HasMany
    {
        return $this->hasMany(InterfaceMetric::class);
    }

    public function latestMetric(): HasOne
    {
        return $this->hasOne(DeviceMetric::class)->latestOfMany();
    }

    public function isUp(): bool
    {
        return $this->status === 'up';
    }
}
