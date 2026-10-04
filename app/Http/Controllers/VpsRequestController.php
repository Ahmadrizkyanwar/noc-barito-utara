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
            'operatingSystems' => config('noc.vps_operating_systems'),
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
            'os' => ['required', 'string', Rule::in(array_keys(config('noc.vps_operating_systems')))],
            'ports' => ['required_without:custom_ports', 'array'],
            'ports.*' => ['string', Rule::in(array_keys(config('noc.vps_ports')))],
            'custom_ports' => ['nullable', 'string', 'max:255', 'regex:/^$|^\d{1,5}(\s*-\s*\d{1,5})?(\s*,\s*\d{1,5}(\s*-\s*\d{1,5})?)*$/'],
            'purpose' => ['required', 'string', 'max:1000'],
            'supporting_document' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:5120'],
        ], [
            'os.in' => 'Sistem operasi tidak dikenal.',
            'custom_ports.regex' => 'Format service port tambahan tidak valid — gunakan angka/rentang dipisah koma, contoh: 8443, 9090, 3000-3100.',
            'supporting_document.mimes' => 'Dokumen pendukung harus berformat PDF, DOC, DOCX, JPG, atau PNG.',
        ]);

        // Minimal satu service port: pilihan di daftar ATAU isian bebas.
        $data['ports'] = $data['ports'] ?? [];

        if ($request->hasFile('supporting_document')) {
            $data['supporting_document'] = $request->file('supporting_document')
                ->store('uploads/vps/documents', 'public');
            $data['supporting_document_uploaded_at'] = now();
        }

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
        $this->authorizeOwnerOrReviewer($request, $vpsRequest);

        if ($vpsRequest->credential_file === null) {
            abort(404);
        }

        $ext = strtolower(pathinfo($vpsRequest->credential_file, PATHINFO_EXTENSION));

        return $this->downloadPublicFile(
            $vpsRequest->credential_file,
            'kredensial-'.$vpsRequest->code.($ext !== '' ? '.'.$ext : '')
        );
    }

    /**
     * Unduh dokumen pendukung yang diunggah user — pemilik request ATAU reviewer.
     */
    public function document(Request $request, VpsRequest $vpsRequest)
    {
        $this->authorizeOwnerOrReviewer($request, $vpsRequest);

        if ($vpsRequest->supporting_document === null) {
            abort(404);
        }

        $ext = strtolower(pathinfo($vpsRequest->supporting_document, PATHINFO_EXTENSION));

        return $this->downloadPublicFile(
            $vpsRequest->supporting_document,
            'dokumen-pendukung-'.$vpsRequest->code.($ext !== '' ? '.'.$ext : '')
        );
    }

    /**
     * Guard: hanya pemilik request atau reviewer (admin/operator).
     */
    protected function authorizeOwnerOrReviewer(Request $request, VpsRequest $vpsRequest): void
    {
        $user = $request->user();

        if ($vpsRequest->user_id !== $user->id && ! $user->canReview()) {
            abort(403);
        }
    }

    /**
     * Unduh berkas dari disk `public` dengan guard path-traversal.
     */
    protected function downloadPublicFile(string $path, string $filename)
    {
        $base = realpath(Storage::disk('public')->path(''));
        $target = realpath(Storage::disk('public')->path($path));

        if ($base === false || $target === false
            || ! str_starts_with($target, $base.DIRECTORY_SEPARATOR)
            || ! is_file($target)) {
            abort(404);
        }

        return response()->download($target, $filename);
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
