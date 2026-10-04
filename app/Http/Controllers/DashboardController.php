<?php

namespace App\Http\Controllers;

use App\Models\DashboardWidget;
use App\Models\Device;
use App\Models\DeviceMetric;
use App\Models\InterfaceMetric;
use App\Models\Ticket;
use App\Models\VpsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard yang dipakai KEDUA peran — tampilannya dibedakan di halaman Vue
 * berdasarkan `auth.user.role` (admin → ringkasan penuh, user → lapor+tiket).
 */
class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return $this->admin($request);
        }

        return $this->user($request);
    }

    // ── Admin: ringkasan jaringan + tiket + grafik ─────────────────────────

    protected function admin(Request $request): Response
    {
        $devices = Device::where('enabled', true)
            ->orderBy('name')
            ->get(['id', 'name', 'host', 'type', 'status', 'last_checked_at', 'last_rtt_ms', 'last_cpu', 'last_uptime_sec']);

        $total = $devices->count();
        $up = $devices->where('status', 'up')->count();
        $down = $devices->where('status', 'down')->count();

        $openTickets = Ticket::whereIn('status', ['open', 'proses'])->count();
        $resolvedToday = Ticket::where('status', 'selesai')
            ->whereDate('resolved_at', today())
            ->count();

        $recentTickets = Ticket::with(['reporter:id,name', 'assignee:id,name'])
            ->latest()
            ->take(6)
            ->get(['id', 'code', 'title', 'category', 'status', 'priority', 'created_at']);

        // Grafik 24 jam: rata-rata CPU + RTT per jam
        $chart = $this->chart24h($devices->pluck('id'));

        // Pilihan widget (urut render) + opsi lengkap untuk modal edit
        $allWidgets = DashboardWidget::orderBy('sort_order')->get();
        $enabledIds = $allWidgets->where('enabled', true)->pluck('id')->values()->all();

        return Inertia::render('Dashboard', [
            'view' => 'admin',
            'stats' => [
                'devices_total' => $total,
                'devices_up' => $up,
                'devices_down' => $down,
                'devices_unknown' => max(0, $total - $up - $down),
                'open_tickets' => $openTickets,
                'resolved_today' => $resolvedToday,
                'uptime_pct' => $total > 0 ? round($up / $total * 100, 1) : null,
            ],
            'devices' => $devices,
            'recentTickets' => $recentTickets,
            'chart' => $chart,
            'widgets' => $enabledIds,
            'widgetOptions' => $allWidgets->map(fn ($w) => [
                'id' => $w->id,
                'label' => $w->label,
                'description' => $w->description,
                'enabled' => $w->enabled,
            ])->values(),
            'trend' => $this->trend24h(),
            'topDevices' => $this->topDevices(),
            'topInterfaces' => $this->topInterfaces(),
        ]);
    }

    /**
     * Simpan pilihan widget dashboard (admin) — baris TETAP, hanya `enabled`.
     */
    public function updateWidgets(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['array'],
            'enabled.*' => ['string', 'max:32', 'exists:dashboard_widgets,id'],
        ]);

        $selected = $data['enabled'];

        foreach (DashboardWidget::all() as $widget) {
            $widget->enabled = in_array($widget->id, $selected, true);
            $widget->save();
        }

        return back()->with('success', 'Tampilan dashboard diperbarui.');
    }

    /**
     * @param  Collection  $deviceIds
     * @return array{labels: list<string>, cpu: list<float|null>, rtt: list<float|null>}
     */
    protected function chart24h($deviceIds): array
    {
        $labels = [];
        $cpu = [];
        $rtt = [];

        $rows = DeviceMetric::whereIn('device_id', $deviceIds)
            ->where('checked_at', '>=', now()->subDay())
            ->get(['checked_at', 'cpu', 'icmp_rtt_ms']);

        $buckets = [];
        foreach ($rows as $row) {
            $key = $row->checked_at->format('H:00');
            $buckets[$key] ??= ['cpu' => [], 'rtt' => []];
            if ($row->cpu !== null) {
                $buckets[$key]['cpu'][] = $row->cpu;
            }
            if ($row->icmp_rtt_ms !== null) {
                $buckets[$key]['rtt'][] = $row->icmp_rtt_ms;
            }
        }

        for ($i = 23; $i >= 0; $i--) {
            $label = now()->subHours($i)->format('H:00');
            $labels[] = $label;
            $b = $buckets[$label] ?? null;
            $cpu[] = $b !== null && $b['cpu'] !== [] ? round(array_sum($b['cpu']) / count($b['cpu']), 1) : null;
            $rtt[] = $b !== null && $b['rtt'] !== [] ? round(array_sum($b['rtt']) / count($b['rtt']), 2) : null;
        }

        return ['labels' => $labels, 'cpu' => $cpu, 'rtt' => $rtt];
    }

    /**
     * Tren trafik agregat RX/TX 24 jam (per jam).
     *
     * Poll perangkat bersinkronisasi ±1 dtk → dijumlahkan per jendela 30 dtk
     * dulu, baru dirata-ratakan per jam.
     *
     * @return array{labels: list<string>, rx: list<float|null>, tx: list<float|null>}
     */
    protected function trend24h(): array
    {
        $epoch = $this->epochExpression();

        $rows = DB::select(
            "SELECT FLOOR(ts / 3600) * 3600 AS t, AVG(s) AS rx, AVG(s2) AS tx
             FROM (
                 SELECT FLOOR(($epoch) / 30) * 30 AS ts,
                        SUM(rx_bps) AS s, SUM(tx_bps) AS s2
                 FROM device_metrics
                 WHERE checked_at >= ?
                 GROUP BY ts
             ) x
             GROUP BY t
             ORDER BY t",
            [now()->subDay()->toDateTimeString()]
        );

        $byHour = [];
        foreach ($rows as $r) {
            $byHour[(int) $r->t] = [
                'rx' => $r->rx !== null ? round((float) $r->rx, 2) : null,
                'tx' => $r->tx !== null ? round((float) $r->tx, 2) : null,
            ];
        }

        $labels = [];
        $rx = [];
        $tx = [];
        for ($i = 23; $i >= 0; $i--) {
            $t = (int) floor(now()->subHours($i)->timestamp / 3600) * 3600;
            $labels[] = date('H:00', $t);
            $rx[] = $byHour[$t]['rx'] ?? null;
            $tx[] = $byHour[$t]['tx'] ?? null;
        }

        return ['labels' => $labels, 'rx' => $rx, 'tx' => $tx];
    }

    /**
     * Peringkat perangkat menurut trafik saat ini (metric terakhir).
     *
     * @return list<array{name: string, status: string, bps: float}>
     */
    protected function topDevices(): array
    {
        $rows = DB::select(
            'SELECT d.name AS name, d.status AS status,
                    COALESCE(m.rx_bps, 0) + COALESCE(m.tx_bps, 0) AS bps
             FROM devices d
             LEFT JOIN device_metrics m
                 ON m.id = (SELECT MAX(m2.id) FROM device_metrics m2 WHERE m2.device_id = d.id)
             WHERE d.enabled = 1
             ORDER BY bps DESC
             LIMIT 6'
        );

        return array_map(fn ($r) => [
            'name' => (string) $r->name,
            'status' => (string) $r->status,
            'bps' => round((float) $r->bps, 2),
        ], $rows);
    }

    /**
     * Top interface lintas perangkat (sampel terakhir per interface).
     *
     * @return list<array{name: string, device: string, rx_bps: float, tx_bps: float, bps: float}>
     */
    protected function topInterfaces(): array
    {
        $latest = InterfaceMetric::with('device:id,name')
            ->orderByDesc('checked_at')
            ->limit(800)
            ->get();

        $uniq = [];
        foreach ($latest as $row) {
            $key = $row->device_id.'|'.$row->if_name;
            if (isset($uniq[$key])) {
                continue;
            }
            $uniq[$key] = $row;
        }

        return collect($uniq)
            ->sortByDesc(fn ($r) => (float) ($r->rx_bps ?? 0) + (float) ($r->tx_bps ?? 0))
            ->take(6)
            ->map(fn ($r) => [
                'name' => $r->if_name,
                'device' => $r->device?->name ?? '-',
                'rx_bps' => round((float) ($r->rx_bps ?? 0), 2),
                'tx_bps' => round((float) ($r->tx_bps ?? 0), 2),
                'bps' => round((float) ($r->rx_bps ?? 0) + (float) ($r->tx_bps ?? 0), 2),
            ])
            ->values()
            ->all();
    }

    /**
     * Ekspresi epoch (detik) per driver — MariaDB: UNIX_TIMESTAMP,
     * SQLite (tes): strftime (sama dengan DeviceController).
     */
    protected function epochExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%s', checked_at) AS INTEGER)"
            : 'UNIX_TIMESTAMP(checked_at)';
    }

    // ── User: form lapor + tiket saya ──────────────────────────────────────

    protected function user(Request $request): Response
    {
        $tickets = Ticket::where('user_id', $request->user()->id)
            ->with('activities:id,ticket_id,action,old_value,new_value,created_at')
            ->latest()
            ->get();

        return Inertia::render('Dashboard', [
            'view' => 'user',
            'tickets' => $tickets,
            'categories' => config('noc.ticket_categories'),
            'statuses' => config('noc.ticket_statuses'),
            'vps' => [
                'status' => $request->user()->status,
                'total' => $request->user()->vpsRequests()->count(),
                'pending' => $request->user()->vpsRequests()->where('status', VpsRequest::STATUS_PENDING)->count(),
            ],
            'vpsStatuses' => config('noc.vps_statuses'),
            'vpsOperatingSystems' => config('noc.vps_operating_systems'),
            'vpsRequests' => $request->user()->vpsRequests()
                ->latest()
                ->take(5)
                ->get([
                    'id', 'code', 'name', 'instansi', 'cores', 'ram_gb', 'public_ips', 'os',
                    'purpose', 'status', 'admin_note', 'credential_file', 'created_at',
                ])
                ->map(function (VpsRequest $r) {
                    if ($r->credential_file !== null && ! Storage::disk('public')->exists($r->credential_file)) {
                        $r->credential_file = null; // file hilang → jangan tampilkan tombol unduh
                    }

                    return $r;
                }),
        ]);
    }
}
