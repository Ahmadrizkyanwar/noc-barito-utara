<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\TelegramWebhook;
use App\Models\User;
use App\Services\Network\MonitorPoller;
use App\Services\Network\PingProbe;
use App\Services\Network\RouterOsProbe;
use App\Services\Network\SnmpProbe;
use App\Services\Telegram\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DevicesTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function makeDevice(array $overrides = []): Device
    {
        return Device::create(array_merge([
            'name' => 'Router Uji',
            'host' => '192.0.2.10',
            'use_icmp' => true,
            'use_snmp' => false,
            'use_routeros' => false,
        ], $overrides));
    }

    public function test_admin_can_list_devices(): void
    {
        $this->makeDevice();

        $this->actingAs($this->admin())
            ->get('/admin/jaringan')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Jaringan')->has('devices', 1));
    }

    public function test_admin_can_create_device(): void
    {
        $this->actingAs($this->admin())->post('/admin/jaringan', [
            'name' => 'RT RW Net',
            'host' => '10.10.10.1',
            'type' => 'router',
            'use_icmp' => true,
            'use_snmp' => true,
            'snmp_version' => '2c',
            'snmp_community' => 'public',
            'snmp_port' => 161,
        ])->assertRedirect();

        $this->assertDatabaseHas('devices', ['name' => 'RT RW Net', 'use_snmp' => true]);
    }

    public function test_admin_can_create_device_with_multiple_hosts(): void
    {
        $this->actingAs($this->admin())->post('/admin/jaringan', [
            'name' => 'Router 2 IP',
            'hosts' => ['10.0.0.1', '10.0.0.2', '10.0.0.2', ' '],
            'type' => 'router',
        ])->assertRedirect();

        $device = Device::where('name', 'Router 2 IP')->firstOrFail();

        // IP utama = elemen pertama; duplikat & kosong dibuang
        $this->assertSame('10.0.0.1', $device->host);
        $this->assertSame(['10.0.0.1', '10.0.0.2'], $device->hosts);
        $this->assertSame(['10.0.0.1', '10.0.0.2'], $device->allHosts());
    }

    public function test_legacy_host_only_payload_backfills_hosts(): void
    {
        $this->actingAs($this->admin())->post('/admin/jaringan', [
            'name' => 'Perangkat Lama',
            'host' => '192.0.2.50',
            'type' => 'router',
        ])->assertRedirect();

        $device = Device::where('name', 'Perangkat Lama')->firstOrFail();

        $this->assertSame(['192.0.2.50'], $device->hosts);
        $this->assertSame(['192.0.2.50'], $device->allHosts());
    }

    public function test_device_is_up_when_alternate_host_responds(): void
    {
        $this->fakeIcmp();

        // IP utama mati — IP alternatif (127.0.0.1 pada fake) merespons
        $device = $this->makeDevice([
            'host' => '192.0.2.200',
            'hosts' => ['192.0.2.200', '127.0.0.1'],
        ]);

        $status = $this->poller()->pollDevice($device);

        $this->assertSame('up', $status);

        $metric = $device->metrics()->first();
        $this->assertTrue($metric->icmp_ok);
        $this->assertEqualsWithDelta(1.0, (float) $metric->icmp_rtt_ms, 0.01);
        $this->assertNull($metric->icmp_error);
    }

    public function test_device_stays_down_when_all_hosts_fail(): void
    {
        $this->fakeIcmp();

        $device = $this->makeDevice([
            'host' => '192.0.2.204',
            'hosts' => ['192.0.2.204', '192.0.2.205'],
        ]);

        $status = $this->poller()->pollDevice($device);

        $this->assertSame('down', $status);

        $metric = $device->metrics()->first();
        $this->assertFalse($metric->icmp_ok);
        $this->assertSame('timeout (tidak ada balasan)', $metric->icmp_error);
    }

    public function test_device_requires_name_and_host(): void
    {
        $this->actingAs($this->admin())
            ->from('/admin/jaringan')
            ->post('/admin/jaringan', ['type' => 'router'])
            ->assertSessionHasErrors(['name', 'host']);
    }

    public function test_invalid_device_type_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->from('/admin/jaringan')
            ->post('/admin/jaringan', ['name' => 'X', 'host' => '1.2.3.4', 'type' => 'kucing'])
            ->assertSessionHasErrors('type');
    }

    public function test_admin_can_update_device(): void
    {
        $device = $this->makeDevice();

        $this->actingAs($this->admin())
            ->put('/admin/jaringan/'.$device->id, [
                'name' => 'Nama Baru',
                'host' => '192.0.2.10',
                'type' => 'router',
                'use_icmp' => true,
            ])
            ->assertRedirect();

        $this->assertSame('Nama Baru', $device->fresh()->name);
    }

    public function test_admin_can_delete_device(): void
    {
        $device = $this->makeDevice();

        $this->actingAs($this->admin())
            ->deleteJson('/admin/jaringan/'.$device->id)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('devices', ['id' => $device->id]);
    }

    public function test_manual_check_updates_status(): void
    {
        $this->fakeIcmp();
        $device = $this->makeDevice(['host' => '127.0.0.1']);

        $this->actingAs($this->admin())
            ->postJson('/admin/jaringan/'.$device->id.'/check')
            ->assertOk()
            ->assertJsonPath('status', 'up');

        $this->assertSame('up', $device->fresh()->status);
        $this->assertSame(1, $device->metrics()->count());
    }

    public function test_unreachable_device_marks_down_without_exception(): void
    {
        $this->fakeIcmp();
        $device = $this->makeDevice(['host' => '192.0.2.200']);

        $this->actingAs($this->admin())
            ->postJson('/admin/jaringan/'.$device->id.'/check')
            ->assertOk()
            ->assertJsonPath('status', 'down');

        $metric = $device->metrics()->first();
        $this->assertFalse($metric->icmp_ok);
        $this->assertNotNull($metric->icmp_error);
    }

    public function test_device_with_no_probe_returns_unknown(): void
    {
        $device = $this->makeDevice([
            'use_icmp' => false,
            'use_snmp' => false,
            'use_routeros' => false,
        ]);

        $poller = $this->poller();
        $status = $poller->pollDevice($device);

        $this->assertSame('unknown', $status);
    }

    public function test_poll_all_handles_empty_and_mixed_devices(): void
    {
        $this->fakeIcmp();
        $this->makeDevice(['host' => '127.0.0.1']);
        $this->makeDevice(['host' => '192.0.2.201']);

        $stats = $this->poller()->pollAll();

        $this->assertSame(2, $stats['polled']);
        $this->assertSame(1, $stats['up']);
        $this->assertSame(1, $stats['down']);
    }

    public function test_status_transition_sends_telegram_alert(): void
    {
        TelegramWebhook::firstOrCreate(
            ['id' => TelegramWebhook::ID_JARINGAN],
            ['label' => 'Jaringan']
        );
        TelegramWebhook::find(TelegramWebhook::ID_JARINGAN)->update([
            'enabled' => true,
            'bot_token' => '42:tok',
            'chat_id' => '-1009',
        ]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        // status awal "up" → poll ke host mati = transisi DOWN → notifikasi
        $device = $this->makeDevice(['host' => '192.0.2.202', 'status' => 'up']);

        $this->poller()->pollDevice($device);

        Http::assertSent(
            fn ($r) => str_contains($r->url(), 'api.telegram.org/bot42:tok/sendMessage')
                && str_contains((string) $r['text'], 'DOWN')
        );
    }

    public function test_first_poll_seeds_status_without_notification(): void
    {
        TelegramWebhook::firstOrCreate(
            ['id' => TelegramWebhook::ID_JARINGAN],
            ['label' => 'Jaringan', 'enabled' => true, 'bot_token' => '42:tok', 'chat_id' => '-1']
        );
        Http::fake();

        $device = $this->makeDevice(['host' => '192.0.2.203']); // status awal 'unknown'
        $this->poller()->pollDevice($device);

        Http::assertNothingSent();
    }

    /** Form UI (Inertia) → wajib redirect, bukan JSON. */
    public function test_device_destroy_redirects_for_inertia_style_request(): void
    {
        $device = $this->makeDevice();

        $this->actingAs($this->admin())
            ->delete('/admin/jaringan/'.$device->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('devices', ['id' => $device->id]);
    }

    protected function poller(): MonitorPoller
    {
        // Resolve dari container → fakeIcmp() (app()->instance) ikut terpakai.
        return new MonitorPoller(
            app(PingProbe::class),
            app(SnmpProbe::class),
            app(RouterOsProbe::class),
            app(Notifier::class),
        );
    }

    /**
     * Bind PingProbe deterministik ke container (image test tidak punya
     * binary `ping` + cap_net_raw → probe ICMP nyata tidak bisa diandalkan).
     *
     * Aturan fake: 127.0.0.1 → OK (RTT 1ms), selain itu → timeout.
     */
    protected function fakeIcmp(): void
    {
        $fake = new class extends PingProbe
        {
            public function ping(string $host): array
            {
                if ($host === '127.0.0.1') {
                    return ['ok' => true, 'rtt_ms' => 1.0, 'error' => null];
                }

                return ['ok' => false, 'rtt_ms' => null, 'error' => 'timeout (tidak ada balasan)'];
            }
        };

        app()->instance(PingProbe::class, $fake);
    }
}
