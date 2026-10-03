<?php

namespace App\Services\Network;

use App\Models\Device;
use App\Models\DeviceMetric;
use App\Models\InterfaceMetric;
use App\Services\Telegram\Notifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Poller: sekali jalan = semua perangkat enabled → 3 probe → tulis metrics →
 * update status terakhir → notifikasi TELEGRAM saat TRANSISI up↔down.
 *
 * Trafik:
 *   - Per interface → tabel `interface_metrics` (delta counter → bit/s),
 *     sumber: SNMP ifTable, fallback RouterOS /interface/print.
 *   - Aggregate device_metrics.rx_bps/tx_bps = SUM delta interface; bila
 *     interface tidak tersedia → delta counter aggregate (jalur lama).
 *   - Counter antar-poll: cache `noc.iftraffic.{id}`; bila cache hilang
 *     (restart) → fallback counter terakhir dari DB.
 */
class MonitorPoller
{
    public function __construct(
        protected PingProbe $ping,
        protected SnmpProbe $snmp,
        protected RouterOsProbe $routeros,
        protected Notifier $notifier,
    ) {}

    /**
     * Poll semua perangkat enabled. Gagal satu perangkat TIDAK menghentikan lainnya.
     *
     * @return array{polled: int, up: int, down: int}
     */
    public function pollAll(): array
    {
        $devices = Device::where('enabled', true)->get();
        $stats = ['polled' => 0, 'up' => 0, 'down' => 0];

        foreach ($devices as $device) {
            try {
                $status = $this->pollDevice($device);
                $stats['polled']++;
                if ($status === 'up') {
                    $stats['up']++;
                } elseif ($status === 'down') {
                    $stats['down']++;
                }
            } catch (\Throwable $e) {
                Log::error('poll gagal', ['device' => $device->id, 'error' => $e->getMessage()]);
            }
        }

        return $stats;
    }

    /**
     * Poll SATU perangkat → simpan metric → update status → notify bila transisi.
     *
     * @return string status akhir: up|down|unknown
     */
    public function pollDevice(Device $device): string
    {
        $now = now();
        $row = [
            'icmp_ok' => null,
            'icmp_rtt_ms' => null,
            'icmp_error' => null,
            'snmp_ok' => null,
            'cpu' => null,
            'uptime_sec' => null,
            'board_name' => null,
            'rx_bps' => null,
            'tx_bps' => null,
            'snmp_error' => null,
            'routeros_ok' => null,
            'routeros_cpu' => null,
            'routeros_error' => null,
        ];

        $anyProbe = false;
        $anyOk = false;

        /** @var list<array{name: string, oper_status: string|null, speed: int|null, rx_bytes: int, tx_bytes: int}>|null */
        $interfaces = null;
        /** @var array{rx: int, tx: int}|null counter aggregate SNMP (fallback) */
        $snmpCounters = null;

        // ── ICMP (cek SEMUA IP — perangkat UP bila satu saja merespons) ──
        if ($device->use_icmp) {
            $anyProbe = true;

            $icmpOk = false;
            $rtt = null;
            $err = null;

            foreach ($device->allHosts() as $h) {
                $ping = $this->ping->ping($h);

                if ($ping['ok']) {
                    $icmpOk = true;
                    $rtt = $ping['rtt_ms'];
                    $err = null;
                    break; // cukup satu IP merespons (IP utama dicek lebih dulu)
                }

                $err ??= $ping['error']; // pertahankan pesan error IP utama
            }

            $row['icmp_ok'] = $icmpOk;
            $row['icmp_rtt_ms'] = $rtt;
            $row['icmp_error'] = $icmpOk ? null : $err;
            $anyOk = $anyOk || $icmpOk;
        }

        // ── SNMP ──
        if ($device->use_snmp) {
            $anyProbe = true;
            $snmp = $this->snmp->collect($device);
            $row['snmp_ok'] = $snmp['ok'];
            $row['cpu'] = $snmp['cpu'];
            $row['uptime_sec'] = $snmp['uptime_sec'];
            $row['board_name'] = $snmp['board_name'];
            $row['snmp_error'] = $snmp['error'];

            if ($snmp['ok']) {
                $interfaces = $snmp['interfaces'] ?? null;
                if ($snmp['rx_bytes'] !== null && $snmp['tx_bytes'] !== null) {
                    $snmpCounters = ['rx' => (int) $snmp['rx_bytes'], 'tx' => (int) $snmp['tx_bytes']];
                }
            }

            $anyOk = $anyOk || $snmp['ok'];
        }

        // ── RouterOS API ──
        if ($device->use_routeros) {
            $anyProbe = true;
            $ros = $this->routeros->collect($device);
            $row['routeros_ok'] = $ros['ok'];
            $row['routeros_cpu'] = $ros['cpu'];
            $row['routeros_error'] = $ros['error'];
            // CPU/uptime dari RouterOS dipakai bila SNMP tidak ada
            if ($ros['ok']) {
                $row['cpu'] ??= $ros['cpu'];
                $row['uptime_sec'] ??= $ros['uptime_sec'];
                $row['board_name'] ??= $ros['board_name'];
                $interfaces ??= $ros['interfaces'] ?? null;
            }
            $anyOk = $anyOk || $ros['ok'];
        }

        // ── Trafik: per-interface (utama) atau aggregate (fallback) ──
        $aggregate = $this->storeInterfaces($device, $interfaces, $now);

        if ($aggregate !== null) {
            $row['rx_bps'] = $aggregate['rx_bps'];
            $row['tx_bps'] = $aggregate['tx_bps'];
        } elseif ($snmpCounters !== null) {
            $delta = $this->snmp->deltaBps(
                $this->previousCounter($device),
                $snmpCounters['rx'],
                $snmpCounters['tx'],
                $this->nowFloat($now)
            );
            $row['rx_bps'] = $delta['rx_bps'];
            $row['tx_bps'] = $delta['tx_bps'];
            $this->rememberCounter($device, $snmpCounters['rx'], $snmpCounters['tx']);
        }

        $status = ! $anyProbe ? 'unknown' : ($anyOk ? 'up' : 'down');

        DeviceMetric::create([
            'device_id' => $device->id,
            'checked_at' => $now,
            'status' => $status,
            ...$row,
        ]);

        $previousStatus = $device->status;

        $device->update([
            'status' => $status,
            'last_checked_at' => $now,
            'last_rtt_ms' => $row['icmp_rtt_ms'],
            'last_cpu' => $row['cpu'],
            'last_uptime_sec' => $row['uptime_sec'],
        ]);

        // ── Transisi status → notifikasi Telegram (webhook `jaringan`) ──
        // Notifikasi HANYA transisi nyata up↔down. Poll pertama (unknown → up/down)
        // TANPA notifikasi (pola 'cek pertama tanpa notifikasi').
        if (in_array($previousStatus, ['up', 'down'], true) && $previousStatus !== $status && $status !== 'unknown') {
            $this->notifier->sendStatusChange($device, $previousStatus, $status);
        }

        return $status;
    }

    // ── Trafik per interface ───────────────────────────────────────────────

    /**
     * Simpan baris interface_metrics (delta counter → bit/s) + hitung aggregate.
     *
     * @param  list<array{name: string, oper_status: string|null, speed: int|null, rx_bytes: int, tx_bytes: int}>|null  $raw
     * @return array{rx_bps: float, tx_bps: float}|null null = belum ada delta (poll pertama / tanpa data)
     */
    protected function storeInterfaces(Device $device, ?array $raw, Carbon $now): ?array
    {
        if ($raw === null || $raw === []) {
            return null;
        }

        $at = $this->nowFloat($now);
        $cacheKey = 'noc.iftraffic.'.$device->id;
        $prev = cache()->get($cacheKey);
        if (! is_array($prev)) {
            $prev = $this->interfaceCountersFromDb($device); // fallback: cache hilang (restart)
        }

        $rows = [];
        $next = [];
        $sumRx = 0.0;
        $sumTx = 0.0;
        $any = false;

        foreach ($raw as $if) {
            $before = null;
            if (isset($prev[$if['name']]) && is_numeric($prev[$if['name']]['rx'])) {
                $before = [
                    'rx' => (int) $prev[$if['name']]['rx'],
                    'tx' => (int) ($prev[$if['name']]['tx'] ?? 0),
                    'at' => (float) ($prev[$if['name']]['at'] ?? 0),
                ];
            }

            $delta = $this->snmp->deltaBps($before, (int) $if['rx_bytes'], (int) $if['tx_bytes'], $at);

            $rows[] = [
                'device_id' => $device->id,
                'checked_at' => $now,
                'if_name' => $if['name'],
                'oper_status' => $if['oper_status'],
                'speed' => $if['speed'],
                'rx_bps' => $delta['rx_bps'],
                'tx_bps' => $delta['tx_bps'],
                'rx_bytes' => $if['rx_bytes'],
                'tx_bytes' => $if['tx_bytes'],
            ];

            if ($delta['rx_bps'] !== null) {
                $sumRx += $delta['rx_bps'];
                $any = true;
            }
            if ($delta['tx_bps'] !== null) {
                $sumTx += $delta['tx_bps'];
            }

            $next[$if['name']] = [
                'rx' => (int) $if['rx_bytes'],
                'tx' => (int) $if['tx_bytes'],
                'at' => $at,
            ];
        }

        cache()->put($cacheKey, $next, now()->addHours(2));
        InterfaceMetric::insert($rows);

        return $any
            ? ['rx_bps' => round($sumRx, 2), 'tx_bps' => round($sumTx, 2)]
            : null;
    }

    /**
     * Counter terakhir per interface dari DB (dipakai bila cache kosong).
     *
     * @return array<string, array{rx: int, tx: int, at: float}>
     */
    protected function interfaceCountersFromDb(Device $device): array
    {
        $latest = $device->interfaceMetrics()
            ->orderByDesc('checked_at')
            ->limit(500)
            ->get(['if_name', 'rx_bytes', 'tx_bytes', 'checked_at']);

        $map = [];
        foreach ($latest as $m) {
            if (isset($map[$m->if_name]) || $m->rx_bytes === null) {
                continue;
            }
            $map[$m->if_name] = [
                'rx' => (int) $m->rx_bytes,
                'tx' => (int) ($m->tx_bytes ?? 0),
                'at' => (float) $m->checked_at->timestamp + $m->checked_at->micro / 1e6,
            ];
        }

        return $map;
    }

    // ── Counter trafik aggregate antar-poll (fallback) ─────────────────────

    /**
     * @return array{rx: int, tx: int, at: float}|null
     */
    protected function previousCounter(Device $device): ?array
    {
        $raw = cache()->get($this->counterKey($device));

        return is_array($raw) ? $raw : null;
    }

    protected function rememberCounter(Device $device, int $rx, int $tx): void
    {
        $now = now();

        cache()->put($this->counterKey($device), [
            'rx' => $rx,
            'tx' => $tx,
            'at' => $this->nowFloat($now),
        ], now()->addHours(2));
    }

    protected function counterKey(Device $device): string
    {
        return 'noc.traffic.'.$device->id;
    }

    protected function nowFloat(Carbon $t): float
    {
        return (float) $t->timestamp + $t->micro / 1e6;
    }
}
