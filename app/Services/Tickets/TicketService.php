<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Services\Telegram\Notifier;
use Illuminate\Support\Facades\DB;

/**
 * Lifecycle tiket laporan gangguan.
 *
 * - Kode unik `TKT-YYYYMMDD-NNNN` (urut per hari).
 * - Setiap perubahan status/catatan dicatat ke ticket_activities.
 * - Tiket baru → notifikasi Telegram (webhook `tiket`).
 */
class TicketService
{
    public function __construct(protected Notifier $notifier) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?int $userId = null): Ticket
    {
        return DB::transaction(function () use ($data, $userId) {
            $ticket = Ticket::create([
                'code' => $this->nextCode(),
                'title' => $data['title'],
                'category' => $data['category'] ?? 'Jaringan',
                'description' => $data['description'],
                'location' => $data['location'] ?? null,
                'lat' => $data['lat'] ?? null,
                'lng' => $data['lng'] ?? null,
                'photo' => $data['photo'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'user_id' => $userId,
                'reporter_name' => $data['reporter_name'] ?? null,
                'reporter_contact' => $data['reporter_contact'] ?? null,
            ]);

            TicketActivity::create([
                'ticket_id' => $ticket->id,
                'user_id' => $userId,
                'action' => 'created',
                'new_value' => $ticket->status,
                'note' => 'Tiket dibuat',
            ]);

            return $ticket;
        });
    }

    /**
     * Ubah status + catat aktivitas + notify bila selesai.
     *
     * @return array{ticket: Ticket, changed: bool}
     */
    public function changeStatus(Ticket $ticket, string $status, ?int $actorId, ?string $note = null): array
    {
        if ($ticket->status === $status) {
            return ['ticket' => $ticket, 'changed' => false];
        }

        $old = $ticket->status;

        DB::transaction(function () use ($ticket, $status, $actorId, $note, $old) {
            $ticket->update([
                'status' => $status,
                'resolved_at' => $status === 'selesai' ? now() : null,
            ]);

            TicketActivity::create([
                'ticket_id' => $ticket->id,
                'user_id' => $actorId,
                'action' => 'status',
                'old_value' => $old,
                'new_value' => $status,
                'note' => $note,
            ]);
        });

        return ['ticket' => $ticket->refresh(), 'changed' => true];
    }

    /**
     * Tambah catatan (tanpa ubah status).
     */
    public function addNote(Ticket $ticket, string $note, ?int $actorId): Ticket
    {
        TicketActivity::create([
            'ticket_id' => $ticket->id,
            'user_id' => $actorId,
            'action' => 'note',
            'note' => $note,
        ]);

        return $ticket;
    }

    /**
     * Kode unik per hari: TKT-YYYYMMDD-0001.
     */
    public function nextCode(): string
    {
        $prefix = 'TKT-'.now()->format('Ymd').'-';

        $last = Ticket::where('code', 'like', $prefix.'%')
            ->orderByDesc('code')
            ->value('code');

        $seq = $last !== null ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Kirim notifikasi tiket baru (dipanggil SETELAH commit).
     */
    public function notifyCreated(Ticket $ticket): void
    {
        try {
            $this->notifier->sendTicketCreated($ticket);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
