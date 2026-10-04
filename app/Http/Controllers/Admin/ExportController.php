<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceRegistration;
use App\Models\Ticket;
use App\Models\VpsRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Export laporan admin/operator — PDF (dompdf) atau Excel (openspout).
 *
 * Dataset: tiket gangguan, pendaftaran VPS, Domain, Hosting (atau semua).
 * Rentang: harian (hari ini), mingguan (7 hari terakhir), atau rentang tanggal.
 */
class ExportController extends Controller
{
    public const DATASETS = [
        'semua' => 'Semua Data',
        'tiket' => 'Tiket Gangguan',
        'vps' => 'Pendaftaran VPS',
        'domain' => 'Pendaftaran Domain',
        'hosting' => 'Pendaftaran Hosting',
    ];

    public const PERIODS = [
        'harian' => 'Harian (hari ini)',
        'mingguan' => 'Mingguan (7 hari terakhir)',
        'custom' => 'Rentang tanggal',
    ];

    public const FORMATS = [
        'pdf' => 'PDF',
        'excel' => 'Excel (XLSX)',
    ];

    /** @var list<string> */
    public const HEADERS = [
        'Jenis',
        'Kode',
        'Nama / Pelapor',
        'Instansi / Kategori',
        'Detail',
        'Keterangan',
        'Status',
        'Catatan Admin',
        'Dibuat',
    ];

    public function index(): Response
    {
        return Inertia::render('Admin/Export', [
            'datasets' => self::DATASETS,
            'periods' => self::PERIODS,
            'formats' => self::FORMATS,
        ]);
    }

    /**
     * Preview jumlah baris untuk filter yang dipilih (dipakai halaman form).
     */
    public function preview(Request $request): JsonResponse
    {
        [$dataset, , $periode, $from, $to] = $this->filters($request);
        $rows = $this->rows($dataset, $from, $to);

        return response()->json([
            'count' => count($rows),
            'from' => $from->format('d M Y'),
            'to' => $to->format('d M Y H:i'),
            'period_label' => self::PERIODS[$periode],
        ]);
    }

    /**
     * Unduh berkas export (PDF / XLSX).
     */
    public function download(Request $request)
    {
        [$dataset, $format, $periode, $from, $to] = $this->filters($request);

        $rows = $this->rows($dataset, $from, $to);
        $filename = sprintf(
            'laporan-%s-%s-%s',
            str_replace(' ', '-', strtolower(self::DATASETS[$dataset])),
            $periode,
            now()->format('Ymd-His')
        );

        $meta = [
            'title' => 'Laporan '.self::DATASETS[$dataset],
            'period' => self::PERIODS[$periode],
            'range' => $from->format('d/m/Y').' — '.$to->format('d/m/Y H:i'),
            'generated_at' => now()->format('d/m/Y H:i'),
            'count' => count($rows),
            'app' => config('app.name'),
        ];

        if ($format === 'pdf') {
            return Pdf::loadView('exports.laporan', [
                'headers' => self::HEADERS,
                'rows' => $rows,
                'meta' => $meta,
            ])->setPaper('a4', 'landscape')->download($filename.'.pdf');
        }

        return response()->streamDownload(function () use ($rows): void {
            $writer = new Writer();
            $writer->openToWrite('php://output');

            $headerStyle = (new Style())
                ->setBackgroundColor('#1d4ed8')
                ->setFontColor('#ffffff')
                ->setFontSize(11);

            $writer->addRow(Row::fromValues(self::HEADERS, $headerStyle));

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues(array_map(fn ($v) => (string) ($v ?? ''), $row)));
            }

            $writer->close();
        }, $filename.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Validasi filter + hitung rentang waktu.
     *
     * @return array{0: string, 1: string, 2: Carbon, 3: Carbon}
     */
    protected function filters(Request $request): array
    {
        $data = $request->validate([
            'dataset' => ['required', Rule::in(array_keys(self::DATASETS))],
            'format' => ['required', Rule::in(array_keys(self::FORMATS))],
            'periode' => ['required', Rule::in(array_keys(self::PERIODS))],
            'dari' => ['required_if:periode,custom', 'nullable', 'date'],
            'sampai' => ['required_if:periode,custom', 'nullable', 'date', 'after_or_equal:dari'],
        ], [
            'dataset.in' => 'Jenis data tidak dikenal.',
            'format.in' => 'Format export tidak dikenal — pilih pdf atau excel.',
            'periode.in' => 'Rentang waktu tidak dikenal.',
            'dari.required_if' => 'Tanggal mulai wajib diisi untuk rentang tanggal.',
            'sampai.required_if' => 'Tanggal selesai wajib diisi untuk rentang tanggal.',
            'sampai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ]);

        [$from, $to] = match ($data['periode']) {
            'harian' => [now()->startOfDay(), now()->endOfDay()],
            'mingguan' => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
            default => [
                Carbon::parse($data['dari'])->startOfDay(),
                Carbon::parse($data['sampai'])->endOfDay(),
            ],
        };

        return [$data['dataset'], $data['format'], $data['periode'], $from, $to];
    }

    /**
     * Baris export per dataset (kolom sama → bisa digabung untuk "semua").
     *
     * @return list<array<int, string>>
     */
    protected function rows(string $dataset, Carbon $from, Carbon $to): array
    {
        $items = [];

        if (in_array($dataset, ['semua', 'tiket'], true)) {
            foreach (Ticket::whereBetween('created_at', [$from, $to])->orderBy('created_at')->get() as $t) {
                $items[] = ['at' => $t->created_at, 'cells' => $this->ticketRow($t)];
            }
        }

        if (in_array($dataset, ['semua', 'vps'], true)) {
            foreach (VpsRequest::whereBetween('created_at', [$from, $to])->orderBy('created_at')->get() as $v) {
                $items[] = ['at' => $v->created_at, 'cells' => $this->vpsRow($v)];
            }
        }

        if (in_array($dataset, ['semua', 'domain', 'hosting'], true)) {
            $services = ServiceRegistration::whereBetween('created_at', [$from, $to])
                ->when($dataset !== 'semua', fn ($q) => $q->where('type', $dataset))
                ->orderBy('created_at')
                ->get();

            foreach ($services as $s) {
                $items[] = ['at' => $s->created_at, 'cells' => $this->serviceRow($s)];
            }
        }

        usort($items, fn (array $a, array $b) => $a['at']->lt($b['at']) ? -1 : ($a['at']->gt($b['at']) ? 1 : 0));

        return array_map(fn (array $item) => $item['cells'], $items);
    }

    /**
     * @return list<string>
     */
    protected function ticketRow(Ticket $t): array
    {
        $location = $t->location !== null && $t->location !== '' ? ' · Lokasi: '.$t->location : '';

        return [
            'Tiket Gangguan',
            $t->code,
            (string) ($t->reporter_name ?? $t->reporter?->name ?? '-'),
            (string) $t->category,
            (string) $t->title,
            $this->limit($t->description.$location),
            $this->label($t->status, 'tiket'),
            '-',
            $t->created_at->format('d/m/Y H:i'),
        ];
    }

    /**
     * @return list<string>
     */
    protected function vpsRow(VpsRequest $v): array
    {
        $parts = [$v->cores.' core', $v->ram_gb.' GB', $v->public_ips.' IP'];

        if ($v->os !== null && $v->os !== '') {
            $osList = (array) config('noc.vps_operating_systems', []);
            $parts[] = 'OS: '.($v->os === 'lainnya'
                ? ($v->os_other ?: 'Lainnya')
                : ($osList[$v->os] ?? $v->os));
        }

        if (is_array($v->ports) && $v->ports !== []) {
            $parts[] = 'Port: '.implode(', ', $v->ports);
        }

        if ($v->custom_ports !== null && $v->custom_ports !== '') {
            $parts[] = 'Tambahan: '.$v->custom_ports;
        }

        return [
            'Pendaftaran VPS',
            $v->code,
            $v->name,
            $v->instansi,
            implode(' · ', $parts),
            $this->limit($v->purpose),
            $this->label($v->status, 'lainnya'),
            (string) ($v->admin_note ?? '-'),
            $v->created_at->format('d/m/Y H:i'),
        ];
    }

    /**
     * @return list<string>
     */
    protected function serviceRow(ServiceRegistration $s): array
    {
        return [
            $s->typeLabel(),
            (string) $s->code,
            $s->name,
            $s->instansi,
            $s->specSummary(),
            $this->limit($s->purpose),
            $this->label($s->status, 'lainnya'),
            (string) ($s->admin_note ?? '-'),
            $s->created_at->format('d/m/Y H:i'),
        ];
    }

    protected function label(string $status, string $kind): string
    {
        $map = $kind === 'tiket'
            ? (array) config('noc.ticket_statuses', [])
            : (array) config('noc.service_statuses', []);

        return (string) ($map[$status] ?? $status);
    }

    protected function limit(?string $text, int $max = 400): string
    {
        $text = trim((string) $text);

        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1).'…' : $text;
    }
}
