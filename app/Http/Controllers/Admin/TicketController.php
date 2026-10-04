<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\VpsRequest;
use App\Services\Tickets\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function __construct(protected TicketService $tickets) {}

    /**
     * Daftar semua tiket (admin) + filter — DENGAN request VPS (kode VPS-…)
     * digabung ke sistem ticketing lewat UNION.
     */
    public function index(Request $request): Response
    {
        $status = (string) $request->query('status', '');
        $term = '%'.trim((string) $request->query('q')).'%';
        $q = trim((string) $request->query('q'));

        $ticketStatuses = array_keys(config('noc.ticket_statuses'));
        $vpsStatuses = array_keys(config('noc.vps_statuses'));

        // ── Cabang tiket gangguan ──
        $tickets = DB::table('tickets')
            ->select([
                'id', 'code', 'title', 'category', 'reporter_name',
                'status', 'created_at',
                DB::raw("'ticket' as kind"),
                DB::raw('NULL as instansi'),
            ]);

        // ── Cabang request VPS (judul disusun di frontend) ──
        $vps = DB::table('vps_requests')
            ->select([
                'id', 'code',
                DB::raw('NULL as title'),
                DB::raw("'VPS' as category"),
                DB::raw('name as reporter_name'),
                'status', 'created_at',
                DB::raw("'vps' as kind"),
                'instansi',
            ]);

        // Filter status: status tiket → hanya tiket; status VPS → hanya VPS.
        if ($status !== '') {
            if (in_array($status, $ticketStatuses, true)) {
                $tickets->where('status', $status);
                $vps->whereRaw('1 = 0');
            } elseif (in_array($status, $vpsStatuses, true)) {
                $vps->where('status', $status);
                $tickets->whereRaw('1 = 0');
            } else {
                $tickets->whereRaw('1 = 0');
                $vps->whereRaw('1 = 0');
            }
        }

        if ($q !== '') {
            $tickets->where(fn ($w) => $w->where('code', 'like', $term)
                ->orWhere('title', 'like', $term)
                ->orWhere('reporter_name', 'like', $term));

            $vps->where(fn ($w) => $w->where('code', 'like', $term)
                ->orWhere('instansi', 'like', $term)
                ->orWhere('name', 'like', $term));
        }

        $rows = DB::query()
            ->fromSub($tickets->unionAll($vps), 'rows')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $stats = [
            'open' => Ticket::where('status', 'open')->count(),
            'proses' => Ticket::where('status', 'proses')->count(),
            'selesai' => Ticket::where('status', 'selesai')->count(),
            'vps_pending' => VpsRequest::where('status', VpsRequest::STATUS_PENDING)->count(),
        ];

        return Inertia::render('Admin/Layanan', [
            'tickets' => $rows->paginate(15)->withQueryString(),
            'stats' => $stats,
            'filters' => $request->only(['status', 'q']),
            'statuses' => config('noc.ticket_statuses'),
            'allStatuses' => [...config('noc.ticket_statuses'), ...config('noc.vps_statuses')],
        ]);
    }

    /**
     * Detail tiket + riwayat aktivitas.
     */
    public function show(Ticket $ticket): Response
    {
        $ticket->load([
            'reporter:id,name,email',
            'assignee:id,name',
            'activities.user:id,name',
        ]);

        return Inertia::render('Admin/TiketDetail', [
            'ticket' => $ticket,
            'statuses' => config('noc.ticket_statuses'),
        ]);
    }

    /**
     * Ubah status (JSON untuk tes/API, redirect untuk form Inertia).
     */
    public function updateStatus(Request $request, Ticket $ticket): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(config('noc.ticket_statuses')))],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $this->tickets->changeStatus(
            $ticket,
            $data['status'],
            $request->user()->id,
            $data['note'] ?? null
        );

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'changed' => $result['changed'],
                'ticket' => $result['ticket']->fresh('activities.user:id,name'),
            ]);
        }

        return back();
    }

    /**
     * Tambah catatan penanganan.
     */
    public function addNote(Request $request, Ticket $ticket): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'max:1000'],
        ]);

        $this->tickets->addNote($ticket, $data['note'], $request->user()->id);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'ticket' => $ticket->fresh('activities.user:id,name'),
            ]);
        }

        return back();
    }

    /**
     * Tugaskan ke admin.
     */
    public function assign(Request $request, Ticket $ticket): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $old = $ticket->assignee_id;
        $ticket->update(['assignee_id' => $data['assignee_id'] ?? null]);

        TicketActivity::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'action' => 'assign',
            'old_value' => $old !== null ? (string) $old : null,
            'new_value' => isset($data['assignee_id']) ? (string) $data['assignee_id'] : null,
            'note' => isset($data['assignee_id']) ? 'Ditugaskan' : 'Tugas dilepas',
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'ticket' => $ticket->fresh('assignee:id,name'),
            ]);
        }

        return back();
    }
}
