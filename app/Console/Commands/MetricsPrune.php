<?php

namespace App\Console\Commands;

use App\Models\DeviceMetric;
use App\Models\InterfaceMetric;
use Illuminate\Console\Command;

class MetricsPrune extends Command
{
    protected $signature = 'metrics:prune
        {--days=} : Retensi device_metrics (default METRICS_RETENTION_DAYS)
        {--interface-days=} : Retensi interface_metrics (default INTERFACE_RETENTION_DAYS)';

    protected $description = 'Hapus metrics lama: device_metrics + interface_metrics (retensi terpisah)';

    public function handle(): int
    {
        $days = max(1, (int) ($this->option('days') ?: config('noc.retention_days', 30)));
        $ifaceDays = max(1, (int) ($this->option('interface-days') ?: config('noc.interface_retention_days', 7)));

        $deleted = DeviceMetric::where('checked_at', '<', now()->subDays($days))->delete();
        $deletedIf = InterfaceMetric::where('checked_at', '<', now()->subDays($ifaceDays))->delete();

        $this->info("metrics:prune menghapus {$deleted} baris device_metrics (> {$days} hari), {$deletedIf} baris interface_metrics (> {$ifaceDays} hari)");

        return self::SUCCESS;
    }
}
