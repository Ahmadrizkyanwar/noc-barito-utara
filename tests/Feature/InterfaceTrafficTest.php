<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\InterfaceMetric;
use App\Models\User;
use App\Services\Network\MonitorPoller;
use App\Services\Network\PingProbe;
use App\Services\Network\RouterOsProbe;
use App\Services\Network\SnmpProbe;
use App\Services\Telegram\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterfaceTrafficTest extends TestCase
{
    use RefreshDatabase;

    /**
     * SnmpProbe palsu: counter NAIK tiap poll → delta pasti terhitung.
     */
    protected function fakeSnmp(): SnmpProbe
    {
        return new class extends SnmpProbe
        {
            public int $tick = 0;

            public function collect(Device $device): array
            {
                $this->tick++;
                $base = 1_000_000 * $this->tick;

                return [
                    'ok' => true,
                    'cpu' => 12,
                    'uptime_sec' => 3600,
                    'board_name' => 'Router Uji',
                    'rx_bytes' => $base + 50000,
                    'tx_bytes' => $base + 20000,
                    'interfaces' => [
                        ['name' => 'ether1', 'oper_status' => 'up', 'speed' => 1000000000, 'rx_bytes' => $base + 40000, 'tx_bytes' => $base + 15000],
                        ['name' => 'ether2', 'oper_status' => 'down', 'speed' => null, 'rx_bytes' => $base + 10000, 'tx_bytes' => $base + 5000],
                    ],
                    'error' => null,
                ];
            }
        };
    }

    protected function poller(): MonitorPoller
    {
        app()->instance(SnmpProbe::class, $this->fakeSnmp());

        return new MonitorPoller(
            app(PingProbe::class),
            app(SnmpProbe::class),
            app(RouterOsProbe::class),
            app(Notifier::class),
        );
    }

    protected function makeDevice(array $overrides = []): Device
    {
        return Device::create(array_merge([
            'name' => 'Router Trafik',
            'host' => '192.0.2.10',
            'use_icmp' => false,
            'use_snmp' => true,
            'use_routeros' => false,
        ], $overrides));
    }

    // ── Poller ─────────────────────────────────────────────────────────────

    public function test_first_poll_records_counters_without_bps(): void
    {
        $device = $this->makeDevice();

        $this->poller()->pollDevice($device);

        $rows = $device->interfaceMetrics()->get();
        $this->assertCount(2, $rows);
        // fake tick=1 → base 1.000.000 + 40.000
        $this->assertSame(1_040_000, $rows->firstWhere('if_name', 'ether1')->rx_bytes);
        $this->assertNull($rows->firstWhere('if_name', 'ether1')->rx_bps); // sampel pertama

        // Aggregate pada sampel pertama juga belum ada delta
        $this->assertNull($device->metrics()->first()->rx_bps);
    }

    public function test_second_poll_computes_delta_per_interface_and_aggregate(): void
    {
        $device = $this->makeDevice();
        $poller = $this->poller();

        $poller->pollDevice($device);

        // Atur counter seolah poll sebelumnya 30 detik yang lalu (dt pasti > 0)
        cache()->put('noc.iftraffic.'.$device->id, [
            'ether1' => ['rx' => 0, 'tx' => 0, 'at' => microtime(true) - 30],
            'ether2' => ['rx' => 0, 'tx' => 0, 'at' => microtime(true) - 30],
        ], 3600);

        $poller->pollDevice($device);

        $e1 = $device->interfaceMetrics()->where('if_name', 'ether1')->orderByDesc('checked_at')->first();
        $this->assertNotNull($e1->rx_bps);
        $this->assertGreaterThan(0, $e1->rx_bps);
        $this->assertGreaterThan(0, $e1->tx_bps);

        // Aggregate device_metrics = SUM delta interface (ether1 + ether2)
        $metric = $device->metrics()->orderByDesc('checked_at')->first();
        $this->assertNotNull($metric->rx_bps);
        $this->assertEqualsWithDelta($e1->rx_bps + $device->interfaceMetrics()->where('if_name', 'ether2')->orderByDesc('checked_at')->first()->rx_bps, $metric->rx_bps, 1);
    }

    public function test_delta_resumes_from_db_when_cache_lost(): void
    {
        $device = $this->makeDevice();
        $poller = $this->poller();

        $poller->pollDevice($device);

        // Simulasikan cache hilang (container restart) → fallback DB
        cache()->forget('noc.iftraffic.'.$device->id);
        // Mundurkan counter di baris terakhir supaya delta dt > 0 dan positif
        $first = $device->interfaceMetrics()->orderByDesc('checked_at')->limit(2)->get();
        foreach ($first as $row) {
            InterfaceMetric::where('id', $row->id)->update([
                'rx_bytes' => 0,
                'tx_bytes' => 0,
                'checked_at' => now()->subSeconds(30),
            ]);
        }

        $poller->pollDevice($device);

        $e1 = $device->interfaceMetrics()->where('if_name', 'ether1')->orderByDesc('checked_at')->first();
        $this->assertNotNull($e1->rx_bps);
        $this->assertGreaterThan(0, $e1->rx_bps);
    }

    public function test_router_without_snmp_and_routeros_records_nothing(): void
    {
        $device = $this->makeDevice(['use_snmp' => false]);

        $this->poller()->pollDevice($device);

        $this->assertSame(0, $device->interfaceMetrics()->count());
    }

    // ── Halaman & endpoint ─────────────────────────────────────────────────

    protected function seedInterfaceData(Device $device): void
    {
        InterfaceMetric::create([
            'device_id' => $device->id,
            'checked_at' => now()->subMinutes(2),
            'if_name' => 'ether1',
            'oper_status' => 'up',
            'speed' => 1000000000,
            'rx_bps' => 1000000,
            'tx_bps' => 500000,
            'rx_bytes' => 1000,
            'tx_bytes' => 500,
        ]);
        InterfaceMetric::create([
            'device_id' => $device->id,
            'checked_at' => now(),
            'if_name' => 'ether1',
            'oper_status' => 'up',
            'speed' => 1000000000,
            'rx_bps' => 2000000,
            'tx_bps' => 250000,
            'rx_bytes' => 2000,
            'tx_bytes' => 1000,
        ]);
    }

    public function test_traffic_page_renders_infographic(): void
    {
        $admin = User::factory()->admin()->create();
        $device = $this->makeDevice();
        $this->seedInterfaceData($device);

        $this->actingAs($admin)
            ->get('/admin/jaringan/'.$device->id.'/trafik')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Trafik')
                ->where('device.id', $device->id)
                ->has('initial.interfaces', 1)
                ->where('initial.interfaces.0.name', 'ether1')
                ->where('initial.total.rx_bps', 2000000)   // float bulat → JSON int
                ->where('initial.interfaces.0.share', 100));
    }

    public function test_traffic_json_returns_history_and_spark(): void
    {
        $admin = User::factory()->admin()->create();
        $device = $this->makeDevice();
        $this->seedInterfaceData($device);

        $response = $this->actingAs($admin)
            ->getJson('/admin/jaringan/'.$device->id.'/interfaces?hours=1')
            ->assertOk()
            ->assertJsonStructure([
                'interfaces' => [['name', 'oper_status', 'speed', 'rx_bps', 'tx_bps', 'share', 'util']],
                'total' => ['rx_bps', 'tx_bps', 'peak_rx_bps', 'peak_tx_bps'],
                'history' => ['labels', 'series', 'bucket_seconds'],
                'spark',
                'generated_at',
            ]);

        $json = $response->json();
        $this->assertSame('ether1', $json['interfaces'][0]['name']);
        $this->assertEquals(2000000, $json['total']['rx_bps']);
        $this->assertEquals(100, $json['interfaces'][0]['share']);
        $this->assertEquals(0.2, $json['interfaces'][0]['util']); // 2 Mbps / 1 Gbps
        $this->assertNotEmpty($json['spark']['ether1']);
        $this->assertNotEmpty($json['history']['labels']);
    }

    public function test_traffic_page_without_data_shows_empty_state(): void
    {
        $admin = User::factory()->admin()->create();
        $device = $this->makeDevice(['use_snmp' => false]);

        $this->actingAs($admin)
            ->get('/admin/jaringan/'.$device->id.'/trafik')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Trafik')
                ->has('initial.interfaces', 0));
    }

    public function test_user_role_cannot_access_traffic(): void
    {
        $user = User::factory()->create();
        $device = $this->makeDevice();

        $this->actingAs($user)->get('/admin/jaringan/'.$device->id.'/trafik')->assertForbidden();
        $this->actingAs($user)->getJson('/admin/jaringan/'.$device->id.'/interfaces')->assertForbidden();
    }

    public function test_guest_cannot_access_traffic(): void
    {
        $device = $this->makeDevice();

        $this->get('/admin/jaringan/'.$device->id.'/trafik')->assertRedirect(route('login'));
    }
}
