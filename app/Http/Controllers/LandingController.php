<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    /**
     * Halaman Landing (publik) — hero + status live + fitur + kontak.
     */
    public function index(): Response
    {
        return Inertia::render('Landing', [
            'status' => $this->summary(),
        ]);
    }

    /**
     * Data status ringkas untuk refresh berkala (endpoint publik).
     */
    public function status(): JsonResponse
    {
        return response()->json($this->summary());
    }

    /**
     * Ringkasan AGREGAT — tanpa IP/nama perangkat (aman untuk publik).
     *
     * @return array<string, mixed>
     */
    protected function summary(): array
    {
        $total = Device::where('enabled', true)->count();
        $up = Device::where('enabled', true)->where('status', 'up')->count();
        $down = Device::where('enabled', true)->where('status', 'down')->count();

        $openTickets = Ticket::whereIn('status', ['open', 'proses'])->count();

        return [
            'total' => $total,
            'up' => $up,
            'down' => $down,
            'unknown' => max(0, $total - $up - $down),
            'uptime_pct' => $total > 0 ? round($up / $total * 100, 1) : null,
            'open_tickets' => $openTickets,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
