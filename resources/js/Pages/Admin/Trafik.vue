<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    device: { type: Object, required: true },
    initial: { type: Object, required: true },
    hours: { type: Number, default: 1 },
});

const data = ref(props.initial);
const range = ref(props.hours);
const mode = ref('rx'); // rx | tx
const refreshing = ref(false);
const now = ref(Date.now());

const donutEl = ref(null);
const areaEl = ref(null);
let donutChart = null;
let areaChart = null;
let timer = null;

const PALETTE = ['#2563eb', '#7c3aed', '#059669', '#d97706', '#0891b2', '#dc2626', '#94a3b8'];

const hasData = computed(() => data.value.interfaces?.length > 0);

// ── Format bitrate ──
function fmt(bps) {
    if (bps === null || bps === undefined) return '—';
    const v = Number(bps);
    if (v >= 1e9) return (v / 1e9).toFixed(2) + ' Gbps';
    if (v >= 1e6) return (v / 1e6).toFixed(2) + ' Mbps';
    if (v >= 1e3) return (v / 1e3).toFixed(1) + ' kbps';
    return Math.round(v) + ' bps';
}
function fmtShort(bps) {
    const v = Number(bps ?? 0);
    if (v >= 1e9) return (v / 1e9).toFixed(2) + 'G';
    if (v >= 1e6) return (v / 1e6).toFixed(1) + 'M';
    if (v >= 1e3) return (v / 1e3).toFixed(0) + 'k';
    return Math.round(v);
}
const statusLabel = (s) => (s === 'up' ? 'UP' : s === 'down' ? 'DOWN' : '—');
const statusClass = (s) =>
    s === 'up' ? 'bg-emerald-100 text-emerald-700' : s === 'down' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-500';

function colorOf(name) {
    const idx = data.value.history?.series?.findIndex((s) => s.name === name) ?? -1;
    return PALETTE[(idx < 0 ? PALETTE.length - 1 : idx) % PALETTE.length];
}

// ── Sparkline SVG ──
function sparkPath(values, w = 100, h = 28) {
    if (!values?.length) return '';
    const max = Math.max(...values, 1);
    const step = values.length > 1 ? w / (values.length - 1) : w;
    return values
        .map((v, i) => `${i === 0 ? 'M' : 'L'}${(i * step).toFixed(2)},${(h - (v / max) * (h - 3) - 1.5).toFixed(2)}`)
        .join(' ');
}
function sparkArea(values, w = 100, h = 28) {
    if (!values?.length) return '';
    return `${sparkPath(values, w, h)} L${w},${h} L0,${h} Z`;
}

// ── Refresh data (auto 15 dtk) ──
async function reload() {
    refreshing.value = true;
    try {
        const res = await fetch(`${route('admin.devices.interfaces', props.device.id)}?hours=${range.value}`, {
            headers: { Accept: 'application/json' },
        });
        if (res.ok) {
            data.value = await res.json();
            now.value = Date.now();
        }
    } catch {
        /* diam-diam — jaringan berubah tidak boleh merusak tampilan */
    } finally {
        refreshing.value = false;
    }
}

// ── Chart.js ──
async function renderCharts() {
    const { Chart, registerables } = await import('chart.js');
    Chart.register(...registerables);

    // Donut share
    if (donutEl.value) {
        donutChart?.destroy();
        const list = (data.value.interfaces ?? []).slice(0, 7);
        donutChart = new Chart(donutEl.value, {
            type: 'doughnut',
            data: {
                labels: list.map((i) => i.name),
                datasets: [
                    {
                        data: list.map((i) => i.total_bps),
                        backgroundColor: PALETTE,
                        borderWidth: 0,
                        hoverOffset: 6,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } },
                    tooltip: {
                        callbacks: {
                            label: (c) => ` ${fmt(c.raw)}`,
                        },
                    },
                },
            },
        });
    }

    // Area chart (stacked) RX / TX
    if (areaEl.value) {
        areaChart?.destroy();
        const hist = data.value.history ?? { labels: [], series: [] };
        const field = mode.value;

        areaChart = new Chart(areaEl.value, {
            type: 'line',
            data: {
                labels: hist.labels,
                datasets: hist.series.map((s, i) => ({
                    label: s.name,
                    data: s[field],
                    borderColor: PALETTE[i % PALETTE.length],
                    backgroundColor: PALETTE[i % PALETTE.length] + '55',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 0,
                    borderWidth: 1.5,
                })),
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { labels: { boxWidth: 12, font: { size: 11 } } },
                    tooltip: {
                        callbacks: {
                            label: (c) => ` ${c.dataset.label}: ${fmt(c.raw)}`,
                        },
                    },
                },
                scales: {
                    x: { stacked: true, ticks: { maxTicksLimit: 8, font: { size: 10 } } },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: { callback: (v) => fmtShort(v) + 'bps', font: { size: 10 } },
                    },
                },
            },
        });
    }
}

watch([data, mode], () => renderCharts(), { deep: false });
watch(range, () => reload());

onMounted(async () => {
    await renderCharts();
    timer = setInterval(reload, 15000);
});
onUnmounted(() => {
    clearInterval(timer);
    donutChart?.destroy();
    areaChart?.destroy();
});
</script>

<template>
    <AdminLayout>
        <!-- Header -->
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <Link :href="route('admin.devices.index')" class="text-sm font-semibold text-brand-700 hover:underline">← Jaringan</Link>
                <h1 class="mt-1 flex items-center gap-2 text-xl font-bold">
                    Infografik Trafik — {{ device.name }}
                    <span class="rounded-full px-2 py-0.5 text-[11px] font-bold" :class="statusClass(device.status)">
                        {{ statusLabel(device.status) }}
                    </span>
                </h1>
                <p class="text-sm text-slate-500">{{ device.host }} · sumber data:
                    <b>{{ device.use_snmp ? 'SNMP ifTable' : '' }}</b>
                    <b v-if="device.use_routeros">{{ device.use_snmp ? ' + ' : '' }}RouterOS API</b>
                    <b v-if="!device.use_snmp && !device.use_routeros" class="text-red-600">tidak ada (aktifkan SNMP/RouterOS)</b>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500">
                    <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span>
                    {{ refreshing ? 'memperbarui…' : 'auto-refresh 15 dtk' }}
                </span>
                <div class="flex rounded-lg border border-slate-300 bg-white p-1">
                    <button
                        v-for="h in [1, 6, 24, 168]"
                        :key="h"
                        class="rounded px-3 py-1 text-xs font-bold"
                        :class="range === h ? 'bg-brand-700 text-white' : 'text-slate-600 hover:bg-slate-100'"
                        @click="range = h"
                    >
                        {{ h < 24 ? h + 'j' : h === 24 ? '24j' : '7h' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="!hasData" class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
            <p class="text-4xl">📡</p>
            <h2 class="mt-3 font-bold">Belum ada data trafik interface</h2>
            <p class="mx-auto mt-1 max-w-lg text-sm text-slate-500">
                Data per-interface diambil dari <b>SNMP (ifTable)</b> atau <b>RouterOS API</b>.
                Aktifkan salah satu metode di pengaturan perangkat, lalu tunggu 1–2 siklus poll (30 detik).
            </p>
            <Link
                :href="route('admin.devices.index')"
                class="mt-5 inline-block rounded-lg bg-brand-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-brand-800"
            >
                Atur Perangkat
            </Link>
        </div>

        <template v-else>
            <!-- Hero statistik -->
            <div class="mt-5 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div class="rounded-2xl bg-gradient-to-br from-blue-600 to-blue-800 p-5 text-white shadow-lg shadow-blue-200">
                    <p class="text-xs font-semibold uppercase tracking-widest text-blue-100">Download (RX)</p>
                    <p class="mt-2 text-3xl font-black tabular-nums">{{ fmt(data.total.rx_bps) }}</p>
                    <p class="mt-1 text-xs text-blue-100">
                        puncak {{ data.total.peak_rx_bps !== null ? fmt(data.total.peak_rx_bps) : '—' }}
                    </p>
                </div>
                <div class="rounded-2xl bg-gradient-to-br from-violet-600 to-violet-800 p-5 text-white shadow-lg shadow-violet-200">
                    <p class="text-xs font-semibold uppercase tracking-widest text-violet-100">Upload (TX)</p>
                    <p class="mt-2 text-3xl font-black tabular-nums">{{ fmt(data.total.tx_bps) }}</p>
                    <p class="mt-1 text-xs text-violet-100">
                        puncak {{ data.total.peak_tx_bps !== null ? fmt(data.total.peak_tx_bps) : '—' }}
                    </p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Gabungan</p>
                    <p class="mt-2 text-3xl font-black tabular-nums text-slate-800">
                        {{ fmt(data.total.rx_bps + data.total.tx_bps) }}
                    </p>
                    <p class="mt-1 text-xs text-slate-400">RX + TX saat ini</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Interface</p>
                    <p class="mt-2 text-3xl font-black tabular-nums text-slate-800">{{ data.interfaces.length }}</p>
                    <p class="mt-1 text-xs text-slate-400">
                        {{ data.interfaces.filter((i) => i.oper_status === 'up').length }} aktif ·
                        {{ data.interfaces.filter((i) => i.oper_status === 'down').length }} down
                    </p>
                </div>
            </div>

            <!-- Donut + Area -->
            <div class="mt-5 grid gap-5 lg:grid-cols-5">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 lg:col-span-2">
                    <h2 class="font-bold">Porsi Trafik per Interface</h2>
                    <p class="text-xs text-slate-400">kontribusi RX+TX saat ini</p>
                    <div class="mt-3 h-64">
                        <canvas ref="donutEl"></canvas>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-4 lg:col-span-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 class="font-bold">Grafik Area Kumulatif</h2>
                            <p class="text-xs text-slate-400">
                                bucket {{ data.history.bucket_seconds }} dtk · rentang {{ range }} jam
                            </p>
                        </div>
                        <div class="flex rounded-lg bg-slate-100 p-1 text-xs font-bold">
                            <button
                                class="rounded px-3 py-1"
                                :class="mode === 'rx' ? 'bg-white text-blue-700 shadow' : 'text-slate-500'"
                                @click="mode = 'rx'"
                            >
                                ↓ Download
                            </button>
                            <button
                                class="rounded px-3 py-1"
                                :class="mode === 'tx' ? 'bg-white text-violet-700 shadow' : 'text-slate-500'"
                                @click="mode = 'tx'"
                            >
                                ↑ Upload
                            </button>
                        </div>
                    </div>
                    <div class="mt-3 h-72">
                        <canvas ref="areaEl"></canvas>
                    </div>
                </div>
            </div>

            <!-- Kartu interface + sparkline -->
            <h2 class="mt-6 font-bold">Detail Interface</h2>
            <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <div
                    v-for="(it, idx) in data.interfaces"
                    :key="it.name"
                    class="rounded-2xl border border-slate-200 bg-white p-4 transition hover:shadow-md"
                >
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex min-w-0 items-center gap-2">
                            <span class="h-3 w-3 shrink-0 rounded-full" :style="{ background: PALETTE[idx % PALETTE.length] }"></span>
                            <p class="truncate font-bold" :title="it.name">{{ it.name }}</p>
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold" :class="statusClass(it.oper_status)">
                            {{ statusLabel(it.oper_status) }}
                        </span>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-2 text-center">
                        <div class="rounded-lg bg-blue-50 py-2">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-blue-600">RX</p>
                            <p class="text-lg font-black tabular-nums text-blue-700">{{ fmt(it.rx_bps) }}</p>
                        </div>
                        <div class="rounded-lg bg-violet-50 py-2">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-violet-600">TX</p>
                            <p class="text-lg font-black tabular-nums text-violet-700">{{ fmt(it.tx_bps) }}</p>
                        </div>
                    </div>

                    <!-- Bar porsi / utilisasi -->
                    <div class="mt-3">
                        <div class="flex items-center justify-between text-[11px] font-semibold text-slate-500">
                            <span v-if="it.util !== null">Utilisasi link</span>
                            <span v-else>Porsi trafik</span>
                            <span>{{ it.util !== null ? it.util + '%' : it.share + '%' }}</span>
                        </div>
                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100">
                            <div
                                class="h-full rounded-full transition-all duration-500"
                                :class="
                                    it.util !== null
                                        ? it.util > 80
                                            ? 'bg-red-500'
                                            : it.util > 50
                                              ? 'bg-amber-500'
                                              : 'bg-emerald-500'
                                        : 'bg-brand-600'
                                "
                                :style="{ width: Math.min(100, it.util ?? it.share) + '%' }"
                            ></div>
                        </div>
                        <p v-if="it.speed" class="mt-1 text-[10px] text-slate-400">link {{ fmt(it.speed) }}</p>
                    </div>

                    <!-- Sparkline 1 jam -->
                    <div class="mt-3">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">1 jam terakhir</p>
                        <svg viewBox="0 0 100 28" class="mt-1 h-8 w-full" preserveAspectRatio="none">
                            <path :d="sparkArea(data.spark?.[it.name] ?? [])" :fill="PALETTE[idx % PALETTE.length]" opacity="0.15" />
                            <path
                                :d="sparkPath(data.spark?.[it.name] ?? [])"
                                fill="none"
                                :stroke="PALETTE[idx % PALETTE.length]"
                                stroke-width="1.5"
                                vector-effect="non-scaling-stroke"
                            />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Tabel ranking -->
            <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">#</th>
                            <th class="px-4 py-3">Interface</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">RX (Download)</th>
                            <th class="px-4 py-3 text-right">TX (Upload)</th>
                            <th class="px-4 py-3 text-right">Util</th>
                            <th class="px-4 py-3 w-48">Porsi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(it, idx) in data.interfaces" :key="it.name" class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-black text-slate-400">{{ idx + 1 }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="h-2.5 w-2.5 rounded-full" :style="{ background: PALETTE[idx % PALETTE.length] }"></span>
                                    <span class="font-semibold">{{ it.name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold" :class="statusClass(it.oper_status)">
                                    {{ statusLabel(it.oper_status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-blue-700">{{ fmt(it.rx_bps) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-violet-700">{{ fmt(it.tx_bps) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                <span v-if="it.util !== null" :class="it.util > 80 ? 'text-red-600 font-bold' : ''">{{ it.util }}%</span>
                                <span v-else class="text-slate-300">—</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                        <div
                                            class="h-full rounded-full"
                                            :style="{ width: it.share + '%', background: PALETTE[idx % PALETTE.length] }"
                                        ></div>
                                    </div>
                                    <span class="w-10 text-right text-xs font-semibold text-slate-500">{{ it.share }}%</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p class="mt-3 text-xs text-slate-400">
                Diperbarui {{ new Date(data.generated_at).toLocaleString('id-ID') }} ·
                interval poll 30 detik · agregat RX/TX menjadi grafik perangkat
            </p>
        </template>
    </AdminLayout>
</template>
