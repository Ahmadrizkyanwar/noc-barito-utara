<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VpsRequest;
use App\Services\Notifications\ReviewerNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Review request VPS — halaman admin/operator.
 * Termasuk upload dokumen kredensial setelah disetujui (+ notifikasi).
 */
class VpsReviewController extends Controller
{
    public function __construct(protected ReviewerNotifier $notifier) {}

    public function index(Request $request): Response
    {
        $statuses = array_keys(config('noc.vps_statuses'));
        $filter = (string) $request->query('status', '');

        $requests = VpsRequest::query()
            ->with(['user:id,name,email', 'reviewer:id,name'])
            ->when(in_array($filter, $statuses, true), fn ($q) => $q->where('status', $filter))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        // File hilang → sembunyikan baris "dokumen terunggah" agar tidak menipu.
        $requests->through(function (VpsRequest $r) {
            if ($r->credential_file !== null && ! Storage::disk('public')->exists($r->credential_file)) {
                $r->credential_file = null;
            }

            if ($r->supporting_document !== null && ! Storage::disk('public')->exists($r->supporting_document)) {
                $r->supporting_document = null;
            }

            return $r;
        });

        return Inertia::render('Admin/VpsReview', [
            'requests' => $requests,
            'statuses' => config('noc.vps_statuses'),
            'ports' => config('noc.vps_ports'),
            'operatingSystems' => config('noc.vps_operating_systems'),
            'filters' => ['status' => in_array($filter, $statuses, true) ? $filter : ''],
        ]);
    }

    public function updateStatus(Request $request, VpsRequest $vpsRequest): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([VpsRequest::STATUS_APPROVED, VpsRequest::STATUS_REJECTED])],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $changed = $vpsRequest->status !== $data['status'];

        $vpsRequest->update([
            'status' => $data['status'],
            'admin_note' => $data['admin_note'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        // Lonceng notifikasi untuk pemilik request (hanya saat status berubah).
        if ($changed) {
            $this->notifier->vpsStatusUpdated($vpsRequest);
        }

        $label = config('noc.vps_statuses.'.$data['status'], $data['status']);

        return back()->with('success', 'Request VPS '.$vpsRequest->code.' → '.$label.'.');
    }

    /**
     * Upload dokumen kredensial (setelah disetujui) → notifikasi user + reviewer.
     */
    public function uploadCredentials(Request $request, VpsRequest $vpsRequest): RedirectResponse
    {
        if (! $vpsRequest->isApproved()) {
            return back()->with('error', 'Kredensial hanya bisa diunggah setelah request disetujui.');
        }

        $data = $request->validate([
            'credential' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,txt', 'max:5120'],
        ]);

        // Ganti berkas lama (hindari penumpukan).
        if ($vpsRequest->credential_file !== null) {
            Storage::disk('public')->delete($vpsRequest->credential_file);
        }

        $path = $data['credential']->store('uploads/vps', 'public');

        $vpsRequest->update([
            'credential_file' => $path,
            'credential_uploaded_at' => now(),
        ]);

        // Notifikasi: user pemilik + seluruh reviewer (kecuali pengunggah).
        $this->notifier->credentialsReady($vpsRequest);
        $this->notifier->credentialsUploaded($vpsRequest, $request->user());

        return back()->with(
            'success',
            'Kredensial '.$vpsRequest->code.' terunggah — pemilik request telah menerima notifikasi.'
        );
    }
}
