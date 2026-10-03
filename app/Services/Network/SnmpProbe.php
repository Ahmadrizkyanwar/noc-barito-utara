<?php

namespace App\Services\Network;

use App\Models\Device;

/**
 * Probe SNMP — ekstensi PHP `snmp` (ext-snmp).
 *
 * OID:
 *   - sysUpTime / sysDescr                    (MIB-II)
 *   - mtxrCpuLoad / mtxrSystemName            (Mikrotik)
 *   - hrProcessorLoad                         (HOST-RESOURCES fallback CPU)
 *   - ifTable (ifDescr/ifOperStatus/ifSpeed/ifInOctets/ifOutOctets)
 *
 * Kontrak `interfaces`: list mentah per interface
 *   [{name, oper_status, speed, rx_bytes, tx_bytes}] — counter MENTAH (bukan bps).
 * Delta → bps dihitung di MonitorPoller (`deltaBps`) dari poll sebelumnya.
 *
 * Gagal-tertutup: ekstensi absen / timeout → {ok:false, error}, tanpa exception.
 */
class SnmpProbe
{
    protected ?string $lastError = null;

    /**
     * @return array{
     *     ok: bool, cpu: int|null, uptime_sec: int|null, board_name: string|null,
     *     rx_bytes: int|null, tx_bytes: int|null, interfaces: list<array<string, mixed>>|null,
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
            'rx_bytes' => null,
            'tx_bytes' => null,
            'interfaces' => null,
            'error' => $error,
        ];

        if (! extension_loaded('snmp')) {
            return $fail('ekstensi PHP snmp tidak tersedia');
        }

        $session = $this->session($device);

        // ── 1. Sistem: sysUpTime + sysDescr (wajib merespons = lulus) ──
        $sys = $this->snmpGet($session, [
            '1.3.6.1.2.1.1.3.0', // sysUpTime.0 (timeticks)
            '1.3.6.1.2.1.1.1.0', // sysDescr.0
        ]);

        if ($sys === null) {
            return $fail($this->lastError ?? 'SNMP tidak merespons');
        }

        $uptime = is_numeric($sys[0] ?? null) ? (int) round(((float) $sys[0]) / 100) : null;
        $board = $this->clean($sys[1] ?? null);

        // ── 2. CPU (Mikrotik → fallback HOST-RESOURCES) ──
        $cpuRaw = $this->snmpGetOne($session, '1.3.6.1.4.1.14988.1.1.2.1.7.0');
        $cpu = is_numeric($cpuRaw) ? $cpuRaw : $this->avgHrProcessorLoad($session);
        $cpu = is_numeric($cpu) ? max(0, min(100, (int) round((float) $cpu))) : null;

        // ── 3. Nama board Mikrotik (opsional, fallback sysDescr) ──
        if ($board === null) {
            $board = $this->clean($this->snmpGetOne($session, '1.3.6.1.4.1.14988.1.1.2.1.6.0'));
        }

        // ── 4. Trafik per interface (ifTable) ──
        $interfaces = $this->readInterfaces($session);

        // Aggregate = sum counter interface; bila ifTable gagal → walk sum lama.
        if ($interfaces !== null && $interfaces !== []) {
            $rxBytes = (int) array_sum(array_column($interfaces, 'rx_bytes'));
            $txBytes = (int) array_sum(array_column($interfaces, 'tx_bytes'));
        } else {
            [$rxBytes, $txBytes] = $this->readTrafficCounters($session);
            $interfaces = $interfaces ?: null;
        }

        return [
            'ok' => true,
            'cpu' => $cpu,
            'uptime_sec' => $uptime,
            'board_name' => $board,
            'rx_bytes' => $rxBytes,
            'tx_bytes' => $txBytes,
            'interfaces' => $interfaces ?: null,
            'error' => null,
        ];
    }

    /**
     * Hitung bps dari counter sebelumnya vs sekarang.
     *
     * @param  array{rx: int, tx: int, at: float}|null  $before  counter poll sebelumnya
     * @return array{rx_bps: float|null, tx_bps: float|null}
     */
    public function deltaBps(?array $before, int $rxNow, int $txNow, float $at): array
    {
        if ($before === null || $before['at'] <= 0 || ($at - $before['at']) <= 0) {
            return ['rx_bps' => null, 'tx_bps' => null];
        }

        $dt = $at - $before['at'];
        $drx = $rxNow - $before['rx'];
        $dtx = $txNow - $before['tx'];

        // Counter wrap (32-bit) / reset perangkat → tolak nilai negatif.
        return [
            'rx_bps' => $drx >= 0 ? round($drx / $dt * 8, 2) : null,
            'tx_bps' => $dtx >= 0 ? round($dtx / $dt * 8, 2) : null,
        ];
    }

    // ── Transport (SEAM untuk tes unit) ────────────────────────────────────

    /**
     * @param  array<string, mixed>  $session
     * @param  list<string>  $oids
     * @return list<string|null>|null null = GAGAL
     */
    protected function snmpGet(array $session, array $oids): ?array
    {
        $result = $this->callSnmp(function () use ($session, $oids) {
            if (($session['version'] ?? '2c') === '3') {
                $v3 = is_array($session['v3'] ?? null) ? $session['v3'] : [];

                return snmp3_get(
                    $session['host'],
                    (string) ($v3['user'] ?? ''),
                    $this->securityLevel($v3),
                    $this->authProtocol($v3),
                    (string) ($v3['authKey'] ?? ''),
                    $this->privProtocol($v3),
                    (string) ($v3['privKey'] ?? ''),
                    $oids,
                    (int) $session['timeout_us'],
                    (int) $session['retries'],
                );
            }

            return snmp2_get(
                $session['host'],
                (string) $session['community'],
                $oids,
                (int) $session['timeout_us'],
                (int) $session['retries'],
            );
        });

        if (is_string($result)) {
            return [$this->normalize($result)];
        }
        if (! is_array($result)) {
            return null;
        }

        return array_values(array_map(
            fn ($v) => is_scalar($v) ? $this->normalize((string) $v) : null,
            $result
        ));
    }

    /**
     * @param  array<string, mixed>  $session
     */
    protected function snmpGetOne(array $session, string $oid): ?string
    {
        $vals = $this->snmpGet($session, [$oid]);

        return $vals[0] ?? null;
    }

    /**
     * Rata-rata hrProcessorLoad — fallback CPU di luar perangkat Mikrotik.
     *
     * @param  array<string, mixed>  $session
     */
    protected function avgHrProcessorLoad(array $session): ?float
    {
        $walk = $this->snmpWalk($session, '1.3.6.1.2.1.25.3.3.1.2');
        if ($walk === null || $walk === []) {
            return null;
        }

        $vals = array_values(array_filter(
            array_map(fn ($v) => is_numeric($v) ? (float) $v : null, $walk),
            fn ($v) => $v !== null
        ));

        return $vals === [] ? null : array_sum($vals) / count($vals);
    }

    /**
     * Baca ifTable → daftar interface dengan counter mentah.
     *
     * OID (MIB-II): ifDescr(.2), ifOperStatus(.8), ifSpeed(.5),
     * ifInOctets(.10), ifOutOctets(.16) — kolom 1.3.6.1.2.1.2.2.1.<n>.
     *
     * @param  array<string, mixed>  $session
     * @return list<array{name: string, oper_status: string|null, speed: int|null, rx_bytes: int, tx_bytes: int}>|null
     *                                                                                                                 null = walk gagal (poller jatuh ke fallback sum lama)
     */
    protected function readInterfaces(array $session): ?array
    {
        $columns = [
            'name' => '1.3.6.1.2.1.2.2.1.2',   // ifDescr
            'status' => '1.3.6.1.2.1.2.2.1.8', // ifOperStatus (1=up, 2=down)
            'speed' => '1.3.6.1.2.1.2.2.1.5',  // ifSpeed (bit/s)
            'rx' => '1.3.6.1.2.1.2.2.1.10',    // ifInOctets
            'tx' => '1.3.6.1.2.1.2.2.1.16',    // ifOutOctets
        ];

        $walks = [];
        foreach ($columns as $key => $oid) {
            $walk = $this->snmpWalk($session, $oid);
            if ($walk === null || $walk === []) {
                return null;
            }
            $walks[$key] = $walk;
        }

        // Key walk = OID LENGKAP per kolom (`…2.2.1.2.2` vs `…2.2.1.10.2`) →
        // tidak boleh di-cross-reference apa adanya. Dipetakan ke ifIndex
        // (angka TERAKHIR OID, mis. `.2` → 2) supaya antar kolom sinkron.
        $byIndex = [];
        foreach ($walks as $key => $walk) {
            foreach ($walk as $oidKey => $value) {
                $oidKey = (string) $oidKey;
                $pos = strrpos($oidKey, '.');
                $ifIndex = $pos !== false ? (int) substr($oidKey, $pos + 1) : (int) $oidKey;
                $byIndex[$key][$ifIndex] = $value;
            }
        }

        $out = [];
        foreach ($byIndex['name'] as $ifIndex => $name) {
            if (trim((string) $name) === '') {
                continue;
            }

            // normalize ulang (defensif — idempoten): siapa pun yang mereturn
            // nilai mentah `STRING: "ether1"` tetap bersih di sini.
            $name = $this->normalize((string) $name) ?? '';
            if ($name === '') {
                continue;
            }

            $statusRaw = $this->normalize($byIndex['status'][$ifIndex] ?? null);
            $speedRaw = $this->normalize($byIndex['speed'][$ifIndex] ?? null);
            $rxRaw = $this->normalize($byIndex['rx'][$ifIndex] ?? null);
            $txRaw = $this->normalize($byIndex['tx'][$ifIndex] ?? null);

            // ifOperStatus: 1=up(1), 2=down(2); selain itu → unknown
            $oper = null;
            if (is_numeric($statusRaw)) {
                $oper = (int) $statusRaw === 1 ? 'up' : ((int) $statusRaw === 2 ? 'down' : 'unknown');
            }

            $out[] = [
                'name' => mb_substr(trim((string) $name), 0, 64),
                'oper_status' => $oper,
                'speed' => is_numeric($speedRaw) && (float) $speedRaw > 0 ? (int) (float) $speedRaw : null,
                'rx_bytes' => is_numeric($rxRaw) ? (int) (float) $rxRaw : 0,
                'tx_bytes' => is_numeric($txRaw) ? (int) (float) $txRaw : 0,
            ];
        }

        return $out === [] ? null : $out;
    }

    /**
     * Sum ifInOctets + ifOutOctets (counter mentah, semua interface).
     *
     * @param  array<string, mixed>  $session
     * @return array{0: int, 1: int}
     */
    protected function readTrafficCounters(array $session): array
    {
        $in = $this->snmpWalk($session, '1.3.6.1.2.1.2.2.1.10');
        $out = $this->snmpWalk($session, '1.3.6.1.2.1.2.2.1.16');

        $sum = fn (?array $walk): int => $walk === null
            ? 0
            : (int) array_sum(array_map(
                fn ($v) => is_numeric($v) ? (float) $v : 0,
                $walk
            ));

        return [$sum($in), $sum($out)];
    }

    /**
     * @param  array<string, mixed>  $session
     * @return array<string, string>|null null = GAGAL
     */
    protected function snmpWalk(array $session, string $oid): ?array
    {
        $result = $this->callSnmp(function () use ($session, $oid) {
            if (($session['version'] ?? '2c') === '3') {
                $v3 = is_array($session['v3'] ?? null) ? $session['v3'] : [];

                return snmp3_real_walk(
                    $session['host'],
                    (string) ($v3['user'] ?? ''),
                    $this->securityLevel($v3),
                    $this->authProtocol($v3),
                    (string) ($v3['authKey'] ?? ''),
                    $this->privProtocol($v3),
                    (string) ($v3['privKey'] ?? ''),
                    $oid,
                    (int) $session['timeout_us'],
                    (int) $session['retries'],
                );
            }

            return snmp2_real_walk(
                $session['host'],
                (string) $session['community'],
                $oid,
                (int) $session['timeout_us'],
                (int) $session['retries'],
            );
        });

        if (! is_array($result)) {
            return null;
        }

        $out = [];
        foreach ($result as $k => $v) {
            $out[(string) $k] = is_scalar($v) ? ($this->normalize((string) $v) ?? '') : '';
        }

        return $out;
    }

    /**
     * Rapikan nilai mentah ekstensi SNMP PHP.
     *
     * Ekstensi mengembalikan nilai ber-prefix tipe:
     *   `STRING: "ether1"`, `Gauge32: 1000000`, `Counter32: 4711`,
     *   `Timeticks: (123456) 0:20:34.56`, `INTEGER: 1` →
     *   tanpa prefix, tanpa kutip mengelilingi; Timeticks → angka dalam ().
     *
     * Tanpa langkah ini ifDescr/ifSpeed/ifInOctets gagal `is_numeric()` /
     * tampil ber-prefix di UI (bug produksi: trafik 0 semua).
     */
    protected function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $v = trim($value);

        $v = (string) preg_replace(
            '/^(?:Hex-STRING|Hex-String|STRING|INTEGER|Gauge32|Counter32|Counter64|Unsigned32|Timeticks|OID|IpAddress|BITS|Float|Double|Opaque|NULL|True|False|Not-Arrival|No-Modification):\s*/i',
            '',
            $v
        );

        // Timeticks: "(123456) 0:20:34.56" → "123456"
        if (preg_match('/^\((\d+)\)/', $v, $m) === 1) {
            $v = $m[1];
        }

        $v = trim($v);

        // Buang kutip ganda yang mengelilingi seluruh nilai
        if (strlen($v) >= 2 && $v[0] === '"' && $v[strlen($v) - 1] === '"') {
            $v = substr($v, 1, -1);
        }

        return $v;
    }

    /**
     * Bungkus pemanggilan SNMP: timeout net-snmp ditulis sebagai WARNING
     * (bukan exception) → tangkap, kembalikan null.
     *
     * @param  callable(): mixed  $callback
     */
    protected function callSnmp(callable $callback): mixed
    {
        $this->lastError = null;
        $warning = null;

        set_error_handler(function (int $no, string $msg) use (&$warning): bool {
            $warning = $msg;

            return true;
        });

        try {
            $result = $callback();
        } catch (\Throwable $e) {
            $this->lastError = mb_substr($e->getMessage(), 0, 200);

            return null;
        } finally {
            restore_error_handler();
        }

        if ($result === false || $result === null) {
            $this->lastError = $warning !== null && trim($warning) !== ''
                ? mb_substr(trim($warning), 0, 200)
                : 'SNMP gagal';

            return null;
        }

        if ($warning !== null && stripos($warning, 'no response') !== false) {
            $this->lastError = 'timeout: tidak ada balasan SNMP';

            return null;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    protected function session(Device $device): array
    {
        $timeoutMs = max(200, (int) config('noc.snmp.timeout_ms', 3000));

        return [
            'host' => $device->host,
            'version' => $device->snmp_version ?: (string) config('noc.snmp.version', '2c'),
            'community' => $device->snmp_community ?: (string) config('noc.snmp.community', 'public'),
            'port' => $device->snmp_port ?: (int) config('noc.snmp.port', 161),
            'v3' => null,
            'timeout_us' => $timeoutMs * 1000, // PHP SNMP memakai mikrodetik
            'retries' => (int) config('noc.snmp.retries', 1),
        ];
    }

    protected function clean(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = preg_replace('/^RouterOS\s+/i', '', trim($value)) ?? trim($value);

        return mb_substr($value, 0, 100);
    }

    // ── SNMPv3 helpers ─────────────────────────────────────────────────────

    protected function securityLevel(array $v3): int
    {
        return match ($v3['securityLevel'] ?? 'noAuthNoPriv') {
            'authPriv' => 3,
            'authNoPriv' => 2,
            default => 1,
        };
    }

    protected function authProtocol(array $v3): int
    {
        return strtoupper((string) ($v3['authProtocol'] ?? 'SHA')) === 'MD5' ? 1 : 2;
    }

    protected function privProtocol(array $v3): int
    {
        return strtoupper((string) ($v3['privProtocol'] ?? 'AES')) === 'DES' ? 1 : 2;
    }
}
