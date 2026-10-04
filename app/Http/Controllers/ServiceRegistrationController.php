<?php

namespace App\Http\Controllers;

use App\Models\ServiceRegistration;
use App\Services\Notifications\ReviewerNotifier;
use App\Services\Telegram\Notifier as TelegramNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pendaftaran Domain & Hosting — halaman user (`/domain`, `/hosting`).
 *
 * Satu controller untuk dua tipe; tipe diambil dari route default `type`,
 * aturan per tipe dari config('noc.service_registrations.*').
 * Mengikuti pola VpsRequestController (gate akun, dokumen pendukung,
 * lonceng reviewer + Telegram webhook `tiket`).
 */
class ServiceRegistrationController extends Controller
{
    public function __construct(
        protected ReviewerNotifier $notifier,
        protected TelegramNotifier $telegram,
    ) {}

    public function index(Request $request): Response
    {
        $type = $this->type($request);
        $user = $request->user();
        $meta = $this->meta($type);

        return Inertia::render('ServiceRegistration', [
            'type' => $type,
            'label' => (string) $meta['label'],
            'durations' => (array) ($meta['durations'] ?? []),
            'packages' => (array) ($meta['packages'] ?? []),
            'canRequest' => $user->canRequestVps(),
            'accountStatus' => $user->status,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = $this->type($request);
        $label = (string) $this->meta($type)['label'];

        // Guard server-side: akun pending/rejected tidak boleh mendaftar.
        if (! $request->user()->canRequestVps()) {
            return back()->with('error', 'Fitur '.$label.' terkunci — akun Anda menunggu validasi admin.');
        }

        $data = $request->validate($this->rules($type), $this->messages($type));

        if ($request->hasFile('supporting_document')) {
            $data['supporting_document'] = $request->file('supporting_document')
                ->store('uploads/service-registrations', 'public');
            $data['supporting_document_uploaded_at'] = now();
        }

        $data['type'] = $type;
        $data['code'] = $this->nextCode((string) $this->meta($type)['code_prefix']);

        $registration = $request->user()->serviceRegistrations()->create($data);

        // Lonceng notifikasi untuk admin/operator.
        $this->notifier->serviceRegistrationReceived($registration);

        // Tiket masuk (pendaftaran domain/hosting) → Telegram webhook `tiket`.
        try {
            $this->telegram->sendServiceRegistrationCreated($registration);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()
            ->route((string) $request->route()->getName())
            ->with('success', $label.' '.$registration->code.' terkirim — menunggu review admin/operator.');
    }

    /**
     * Unduh dokumen pendukung — hanya pemilik ATAU reviewer.
     */
    public function document(Request $request, ServiceRegistration $serviceRegistration)
    {
        $user = $request->user();

        if ($serviceRegistration->user_id !== $user->id && ! $user->canReview()) {
            abort(403);
        }

        if ($serviceRegistration->supporting_document === null) {
            abort(404);
        }

        $base = realpath(Storage::disk('public')->path(''));
        $target = realpath(Storage::disk('public')->path($serviceRegistration->supporting_document));

        if ($base === false || $target === false
            || ! str_starts_with($target, $base.DIRECTORY_SEPARATOR)
            || ! is_file($target)) {
            abort(404);
        }

        $ext = strtolower(pathinfo($serviceRegistration->supporting_document, PATHINFO_EXTENSION));

        return response()->download(
            $target,
            'dokumen-'.$serviceRegistration->code.($ext !== '' ? '.'.$ext : '')
        );
    }

    /**
     * Tipe dari route default — 404 bila tidak dikenal config.
     */
    protected function type(Request $request): string
    {
        $type = (string) $request->route('type');
        $all = (array) config('noc.service_registrations', []);

        abort_unless(array_key_exists($type, $all), 404);

        return $type;
    }

    /**
     * @return array<string, mixed>
     */
    protected function meta(string $type): array
    {
        return (array) config('noc.service_registrations.'.$type, []);
    }

    /**
     * Aturan validasi: umum + spesifik tipe.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function rules(string $type): array
    {
        $common = [
            'name' => ['required', 'string', 'max:100'],
            'nip' => ['required', 'string', 'max:30'],
            'jabatan' => ['required', 'string', 'max:100'],
            'instansi' => ['required', 'string', 'max:150'],
            'purpose' => ['required', 'string', 'max:1000'],
            'supporting_document' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:5120'],
        ];

        $domainRegex = '/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i';

        if ($type === ServiceRegistration::TYPE_DOMAIN) {
            return $common + [
                'domain_name' => ['required', 'string', 'max:255', 'regex:'.$domainRegex],
                'duration' => ['required', 'integer', Rule::in(array_keys((array) $this->meta($type)['durations']))],
            ];
        }

        return $common + [
            'domain_name' => ['nullable', 'string', 'max:255', 'regex:'.$domainRegex],
            'hosting_package' => ['required', 'string', Rule::in(array_keys((array) $this->meta($type)['packages']))],
            'duration' => ['required', 'integer', Rule::in(array_keys((array) $this->meta($type)['durations']))],
        ];
    }

    /**
     * Pesan validasi (Bahasa Indonesia).
     *
     * @return array<string, string>
     */
    protected function messages(string $type): array
    {
        return [
            'domain_name.regex' => 'Format nama domain tidak valid — contoh: diskominfosandi.go.id.',
            'duration.in' => 'Durasi tidak tersedia untuk pilihan ini.',
            'hosting_package.in' => 'Paket hosting tidak dikenal.',
            'supporting_document.mimes' => 'Dokumen pendukung harus berformat PDF, DOC, DOCX, JPG, atau PNG.',
        ];
    }

    /**
     * Kode unik per hari per tipe: DOM-YYYYMMDD-NNNN / HST-YYYYMMDD-NNNN.
     */
    protected function nextCode(string $prefix): string
    {
        $prefix .= '-'.now()->format('Ymd').'-';

        $last = ServiceRegistration::where('code', 'like', $prefix.'%')
            ->orderByDesc('code')
            ->value('code');

        $seq = $last !== null ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
