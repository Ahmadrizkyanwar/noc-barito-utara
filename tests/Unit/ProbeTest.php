<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Services\Network\PingProbe;
use App\Services\Network\RouterOsProbe;
use App\Services\Network\SnmpProbe;
use Tests\TestCase;

/**
 * Tes unit probe — tanpa jaringan; app di-boot untuk resolusi Model.
 */
class ProbeTest extends TestCase
{
    // ── ICMP ───────────────────────────────────────────────────────────────

    public function test_ping_fails_closed_on_empty_host(): void
    {
        $result = (new PingProbe)->ping('');

        $this->assertFalse($result['ok']);
        $this->assertNull($result['rtt_ms']);
        $this->assertNotNull($result['error']);
    }

    public function test_rtt_is_extracted_from_iputils_output(): void
    {
        $probe = new class extends PingProbe
        {
            public function expose(string $out): ?float
            {
                return $this->extractRtt($out);
            }
        };

        $this->assertSame(1.23, $probe->expose('64 bytes from 127.0.0.1: icmp_seq=1 ttl=64 time=1.23 ms'));
        $this->assertSame(0.04, $probe->expose('64 bytes from 127.0.0.1: icmp_seq=1 ttl=64 time=0.04 ms'));
        $this->assertNull($probe->expose('ping: unknown host'));
    }

    public function test_human_error_maps_common_failures(): void
    {
        $probe = new class extends PingProbe
        {
            public function expose(string $out): string
            {
                return $this->humanError($out);
            }
        };

        $this->assertStringContainsString('timeout', $probe->expose('1 packets transmitted, 0 received, 100% packet loss'));
        $this->assertStringContainsString('tidak dikenal', $probe->expose('ping: foo.bar: Name or service not known'));
        $this->assertStringContainsString('tidak terjangkau', $probe->expose('connect: Network is unreachable'));
    }

    // ── RouterOS ───────────────────────────────────────────────────────────

    public function test_routeros_uptime_parsing(): void
    {
        $probe = new RouterOsProbe;

        $this->assertSame(788645, $probe->parseUptime('1w2d3h4m5s'));
        $this->assertSame(45, $probe->parseUptime('45s'));
        $this->assertSame(3661, $probe->parseUptime('1h1m1s'));
        $this->assertNull($probe->parseUptime('bukan-uptime'));
    }

    // ── SNMP ───────────────────────────────────────────────────────────────

    public function test_traffic_delta_computes_bps(): void
    {
        $probe = new SnmpProbe;
        $before = ['rx' => 1000, 'tx' => 2000, 'at' => 1000.0];

        // Δ 10 dtk: +8000 byte rx → 8000/10*8 = 6400 bps
        $delta = $probe->deltaBps($before, 9000, 4000, 1010.0);

        $this->assertSame(6400.0, $delta['rx_bps']);
        $this->assertSame(1600.0, $delta['tx_bps']);
    }

    public function test_traffic_delta_handles_first_sample_and_reset(): void
    {
        $probe = new SnmpProbe;

        // Sample pertama (belum ada counter sebelumnya) → null
        $this->assertNull($probe->deltaBps(null, 100, 100, 2000.0)['rx_bps']);

        // Counter wrap/reset (negatif) → null, bukan angka negatif
        $delta = $probe->deltaBps(['rx' => 5000, 'tx' => 5000, 'at' => 1000.0], 100, 100, 1010.0);
        $this->assertNull($delta['rx_bps']);
        $this->assertNull($delta['tx_bps']);
    }

    public function test_snmp_values_are_normalized_from_type_prefixed_raw(): void
    {
        $probe = new class extends SnmpProbe
        {
            public function expose(?string $v): ?string
            {
                return $this->normalize($v);
            }
        };

        // Ekstensi PHP snmp menempelkan prefix tipe + kutip
        $this->assertSame('ether1-astinet', $probe->expose('STRING: "ether1-astinet"'));
        $this->assertSame('1000000000', $probe->expose('Gauge32: 1000000000'));
        $this->assertSame('4711', $probe->expose('Counter32: 4711'));
        $this->assertSame('1', $probe->expose('INTEGER: 1'));
        $this->assertSame('123456', $probe->expose('Timeticks: (123456) 0:20:34.56'));
        // Nilai polos tetap dipertahankan
        $this->assertSame('55', $probe->expose('55'));
        $this->assertNull($probe->expose(null));
        // Setelah normalisasi harus lolos is_numeric (dasar ifSpeed/ifInOctets)
        $this->assertTrue(is_numeric($probe->expose('Gauge32: 1000000000')));
    }

    public function test_interface_walk_columns_are_matched_by_ifindex_not_raw_key(): void
    {
        // Key walk per kolom = OID lengkap yang BERBEDA (`…1.2.2` vs `…1.10.2`)
        // → wajib dipetakan via ifIndex; kalau di-cross-reference apa adanya,
        // counter terbaca 0 (bug produksi Kominfo).
        $probe = new class extends SnmpProbe
        {
            protected function snmpWalk(array $session, string $oid): ?array
            {
                return match ($oid) {
                    '1.3.6.1.2.1.2.2.1.2' => [
                        'iso.3.6.1.2.1.2.2.1.2.2' => 'STRING: "ether1"',
                        'iso.3.6.1.2.1.2.2.1.2.3' => 'STRING: "sfp1"',
                    ],
                    '1.3.6.1.2.1.2.2.1.8' => [
                        'iso.3.6.1.2.1.2.2.1.8.2' => 'INTEGER: 1',
                        'iso.3.6.1.2.1.2.2.1.8.3' => 'INTEGER: 2',
                    ],
                    '1.3.6.1.2.1.2.2.1.5' => [
                        'iso.3.6.1.2.1.2.2.1.5.2' => 'Gauge32: 1000000000',
                        'iso.3.6.1.2.1.2.2.1.5.3' => 'Gauge32: 10000000000',
                    ],
                    '1.3.6.1.2.1.2.2.1.10' => [
                        'iso.3.6.1.2.1.2.2.1.10.2' => 'Counter32: 5000',
                        'iso.3.6.1.2.1.2.2.1.10.3' => 'Counter32: 9000',
                    ],
                    '1.3.6.1.2.1.2.2.1.16' => [
                        'iso.3.6.1.2.1.2.2.1.16.2' => 'Counter32: 3000',
                        'iso.3.6.1.2.1.2.2.1.16.3' => 'Counter32: 7000',
                    ],
                    default => null,
                };
            }

            public function exposeRead(array $session): ?array
            {
                return $this->readInterfaces($session);
            }
        };

        $rows = $probe->exposeRead([]);

        $this->assertNotNull($rows);
        $this->assertCount(2, $rows);

        $e1 = $rows[0];
        $this->assertSame('ether1', $e1['name']);
        $this->assertSame('up', $e1['oper_status']);
        $this->assertSame(1000000000, $e1['speed']);
        $this->assertSame(5000, $e1['rx_bytes']);
        $this->assertSame(3000, $e1['tx_bytes']);

        $s1 = $rows[1];
        $this->assertSame('sfp1', $s1['name']);
        $this->assertSame('down', $s1['oper_status']);
        $this->assertSame(9000, $s1['rx_bytes']);
        $this->assertSame(7000, $s1['tx_bytes']);
    }

    public function test_snmp_fails_closed_without_extension(): void
    {
        if (extension_loaded('snmp')) {
            $this->markTestSkipped('ekstensi snmp tersedia di runtime ini — jalur fail-closed diuji di image tanpa snmp');
        }

        $result = (new SnmpProbe)->collect(Device::make(['name' => 'x', 'host' => '192.0.2.1']));

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('snmp', $result['error']);
    }
}
