<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceRegistration;
use App\Services\Notifications\ReviewerNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Review pendaftaran Domain & Hosting — halaman admin/operator.
 * Satu halaman untuk dua tipe (filter `?type=domain|hosting`) + filter status.
 */
class ServiceReviewController extends Controller
{
    public function __construct(protected ReviewerNotifier $notifier) {}

    public function index(Request $request): Response
    {
        $statuses = array_keys(config('noc.service_statuses'));
        $types = array_keys(config('noc.service_registrations'));

        $status = (string) $request->query('status', '');
        $type = (string) $request->query('type', '');

        $registrations = ServiceRegistration::query()
            ->with(['user:id,name,email', 'reviewer:id,name'])
            ->when(in_array($type, $types, true), fn ($q) => $q->where('type', $type))
            ->when(in_array($status, $statuses, true), fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        // File hilang → sembunyikan baris dokumen agar tidak menipu.
        $registrations->through(function (ServiceRegistration $r) {
            if ($r->supporting_document !== null && ! Storage::disk('public')->exists($r->supporting_document)) {
                $r->supporting_document = null;
            }

            return $r;
        });

        return Inertia::render('Admin/ServiceReview', [
            'registrations' => $registrations,
            'statuses' => config('noc.service_statuses'),
            'types' => collect($types)
                ->mapWithKeys(fn ($t) => [$t => config('noc.service_registrations.'.$t.'.label')])
                ->all(),
            'packages' => (array) config('noc.service_registrations.hosting.packages', []),
            'durations' => collect($types)
                ->mapWithKeys(fn ($t) => [$t => (array) config('noc.service_registrations.'.$t.'.durations', [])])
                ->all(),
            'filters' => [
                'status' => in_array($status, $statuses, true) ? $status : '',
                'type' => in_array($type, $types, true) ? $type : '',
            ],
        ]);
    }

    public function updateStatus(Request $request, ServiceRegistration $serviceRegistration): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([ServiceRegistration::STATUS_APPROVED, ServiceRegistration::STATUS_REJECTED])],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $changed = $serviceRegistration->status !== $data['status'];

        $serviceRegistration->update([
            'status' => $data['status'],
            'admin_note' => $data['admin_note'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        // Lonceng notifikasi untuk pemilik (hanya saat status berubah).
        if ($changed) {
            $this->notifier->serviceStatusUpdated($serviceRegistration);
        }

        $label = config('noc.service_statuses.'.$data['status'], $data['status']);

        return back()->with('success', $serviceRegistration->code.' → '.$label.'.');
    }
}
