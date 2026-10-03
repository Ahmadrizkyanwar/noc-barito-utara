<?php

namespace App\Services\Network;

use Illuminate\Support\Facades\Process;

/**
 * Probe ICMP — binary `ping` (iputils) lewat Process facade TANPA shell.
 *
 * Gagal-tertutup: binary tidak ada / host tak terjangkau → {ok:false},
 * TIDAK PERNAH melempar exception ke pemanggil (poller tetap jalan).
 */
class PingProbe
{
    /**
     * @return array{ok: bool, rtt_ms: float|null, error: string|null}
     */
    public function ping(string $host): array
    {
        if ($host === '') {
            return ['ok' => false, 'rtt_ms' => null, 'error' => 'host kosong'];
        }

        $binary = (string) config('noc.icmp.binary', 'ping');
        $wait = max(1, (int) config('noc.icmp.timeout', 2));

        try {
            $result = Process::run([
                $binary, '-c', '1', '-W', (string) $wait, $host,
            ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'rtt_ms' => null, 'error' => 'binary ping tidak tersedia: '.$e->getMessage()];
        }

        $output = $result->output().$result->errorOutput();

        if (! $result->successful()) {
            return ['ok' => false, 'rtt_ms' => null, 'error' => $this->humanError($output)];
        }

        return ['ok' => true, 'rtt_ms' => $this->extractRtt($output), 'error' => null];
    }

    /**
     * Ambil `time=1.23 ms` dari output ping (iputils & busybox).
     */
    protected function extractRtt(string $output): ?float
    {
        if (preg_match('/time[=<]\s*([\d.]+)\s*ms/i', $output, $m) === 1) {
            return (float) $m[1];
        }

        return null;
    }

    protected function humanError(string $output): string
    {
        $line = trim(preg_replace('/\s+/', ' ', $output) ?? '');
        $line = mb_substr($line, 0, 200);

        if (stripos($line, 'network is unreachable') !== false) {
            return 'jaringan tidak terjangkau';
        }
        if (stripos($line, 'host unreachable') !== false) {
            return 'host tidak terjangkau';
        }
        if (stripos($line, 'timeout') !== false || stripos($line, '100% packet loss') !== false) {
            return 'timeout (tidak ada balasan)';
        }
        if (stripos($line, 'name or service not known') !== false || stripos($line, 'unknown host') !== false) {
            return 'nama host tidak dikenal';
        }

        return $line !== '' ? $line : 'ping gagal';
    }
}
