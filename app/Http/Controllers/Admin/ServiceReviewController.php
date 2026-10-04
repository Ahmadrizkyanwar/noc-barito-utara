<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceRegistration;
use App\Models\User;
use App\Services\Notifications\ReviewerNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Review Pendaftaran — SATU halaman untuk VPS + Domain + Hosting.
 *
 * Baris digabung lewat UNION (`vps_requests` + `service_registrations`),
 * difilter `?type=vps|domain|hosting` dan `?status=`. Route lama `/admin/vps`
 * tetap ada (default type=vps) agar tautan lama & notifikasi tidak putus.
 * Review status memakai route masing-masing: `admin.vps.status` / `admin.services.status`.
 */
class ServiceReviewController extends Controller
{
    public function __construct(protected ReviewerNotifier $notifier) {}

    public function index(Request $request): Response
    {
        $statuses = array_keys(config('noc.service_statuses'));
        $types = ['vps', ...array_keys(config('noc.service_registrations'))];

        $status = (string) $request->query('status', '');
        // Route lama /admin/vps memakai default type=vps; /admin/pendaftaran → semua.
        $type = (string) $request->query('type', (string) $request->route('type'));

        // ── Cabang VPS ──
        $vps = DB::table('vps_requests')
            ->select([
                'id',
                DB::raw("'vps' as kind"),
                DB::raw("'vps' as type"),
                'code', 'user_id', 'name', 'nip', 'jabatan', 'instansi',
                'cores', 'ram_gb', 'public_ips', 'os', 'os_other', 'ports', 'custom_ports',
                'purpose',
                DB::raw('NULL as domain_name'),
                DB::raw('NULL as hosting_package'),
                DB::raw('NULL as duration'),
                'supporting_document', 'supporting_document_uploaded_at',
                'credential_file', 'credential_uploaded_at',
                'status', 'admin_note', 'reviewed_by', 'reviewed_at', 'created_at',
            ]);

        // ── Cabang Domain & Hosting ──
        $svc = DB::table('service_registrations')
            ->select([
                'id',
                DB::raw("'service' as kind"),
                'type',
                'code', 'user_id', 'name', 'nip', 'jabatan', 'instansi',
                DB::raw('NULL as cores'),
                DB::raw('NULL as ram_gb'),
                DB::raw('NULL as public_ips'),
                DB::raw('NULL as os'),
                DB::raw('NULL as os_other'),
                DB::raw('NULL as ports'),
                DB::raw('NULL as custom_ports'),
                'purpose',
                'domain_name', 'hosting_package', 'duration',
                'supporting_document', 'supporting_document_uploaded_at',
                DB::raw('NULL as credential_file'),
                DB::raw('NULL as credential_uploaded_at'),
                'status', 'admin_note', 'reviewed_by', 'reviewed_at', 'created_at',
            ]);

        if (in_array($type, $types, true) && $type !== '') {
            if ($type === 'vps') {
                $svc->whereRaw('1 = 0');
            } else {
                $vps->whereRaw('1 = 0');
                $svc->where('type', $type);
            }
        }

        if (in_array($status, $statuses, true)) {
            $vps->where('status', $status);
            $svc->where('status', $status);
        }

        $paginator = DB::query()
            ->fromSub($vps->unionAll($svc), 'rows')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // Eager-load pemilik & reviewer (unik dari halaman ini saja).
        $pageRows = collect($paginator->items());
        $users = User::whereIn('id', $pageRows->pluck('user_id')->filter()->unique())
            ->get(['id', 'name', 'email'])->keyBy('id');
        $reviewers = User::whereIn('id', $pageRows->pluck('reviewed_by')->filter()->unique())
            ->get(['id', 'name'])->keyBy('id');

        $items = $pageRows->map(function (object $r) use ($users, $reviewers): array {
            $ports = null;

            if ($r->kind === 'vps' && $r->ports !== null) {
                $ports = json_decode((string) $r->ports, true) ?: [];
            }

            // File hilang → sembunyikan tautan unduh agar tidak menipu.
            $supporting = $this->existing($r->supporting_document);
            $credential = $this->existing($r->credential_file);

            return [
                'id' => $r->id,
                'kind' => $r->kind,
                'type' => $r->type,
                'code' => $r->code,
                'name' => $r->name,
                'nip' => $r->nip,
                'jabatan' => $r->jabatan,
                'instansi' => $r->instansi,
                'cores' => $r->cores !== null ? (int) $r->cores : null,
                'ram_gb' => $r->ram_gb !== null ? (int) $r->ram_gb : null,
                'public_ips' => $r->public_ips !== null ? (int) $r->public_ips : null,
                'os' => $r->os,
                'os_other' => $r->os_other,
                'ports' => $ports,
                'custom_ports' => $r->custom_ports,
                'domain_name' => $r->domain_name,
                'hosting_package' => $r->hosting_package,
                'duration' => $r->duration !== null ? (int) $r->duration : null,
                'purpose' => $r->purpose,
                'supporting_document' => $supporting,
                'supporting_document_uploaded_at' => $r->supporting_document_uploaded_at,
                'credential_file' => $credential,
                'credential_uploaded_at' => $r->credential_uploaded_at,
                'status' => $r->status,
                'admin_note' => $r->admin_note,
                'reviewed_at' => $r->reviewed_at,
                'created_at' => $r->created_at,
                'user' => $r->user_id !== null && isset($users[$r->user_id])
                    ? ['id' => $users[$r->user_id]->id, 'name' => $users[$r->user_id]->name, 'email' => $users[$r->user_id]->email]
                    : null,
                'reviewer' => $r->reviewed_by !== null && isset($reviewers[$r->reviewed_by])
                    ? ['id' => $reviewers[$r->reviewed_by]->id, 'name' => $reviewers[$r->reviewed_by]->name]
                    : null,
            ];
        });

        /** @var LengthAwarePaginator $paginator */
        $paginator->setCollection($items);

        return Inertia::render('Admin/ServiceReview', [
            'registrations' => $paginator,
            'statuses' => config('noc.service_statuses'),
            'types' => ['vps' => 'Request VPS'] + collect(config('noc.service_registrations'))
                ->mapWithKeys(fn ($meta, $key) => [$key => (string) ($meta['label'] ?? $key)])
                ->all(),
            'ports' => config('noc.vps_ports'),
            'operatingSystems' => config('noc.vps_operating_systems'),
            'packages' => (array) config('noc.service_registrations.hosting.packages', []),
            'durations' => collect(array_keys(config('noc.service_registrations')))
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

    /**
     * Path file ada di disk publik? (null bila tidak ada/hilang)
     */
    protected function existing(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return Storage::disk('public')->exists($path) ? $path : null;
    }
}
