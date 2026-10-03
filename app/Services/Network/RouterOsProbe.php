<?php

namespace App\Services\Network;

use App\Models\Device;
use RouterOS\Client;
use RouterOS\Query;

/**
 * Probe RouterOS API — paket `evilfreelancer/routeros-api-php` (ext-sockets).
 *
 * Perintah:
 *   - /system/resource/print → cpu-load, uptime, board-name
 *   - /interface/print (.proplist) → trafik per interface (rx-byte/tx-byte)
 *
 * Gagal-tertutup: tanpa ext-sockets / auth gagal / timeout → {ok:false},
 * TIDAK PERNAH melempar exception ke poller. Kegagalan query interface hanya
 * membuat `interfaces = null` — data resource tetap dikembalikan.
 */
class RouterOsProbe
{
    /**
     * @return array{
     *     ok: bool, cpu: int|null, uptime_sec: int|null, board_name: string|null,
     *     interfaces: list<array{name: string, oper_status: string|null, speed: int|null, rx_bytes: int, tx_bytes: int}>|null,
     *     error: string|null
     * }
     */
    public function collect(Device $device): array
    {
        $fail = fn (string $error): array => [
            'ok' => false,
            'cpu' => null,
            'uptime_sec' => null,
            'board_name' => null,
            'interfaces' => null,
            'error' => $error,
        ];

        if (! extension_loaded('sockets')) {
            return $fail('ekstensi PHP sockets tidak tersedia');
        }

        if (! class_exists(Client::class)) {
            return $fail('paket routeros-api-php tidak terpasang');
        }

        try {
            $client = $this->connect($device);
        } catch (\Throwable $e) {
            return $fail($this->humanError($e));
        }

        try {
            $rows = $client->query('/system/resource/print')->read();
        } catch (\Throwable $e) {
            return $fail($this->humanError($e));
        }

        if (! is_array($rows) || $rows === []) {
            return $fail('/system/resource/print tidak mengembalikan data');
        }

        $res = $rows[0];

        return [
            'ok' => true,
            'cpu' => isset($res['cpu-load']) && is_numeric($res['cpu-load'])
                ? max(0, min(100, (int) $res['cpu-load']))
                : null,
            'uptime_sec' => isset($res['uptime']) ? $this->parseUptime((string) $res['uptime']) : null,
            'board_name' => isset($res['board-name']) ? mb_substr((string) $res['board-name'], 0, 100) : null,
            'interfaces' => $this->readInterfaces($client),
            'error' => null,
        ];
    }

    /**
     * /interface/print dengan proplist minim → counter trafik per interface.
     *
     * Gagal-senyap: field `rx-byte`/`tx-byte` tidak ada (routerOS lama) atau
     * query error → null (resource tetap ok).
     *
     * @return list<array{name: string, oper_status: string|null, speed: int|null, rx_bytes: int, tx_bytes: int}>|null
     */
    protected function readInterfaces(Client $client): ?array
    {
        try {
            $query = (new Query('/interface/print'))
                ->equal('.proplist', 'name,type,running,disabled,rx-byte,tx-byte');
            $rows = $client->query($query)->read();
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($rows) || $rows === []) {
            return null;
        }

        $out = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '' || ! isset($row['rx-byte'], $row['tx-byte'])) {
                continue;
            }

            $running = $row['running'] ?? null;
            $disabled = $row['disabled'] ?? null;

            $oper = null;
            if ($disabled === true || $disabled === 'true') {
                $oper = 'down';
            } elseif ($running === true || $running === 'true' || $running === 'false' || $running === false) {
                $oper = $running === true || $running === 'true' ? 'up' : 'down';
            }

            $out[] = [
                'name' => mb_substr($name, 0, 64),
                'oper_status' => $oper,
                'speed' => null, // /interface/print tidak membawa kecepatan link
                'rx_bytes' => is_numeric($row['rx-byte']) ? (int) (float) $row['rx-byte'] : 0,
                'tx_bytes' => is_numeric($row['tx-byte']) ? (int) (float) $row['tx-byte'] : 0,
            ];
        }

        return $out === [] ? null : $out;
    }

    /**
     * Buat klien — SEAM untuk tes unit (override tanpa jaringan).
     *
     * @throws \Throwable
     */
    protected function connect(Device $device): Client
    {
        $timeout = max(1, $device->routeros_timeout ?: 5);

        return new Client([
            'host' => $device->host,
            'user' => $device->routeros_user ?: 'admin',
            'pass' => $device->routeros_password,
            'port' => $device->routeros_port ?: 8728,
            'timeout' => $timeout,
            'socket_timeout' => $timeout,
        ]);
    }

    /**
     * RouterOS uptime `1w2d3h4m5s` → detik.
     */
    public function parseUptime(string $uptime): ?int
    {
        if (preg_match_all('/(\d+)([wdhms])/i', $uptime, $m, PREG_SET_ORDER) === 0) {
            return null;
        }

        $mult = ['w' => 604800, 'd' => 86400, 'h' => 3600, 'm' => 60, 's' => 1];
        $total = 0;

        foreach ($m as $part) {
            $total += (int) $part[1] * $mult[strtolower($part[2])];
        }

        return $total;
    }

    protected function humanError(\Throwable $e): string
    {
        $msg = mb_substr(trim($e->getMessage()), 0, 200);
        $lower = strtolower($msg);

        if (str_contains($lower, 'timeout') || str_contains($lower, 'timed out')) {
            return 'timeout: RouterOS API tidak merespons';
        }
        if (str_contains($lower, 'login') || str_contains($lower, 'password') || str_contains($lower, 'wrong')) {
            return 'autentikasi gagal (user/password salah)';
        }
        if (str_contains($lower, 'refused') || str_contains($lower, 'unreachable')) {
            return 'koneksi ditolak / host tidak terjangkau';
        }
        if (str_contains($lower, 'socket')) {
            return 'gagal membuka socket: '.$msg;
        }

        return $msg !== '' ? $msg : 'RouterOS API gagal';
    }
}
