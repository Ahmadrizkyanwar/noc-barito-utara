<?php

namespace App\Http\Controllers;

use App\Models\VpsRequest;
use App\Services\Notifications\ReviewerNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Request VPS — halaman user terdaftar (`/vps`).
 * Fitur terkunci sampai registrasi akun disetujui admin/operator.
 */
class VpsRequestController extends Controller
{
    public function __construct(protected ReviewerNotifier $notifier) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        // Halaman ini HANYA formulir — riwayat request tampil di Dashboard user.
        return Inertia::render('VpsRequest', [
            'canRequest' => $user->canRequestVps(),
            'accountStatus' => $user->status,
            'ports' => config('noc.vps_ports'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Guard server-side: akun pending/rejected tidak boleh mengirim request.
        if (! $request->user()->canRequestVps()) {
            return back()->with('error', 'Fitur Request VPS terkunci — akun Anda menunggu validasi admin.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'nip' => ['required', 'string', 'max:30'],
            'jabatan' => ['required', 'string', 'max:100'],
            'instansi' => ['required', 'string', 'max:150'],
            'cores' => ['required', 'integer', 'between:1,256'],
            'ram_gb' => ['required', 'integer', 'between:1,1024'],
            'public_ips' => ['required', 'integer', 'between:1,16'],
            'ports' => ['required', 'array', 'min:1'],
            'ports.*' => ['string', Rule::in(array_keys(config('noc.vps_ports')))],
            'purpose' => ['required', 'string', 'max:1000'],
        ]);

        $data['code'] = $this->nextVpsCode();

        $vpsRequest = $request->user()->vpsRequests()->create($data);

        // Lonceng notifikasi untuk admin/operator.
        $this->notifier->vpsRequestReceived($vpsRequest);

        return redirect()
            ->route('vps.index')
            ->with('success', 'Request VPS '.$vpsRequest->code.' terkirim — menunggu review admin/operator.');
    }

    /**
     * Unduh dokumen kredensial — hanya pemilik request ATAU reviewer.
     */
    public function credentials(Request $request, VpsRequest $vpsRequest)
    {
        $user = $request->user();

        if ($vpsRequest->user_id !== $user->id && ! $user->canReview()) {
            abort(403);
        }

        if ($vpsRequest->credential_file === null) {
            abort(404);
        }

        $base = realpath(Storage::disk('public')->path(''));
        $target = realpath(Storage::disk('public')->path($vpsRequest->credential_file));

        if ($base === false || $target === false
            || ! str_starts_with($target, $base.DIRECTORY_SEPARATOR)
            || ! is_file($target)) {
            abort(404);
        }

        $ext = strtolower(pathinfo($vpsRequest->credential_file, PATHINFO_EXTENSION));

        return response()->download($target, 'kredensial-'.$vpsRequest->code.($ext !== '' ? '.'.$ext : ''));
    }

    /**
     * Kode unik VPS-YYYYMMDD-NNNN (berbeda dari TKT- pada tiket gangguan).
     */
    protected function nextVpsCode(): string
    {
        $prefix = 'VPS-'.now()->format('Ymd').'-';

        $last = VpsRequest::where('code', 'like', $prefix.'%')
            ->orderByDesc('code')
            ->value('code');

        $seq = $last !== null ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
