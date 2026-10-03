<?php

namespace App\Http\Controllers;

use App\Services\Tickets\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Laporan gangguan — form PUBLIK (tanpa login) di `/lapor`.
 * Bila pelapor sedang login, user_id ikut tersimpan.
 */
class ReportController extends Controller
{
    public function __construct(protected TicketService $tickets) {}

    public function create(): Response
    {
        return Inertia::render('Laporan', [
            'categories' => config('noc.ticket_categories'),
            'statuses' => config('noc.ticket_statuses'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', Rule::in(config('noc.ticket_categories'))],
            'description' => ['required', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:200'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'reporter_name' => ['required', 'string', 'max:100'],
            'reporter_contact' => ['nullable', 'string', 'max:100'],
            'photo' => ['nullable', 'image', 'max:2048'], // 2 MB
        ]);

        $userId = $request->user()?->id;

        // Pelapor login tidak wajib mengisi nama → pakai nama akun.
        if ($userId !== null && trim((string) $data['reporter_name']) === '') {
            $data['reporter_name'] = $request->user()->name;
        }

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('uploads', 'public');
        } else {
            unset($data['photo']);
        }

        $ticket = $this->tickets->create($data, $userId);

        // Notifikasi Telegram di luar transaksi (gagal = log, bukan error user).
        $this->tickets->notifyCreated($ticket);

        return redirect()
            ->route('lapor.index')
            ->with('success', 'Laporan berhasil dikirim. Kode tiket Anda: '.$ticket->code);
    }

    /**
     * Tampilkan foto tiket dari storage (dengan guard path).
     */
    public function photo(string $path)
    {
        $base = realpath(Storage::disk('public')->path(''));
        $target = realpath(Storage::disk('public')->path($path));

        if ($base === false || $target === false || ! str_starts_with($target, $base.DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        if (! is_file($target)) {
            abort(404);
        }

        return response()->file($target);
    }
}
