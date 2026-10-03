<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\Network\MonitorPoller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DeviceController extends Controller
{
    /**
     * Daftar perangkat + filter status.
     */
    public function index(Request $request): Response
    {
        $q = Device::query()->orderBy('name');

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('host', 'like', $term));
        }

        return Inertia::render('Admin/Jaringan', [
            'devices' => $q->get(),
            'filters' => $request->only(['status', 'q']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validate($request);

        $device = Device::create($data);

        return redirect()->route('admin.devices.show', $device)
            ->with('success', 'Perangkat "'.$device->name.'" berhasil ditambahkan.');
    }

    public function show(Device $device): Response
    {
        return Inertia::render('Admin/Perangkat', [
            'device' => $device,
            'metrics' => $device->metrics()
                ->latest('checked_at')
                ->take(288) // ~24 jam pada interval 30 dtk
                ->get()
                ->reverse()
                ->values(),
        ]);
    }

    public function update(Request $request, Device $device)
    {
        $data = $this->validate($request, $device);

        $device->update($data);

        return back()->with('success', 'Perangkat "'.$device->name.'" diperbarui.');
    }

    public function destroy(Device $device): JsonResponse
    {
        $name = $device->name;
        $device->delete();

        return response()->json(['ok' => true, 'message' => 'Perangkat "'.$name.'" dihapus.']);
    }

    /**
     * Cek manual SATU perangkat (tanpa menunggu jadwal).
     */
    public function check(Device $device, MonitorPoller $poller): JsonResponse
    {
        $status = $poller->pollDevice($device->fresh());

        return response()->json([
            'ok' => true,
            'status' => $status,
            'device' => $device->fresh(),
        ]);
    }

    /**
     * Data grafik per-perangkat (rentang bebas, default 24 jam).
     */
    public function metrics(Request $request, Device $device): JsonResponse
    {
        $hours = min(168, max(1, (int) $request->input('hours', 24)));

        $rows = $device->metrics()
            ->where('checked_at', '>=', now()->subHours($hours))
            ->orderBy('checked_at')
            ->get(['checked_at', 'status', 'icmp_rtt_ms', 'cpu', 'rx_bps', 'tx_bps', 'snmp_ok', 'icmp_ok', 'routeros_ok']);

        return response()->json([
            'labels' => $rows->pluck('checked_at')->map(fn ($t) => $t->format('d/m H:i')),
            'rtt' => $rows->pluck('icmp_rtt_ms'),
            'cpu' => $rows->pluck('cpu'),
            'rx' => $rows->pluck('rx_bps'),
            'tx' => $rows->pluck('tx_bps'),
            'status' => $rows->pluck('status'),
        ]);
    }

    // ── Infografik trafik per interface ────────────────────────────────────

    /**
     * Halaman infografik trafik (Inertia).
     */
    public function traffic(Request $request, Device $device): Response
    {
        $hours = min(168, max(1, (int) $request->input('hours', 1)));

        return Inertia::render('Admin/Trafik', [
            'device' => $device->only(['id', 'name', 'host', 'type', 'status', 'use_snmp', 'use_routeros']),
            'initial' => $this->trafficPayload($device, $hours),
            'hours' => $hours,
        ]);
    }

    /**
     * JSON data infografik — dipanggil halaman trafik (auto-refresh 15 dtk).
     */
    public function interfaces(Request $request, Device $device): JsonResponse
    {
        $hours = min(168, max(1, (int) $request->input('hours', 1)));

        return response()->json($this->trafficPayload($device, $hours));
    }

    /**
     * Susun payload infografik:
     *   - latest   : sampel terakhir tiap interface (+ util% & share)
     *   - total    : agregat RX/TX saat ini + puncak pada rentang
     *   - history  : deret ber-bucket per interface top (grafik area)
     *   - spark    : 1 jam terakhir per interface (sparkline kartu)
     *
     * @return array<string, mixed>
     */
    protected function trafficPayload(Device $device, int $hours): array
    {
        // ── 1. Sampel terakhir per interface ──
        $lastRows = $device->interfaceMetrics()
            ->orderByDesc('checked_at')
            ->limit(200)
            ->get(['if_name', 'oper_status', 'speed', 'rx_bps', 'tx_bps', 'checked_at']);

        $latest = [];
        foreach ($lastRows as $m) {
            if (isset($latest[$m->if_name])) {
                continue;
            }
            $latest[$m->if_name] = [
                'name' => $m->if_name,
                'oper_status' => $m->oper_status,
                'speed' => $m->speed,
                'rx_bps' => $m->rx_bps,
                'tx_bps' => $m->tx_bps,
                'updated_at' => $m->checked_at->toIso8601String(),
            ];
        }

        $totalRx = (float) array_sum(array_column($latest, 'rx_bps'));
        $totalTx = (float) array_sum(array_column($latest, 'tx_bps'));
        $totalAll = $totalRx + $totalTx;

        foreach ($latest as $k => $if) {
            $cur = (float) $if['rx_bps'] + (float) $if['tx_bps'];
            $latest[$k]['total_bps'] = $cur;
            $latest[$k]['share'] = $totalAll > 0 ? round($cur / $totalAll * 100, 1) : 0.0;
            $speed = $if['speed'];
            $latest[$k]['util'] = (is_numeric($speed) && $speed > 0)
                ? round(max((float) $if['rx_bps'], (float) $if['tx_bps']) / $speed * 100, 1)
                : null;
        }

        $interfaces = array_values($latest);
        usort($interfaces, fn ($a, $b) => $b['total_bps'] <=> $a['total_bps']);

        // ── 2. Deret waktu ber-bucket ──
        $bucket = match (true) {
            $hours <= 1 => 60,
            $hours <= 6 => 300,
            $hours <= 24 => 900,
            default => 7200,
        };

        $epoch = $this->epochExpression();
        $rows = DB::select(
            "SELECT if_name,
                    FLOOR(($epoch) / ?) * ? AS t,
                    AVG(rx_bps) AS rx, AVG(tx_bps) AS tx,
                    MAX(rx_bps) AS rx_peak, MAX(tx_bps) AS tx_peak
             FROM interface_metrics
             WHERE device_id = ? AND checked_at >= ?
             GROUP BY if_name, t
             ORDER BY t",
            [$bucket, $bucket, $device->id, now()->subHours($hours)->toDateTimeString()]
        );

        // Top 6 interface (total traffic rentang); sisanya digabung "lainnya"
        $totals = [];
        foreach ($rows as $r) {
            $totals[$r->if_name] = ($totals[$r->if_name] ?? 0) + $r->rx + $r->tx;
        }
        arsort($totals);
        $topNames = array_slice(array_keys($totals), 0, 6);

        $byTime = [];
        foreach ($rows as $r) {
            $t = (int) $r->t;
            $bucketName = in_array($r->if_name, $topNames, true) ? $r->if_name : '__other';
            $byTime[$t][$bucketName] ??= ['rx' => 0.0, 'tx' => 0.0];
            $byTime[$t][$bucketName]['rx'] += (float) $r->rx;
            $byTime[$t][$bucketName]['tx'] += (float) $r->tx;
        }

        $timestamps = $byTime === [] ? [] : array_map('intval', array_keys($byTime));
        sort($timestamps);

        $seriesNames = $topNames;
        $hasOther = false;
        foreach ($timestamps as $t) {
            if (isset($byTime[$t]['__other'])) {
                $hasOther = true;
                break;
            }
        }
        if ($hasOther) {
            $seriesNames[] = '__other';
        }

        $labels = array_map(fn (int $t) => date('d/m H:i', $t), $timestamps);

        $series = [];
        foreach ($seriesNames as $name) {
            $series[] = [
                'name' => $name === '__other' ? 'Lainnya' : $name,
                'rx' => array_map(fn (int $t) => round($byTime[$t][$name]['rx'] ?? 0, 2), $timestamps),
                'tx' => array_map(fn (int $t) => round($byTime[$t][$name]['tx'] ?? 0, 2), $timestamps),
            ];
        }

        // ── 3. Puncak (peak) pada rentang ──
        $peakRx = $rows === [] ? null : (float) max(array_map(fn ($r) => (float) $r->rx_peak, $rows));
        $peakTx = $rows === [] ? null : (float) max(array_map(fn ($r) => (float) $r->tx_peak, $rows));

        // ── 4. Sparkline 1 jam (bucket 60 dtk) per interface ──
        $sparkRows = DB::select(
            "SELECT if_name,
                    FLOOR(($epoch) / 60) * 60 AS t,
                    AVG(rx_bps) + AVG(tx_bps) AS total
             FROM interface_metrics
             WHERE device_id = ? AND checked_at >= ?
             GROUP BY if_name, t
             ORDER BY t",
            [$device->id, now()->subHour()->toDateTimeString()]
        );

        $spark = [];
        foreach ($sparkRows as $r) {
            $spark[$r->if_name][(int) $r->t] = round((float) $r->total, 2);
        }

        $sparkStart = (int) floor((time() - 3600) / 60) * 60;
        $sparkOut = [];
        foreach (array_keys($latest) as $name) {
            $vals = [];
            for ($t = $sparkStart; $t <= time(); $t += 60) {
                $vals[] = $spark[$name][$t] ?? 0;
            }
            $sparkOut[$name] = $vals;
        }

        return [
            'interfaces' => $interfaces,
            'total' => [
                'rx_bps' => round($totalRx, 2),
                'tx_bps' => round($totalTx, 2),
                'peak_rx_bps' => $peakRx !== null ? round($peakRx, 2) : null,
                'peak_tx_bps' => $peakTx !== null ? round($peakTx, 2) : null,
            ],
            'history' => [
                'labels' => $labels,
                'series' => $series,
                'bucket_seconds' => $bucket,
            ],
            'spark' => $sparkOut,
            'window_hours' => $hours,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Ekspresi epoch (detik) per driver — MariaDB: UNIX_TIMESTAMP,
     * SQLite (tes): strftime. GROUP BY memakai ekspresi yang sama.
     */
    protected function epochExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%s', checked_at) AS INTEGER)"
            : 'UNIX_TIMESTAMP(checked_at)';
    }

    /**
     * @return array<string, mixed>
     */
    protected function validate(Request $request, ?Device $device = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            // IP utama diisi OTOMATIS dari daftar `hosts` (lihat normalisasi).
            'host' => ['required_without:hosts', 'nullable', 'string', 'max:255'],
            'hosts' => ['nullable', 'array', 'max:8'],
            'hosts.*' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(['router', 'switch', 'server', 'website', 'lainnya'])],
            'location' => ['nullable', 'string', 'max:150'],
            'enabled' => ['boolean'],

            'use_icmp' => ['boolean'],
            'use_snmp' => ['boolean'],
            'use_routeros' => ['boolean'],

            'snmp_version' => ['nullable', Rule::in(['1', '2c', '3'])],
            'snmp_community' => ['nullable', 'string', 'max:128'],
            'snmp_port' => ['nullable', 'integer', 'between:1,65535'],

            'routeros_port' => ['nullable', 'integer', 'between:1,65535'],
            'routeros_user' => ['nullable', 'string', 'max:64'],
            'routeros_password' => ['nullable', 'string', 'max:255'],
            'routeros_timeout' => ['nullable', 'integer', 'between:1,60'],
        ], [
            'use_snmp.required_if' => 'use_snmp',
        ]);

        // ConvertEmptyStringsToNull mengubah "" → null; kolom NOT NULL
        // (routeros_user/routeros_password) dipaksa kembali ke string kosong.
        foreach (['routeros_user', 'routeros_password'] as $key) {
            if (array_key_exists($key, $data) && $data[$key] === null) {
                $data[$key] = '';
            }
        }

        // ── Normalisasi daftar IP: unik, tanpa kosong, IP utama = pertama ──
        $hosts = array_values(array_unique(array_filter(array_map(
            static fn ($h) => trim((string) $h),
            $data['hosts'] ?? []
        ), static fn ($h) => $h !== '')));

        // Kompatibilitas mundur: payload lama hanya mengirim `host`.
        if ($hosts === [] && ($data['host'] ?? '') !== '') {
            $hosts = [trim((string) $data['host'])];
        }

        if ($hosts === []) {
            throw ValidationException::withMessages([
                'host' => 'Minimal satu IP/hostname wajib diisi.',
            ]);
        }

        $data['hosts'] = $hosts;
        $data['host'] = $hosts[0];

        return $data;
    }
}
