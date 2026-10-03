<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    device: { type: Object, required: true },
    metrics: { type: Array, default: () => [] },
});

const hours = ref(24);
const loading = ref(false);
const chartEl = ref(null);
let chartInstance = null;

const labels = ref([]);
const rtt = ref([]);
const cpu = ref([]);
const rx = ref([]);
const tx = ref([]);

function fromProps() {
    labels.value = props.metrics.map((m) => new Date(m.checked_at).toLocaleString('id-ID', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }));
    rtt.value = props.metrics.map((m) => m.icmp_rtt_ms);
    cpu.value = props.metrics.map((m) => m.cpu);
    rx.value = props.metrics.map((m) => m.rx_bps);
    tx.value = props.metrics.map((m) => m.tx_bps);
}

async function fetchMetrics() {
    loading.value = true;
    try {
        const res = await fetch(`${route('admin.devices.metrics', props.device.id)}?hours=${hours.value}`, {
            headers: { Accept: 'application/json' },
        });
        if (res.ok) {
            const data = await res.json();
            labels.value = data.labels;
            rtt.value = data.rtt;
            cpu.value = data.cpu;
            rx.value = data.rx;
            tx.value = data.tx;
        }
    } finally {
        loading.value = false;
    }
}

async function draw() {
    const { Chart, registerables } = await import('chart.js');
    Chart.register(...registerables);
    if (chartInstance) chartInstance.destroy();

    if (!chartEl.value) return;

    chartInstance = new Chart(chartEl.value, {
        type: 'line',
        data: {
            labels: labels.value,
            datasets: [
                { label: 'CPU (%)', data: cpu.value, borderColor: '#2563eb', tension: 0.3, yAxisID: 'y', spanGaps: true },
                { label: 'RTT (ms)', data: rtt.value, borderColor: '#059669', tension: 0.3, yAxisID: 'y1', spanGaps: true },
                { label: 'RX (bps)', data: rx.value, borderColor: '#7c3aed', tension: 0.3, yAxisID: 'y2', spanGaps: true },
                { label: 'TX (bps)', data: tx.value, borderColor: '#db2777', tension: 0.3, yAxisID: 'y2', spanGaps: true },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { position: 'left', min: 0, max: 100, title: { display: true, text: 'CPU %' } },
                y1: { position: 'right', grid: { drawOnChartArea: false }, min: 0, title: { display: true, text: 'ms' } },
                y2: { display: false },
            },
        },
    });
}

watch(hours, fetchMetrics);
watch([labels, rtt, cpu, rx, tx], () => draw());

onMounted(() => {
    fromProps();
    draw();
});
onUnmounted(() => chartInstance?.destroy());

function checkNow() {
    router.post(route('admin.devices.check', props.device.id), {}, { preserveScroll: true });
}

function destroy() {
    if (!confirm(`Hapus perangkat "${props.device.name}"?`)) return;
    router.delete(route('admin.devices.destroy', props.device.id), {
        onSuccess: () => router.get(route('admin.devices.index')),
    });
}

const statusText = { up: 'ONLINE', down: 'DOWN', unknown: 'BELUM DICEK' };

function fmtUptime(sec) {
    const d = Math.floor(sec / 86400);
    const h = Math.floor((sec % 86400) / 3600);
    const m = Math.floor((sec % 3600) / 60);
    if (d > 0) return `${d}h ${h}j`;
    if (h > 0) return `${h}j ${m}m`;
    return `${m}m`;
}

function fmtBps(v) {
    if (v === null || v === undefined) return '—';
    if (v >= 1e9) return (v / 1e9).toFixed(1) + ' Gbps';
    if (v >= 1e6) return (v / 1e6).toFixed(1) + ' Mbps';
    if (v >= 1e3) return (v / 1e3).toFixed(1) + ' kbps';
    return Math.round(v) + ' bps';
}
</script>

<template>
    <AdminLayout>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <Link :href="route('admin.devices.index')" class="text-sm font-semibold text-brand-700 hover:underline">← Jaringan</Link>
                <h1 class="mt-1 text-xl font-bold">{{ device.name }}</h1>
                <p class="text-sm text-slate-500">{{ device.host }} · {{ device.type }}<span v-if="device.location"> · {{ device.location }}</span></p>
            </div>
            <div class="flex gap-2">
                <Link
                    :href="route('admin.devices.traffic', device.id)"
                    class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-bold text-white hover:bg-brand-800"
                >
                    Infografik Trafik
                </Link>
                <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-100" @click="checkNow">
                    Cek Sekarang
                </button>
                <button class="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50" @click="destroy">
                    Hapus
                </button>
            </div>
        </div>

        <!-- Kartu status -->
        <div class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</p>
                <p
                    class="mt-1 text-2xl font-black"
                    :class="device.status === 'up' ? 'text-emerald-600' : device.status === 'down' ? 'text-red-600' : 'text-slate-400'"
                >
                    {{ statusText[device.status] ?? device.status }}
                </p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">CPU</p>
                <p class="mt-1 text-2xl font-black">{{ device.last_cpu !== null ? device.last_cpu + '%' : '—' }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">RTT</p>
                <p class="mt-1 text-2xl font-black">{{ device.last_rtt_ms !== null ? device.last_rtt_ms + ' ms' : '—' }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Uptime</p>
                <p class="mt-1 text-2xl font-black">{{ device.last_uptime_sec !== null ? fmtUptime(device.last_uptime_sec) : '—' }}</p>
            </div>
        </div>

        <!-- Grafik -->
        <div class="mt-6 rounded-xl border border-slate-200 bg-white p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-bold">Grafik Metrik</h2>
                <div class="flex gap-1">
                    <button
                        v-for="h in [1, 6, 24, 168]"
                        :key="h"
                        class="rounded px-3 py-1 text-xs font-bold"
                        :class="hours === h ? 'bg-brand-700 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        @click="hours = h"
                    >
                        {{ h < 24 ? h + 'j' : h === 24 ? '24j' : '7h' }}
                    </button>
                </div>
            </div>
            <div class="mt-3 h-72">
                <canvas ref="chartEl"></canvas>
                <p v-if="loading" class="mt-2 text-xs text-slate-400">memuat…</p>
            </div>
        </div>

        <!-- Tabel metrik terakhir -->
        <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Waktu</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">ICMP</th>
                        <th class="px-4 py-3">SNMP</th>
                        <th class="px-4 py-3">RouterOS</th>
                        <th class="px-4 py-3 text-right">CPU</th>
                        <th class="px-4 py-3 text-right">RTT</th>
                        <th class="px-4 py-3 text-right">RX/TX</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="m in [...metrics].reverse().slice(0, 50)" :key="m.id" class="hover:bg-slate-50">
                        <td class="px-4 py-2 text-xs text-slate-500">{{ new Date(m.checked_at).toLocaleString('id-ID') }}</td>
                        <td class="px-4 py-2">
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-bold"
                                :class="m.status === 'up' ? 'bg-emerald-100 text-emerald-700' : m.status === 'down' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-500'"
                            >
                                {{ m.status }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-xs">
                            <span v-if="m.icmp_ok" class="text-emerald-600">OK</span>
                            <span v-else-if="m.icmp_ok === false" class="text-red-600" :title="m.icmp_error">GAGAL</span>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-4 py-2 text-xs">
                            <span v-if="m.snmp_ok" class="text-emerald-600">OK</span>
                            <span v-else-if="m.snmp_ok === false" class="text-red-600" :title="m.snmp_error">GAGAL</span>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-4 py-2 text-xs">
                            <span v-if="m.routeros_ok" class="text-emerald-600">OK</span>
                            <span v-else-if="m.routeros_ok === false" class="text-red-600" :title="m.routeros_error">GAGAL</span>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ m.cpu !== null ? m.cpu + '%' : '—' }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ m.icmp_rtt_ms !== null ? m.icmp_rtt_ms + ' ms' : '—' }}</td>
                        <td class="px-4 py-2 text-right tabular-nums text-xs">
                            <template v-if="m.rx_bps !== null">
                                {{ fmtBps(m.rx_bps) }} / {{ fmtBps(m.tx_bps) }}
                            </template>
                            <template v-else>—</template>
                        </td>
                    </tr>
                    <tr v-if="metrics.length === 0">
                        <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">Belum ada data metrics.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
