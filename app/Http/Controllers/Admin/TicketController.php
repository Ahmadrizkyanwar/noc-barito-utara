<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Services\Tickets\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function __construct(protected TicketService $tickets) {}

    /**
     * Daftar semua tiket (admin) + filter.
     */
    public function index(Request $request): Response
    {
        $q = Ticket::with(['reporter:id,name', 'assignee:id,name'])->latest();

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }
        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $q->where(fn ($w) => $w->where('code', 'like', $term)
                ->orWhere('title', 'like', $term)
                ->orWhere('reporter_name', 'like', $term));
        }

        $stats = [
            'open' => Ticket::where('status', 'open')->count(),
            'proses' => Ticket::where('status', 'proses')->count(),
            'selesai' => Ticket::where('status', 'selesai')->count(),
        ];

        return Inertia::render('Admin/Layanan', [
            'tickets' => $q->paginate(15)->withQueryString(),
            'stats' => $stats,
            'filters' => $request->only(['status', 'q']),
            'statuses' => config('noc.ticket_statuses'),
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
     * Ubah status (JSON untuk UI).
     */
    public function updateStatus(Request $request, Ticket $ticket): JsonResponse
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

        return response()->json([
            'ok' => true,
            'changed' => $result['changed'],
            'ticket' => $result['ticket']->fresh('activities.user:id,name'),
        ]);
    }

    /**
     * Tambah catatan penanganan.
     */
    public function addNote(Request $request, Ticket $ticket): JsonResponse
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'max:1000'],
        ]);

        $this->tickets->addNote($ticket, $data['note'], $request->user()->id);

        return response()->json([
            'ok' => true,
            'ticket' => $ticket->fresh('activities.user:id,name'),
        ]);
    }

    /**
     * Tugaskan ke admin.
     */
    public function assign(Request $request, Ticket $ticket): JsonResponse
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

        return response()->json([
            'ok' => true,
            'ticket' => $ticket->fresh('assignee:id,name'),
        ]);
    }
}
