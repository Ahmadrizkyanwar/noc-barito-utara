<script setup>
import LocationPicker from '@/Components/LocationPicker.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import UserLayout from '@/Layouts/UserLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    view: { type: String, required: true },
    // admin
    stats: { type: Object, default: null },
    devices: { type: Array, default: () => [] },
    recentTickets: { type: Array, default: () => [] },
    chart: { type: Object, default: null },
    widgets: { type: Array, default: () => [] },
    widgetOptions: { type: Array, default: () => [] },
    trend: { type: Object, default: null },
    topDevices: { type: Array, default: () => [] },
    topInterfaces: { type: Array, default: () => [] },
    // user
    tickets: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    statuses: { type: Object, default: () => ({}) },
    vps: { type: Object, default: () => ({}) },
    vpsStatuses: { type: Object, default: () => ({}) },
    vpsOperatingSystems: { type: Object, default: () => ({}) },
    vpsRequests: { type: Array, default: () => [] },
    // Pendaftaran Domain & Hosting
    serviceSummary: {
        type: Object,
        default: () => ({ status: null, domain: { total: 0, pending: 0 }, hosting: { total: 0, pending: 0 } }),
    },
    serviceStatuses: { type: Object, default: () => ({}) },
    serviceTypes: { type: Object, default: () => ({}) },
    servicePackages: { type: Object, default: () => ({}) },
    serviceDurations: { type: Object, default: () => ({}) },
    serviceRequests: { type: Array, default: () => [] },
});

const isAdmin = computed(() => props.view === 'admin');
const Layout = computed(() => (isAdmin.value ? AdminLayout : UserLayout));

const statusLabel = (s) => props.statuses[s] ?? s;
const statusClass = (s) =>
    ({
        open: 'bg-blue-100 text-blue-700',
        proses: 'bg-amber-100 text-amber-800',
        selesai: 'bg-emerald-100 text-emerald-700',
    })[s] ?? 'bg-slate-100 text-slate-600';

// Status request VPS (label & warna berbeda dari tiket)
const vpsStatusLabel = (s) => props.vpsStatuses[s] ?? s;
const vpsOsLabel = (r) => {
    if (!r.os) return '';
    if (r.os === 'lainnya') return r.os_other || 'Lainnya';
    return props.vpsOperatingSystems[r.os] ?? r.os;
};
const vpsStatusClass = (s) =>
    ({
        pending: 'bg-amber-100 text-amber-800',
        approved: 'bg-emerald-100 text-emerald-700',
        rejected: 'bg-red-100 text-red-700',
    })[s] ?? 'bg-slate-100 text-slate-600';

// ── Pendaftaran Domain & Hosting (kartu + riwayat) ──
const accountStatusClass = (s) =>
    ({ pending: 'text-amber-600', approved: 'text-emerald-600', rejected: 'text-red-600' })[s] ?? '';
const accountStatusLabel = (s) =>
    ({ pending: 'Menunggu validasi', approved: 'Disetujui', rejected: 'Ditolak' })[s] ?? s;
const svcStatusLabel = (s) => props.serviceStatuses[s] ?? s;
const svcStatusClass = (s) =>
    ({
        pending: 'bg-amber-100 text-amber-800',
        approved: 'bg-emerald-100 text-emerald-700',
        rejected: 'bg-red-100 text-red-700',
    })[s] ?? 'bg-slate-100 text-slate-600';
const svcSpecOf = (r) => {
    const parts = [];
    if (r.domain_name) parts.push(r.domain_name);
    if (r.hosting_package) parts.push(props.servicePackages[r.hosting_package] ?? r.hosting_package);
    if (r.duration) {
        const d = props.serviceDurations?.[r.type] ?? {};
        parts.push(d[r.duration] ?? `${r.duration} ${r.type === 'domain' ? 'tahun' : 'bulan'}`);
    }
    return parts.join(' · ') || '—';
};
const svcTotal = () => (props.serviceSummary.domain?.total ?? 0) + (props.serviceSummary.hosting?.total ?? 0);

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
    if (v >= 1e9) return (v / 1e9).toFixed(1) + 'G';
    if (v >= 1e6) return (v / 1e6).toFixed(0) + 'M';
    if (v >= 1e3) return (v / 1e3).toFixed(0) + 'k';
    return Math.round(v);
}
const devClass = (s) =>
    s === 'up' ? 'bg-emerald-500' : s === 'down' ? 'bg-red-500' : 'bg-slate-400';

// ── Widget: pilihan admin ──
const isEnabled = (id) => props.widgets.includes(id);
const showWidgetModal = ref(false);
const selected = ref([...props.widgets]);

watch(
    () => props.widgets,
    (v) => (selected.value = [...v])
);

const widgetForm = useForm({ enabled: [] });

function saveWidgets() {
    widgetForm.enabled = [...selected.value];
    widgetForm.patch(route('admin.dashboard.widgets.update'), {
        preserveScroll: true,
        onSuccess: () => {
            showWidgetModal.value = false;
            widgetForm.reset();
        },
    });
}

function toggleAll() {
    selected.value = props.widgetOptions.map((w) => w.id);
}
function clearAll() {
    selected.value = [];
}

// ── Form lapor (user) ──
const report = useForm({
    title: '',
    category: props.categories[0] ?? 'Jaringan',
    description: '',
    location: '',
    lat: null,
    lng: null,
    reporter_name: '',
    reporter_contact: '',
});

// Jembatan v-model LocationPicker ({lat,lng}|null) ↔ report.lat/report.lng.
const reportPoint = computed({
    get: () =>
        report.lat !== null && report.lng !== null ? { lat: report.lat, lng: report.lng } : null,
    set: (v) => {
        report.lat = v?.lat ?? null;
        report.lng = v?.lng ?? null;
    },
});

function submitReport() {
    report.post(route('lapor.store'), {
        preserveScroll: true,
        onSuccess: () => report.reset(),
    });
}

// ── Chart.js (semua canvas bersifat kondisional → guard null) ──
const cpuEl = ref(null);
const trendEl = ref(null);
const donutEl = ref(null);
const charts = { cpu: null, trend: null, donut: null };
let refreshTimer = null;

async function renderCharts() {
    const { Chart, registerables } = await import('chart.js');
    Chart.register(...registerables);

    // CPU & RTT 24 jam
    if (cpuEl.value && props.chart) {
        charts.cpu?.destroy();
        charts.cpu = new Chart(cpuEl.value, {
            type: 'line',
            data: {
                labels: props.chart.labels,
                datasets: [
                    {
                        label: 'CPU (%)',
                        data: props.chart.cpu,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37,99,235,.1)',
                        tension: 0.3,
                        yAxisID: 'y',
                        spanGaps: true,
                    },
                    {
                        label: 'RTT (ms)',
                        data: props.chart.rtt,
                        borderColor: '#059669',
                        backgroundColor: 'rgba(5,150,105,.1)',
                        tension: 0.3,
                        yAxisID: 'y1',
                        spanGaps: true,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: { position: 'left', min: 0, max: 100, title: { display: true, text: 'CPU %' } },
                    y1: { position: 'right', min: 0, grid: { drawOnChartArea: false }, title: { display: true, text: 'ms' } },
                },
            },
        });
    }

    // Tren trafik 24 jam (area RX/TX)
    if (trendEl.value && props.trend) {
        charts.trend?.destroy();
        charts.trend = new Chart(trendEl.value, {
            type: 'line',
            data: {
                labels: props.trend.labels,
                datasets: [
                    {
                        label: 'RX (Download)',
                        data: props.trend.rx,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37,99,235,.18)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 0,
                        spanGaps: true,
                    },
                    {
                        label: 'TX (Upload)',
                        data: props.trend.tx,
                        borderColor: '#7c3aed',
                        backgroundColor: 'rgba(124,58,237,.15)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 0,
                        spanGaps: true,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    tooltip: { callbacks: { label: (c) => ` ${c.dataset.label}: ${fmt(c.raw)}` } },
                    legend: { labels: { boxWidth: 12, font: { size: 11 } } },
                },
                scales: {
                    x: { ticks: { maxTicksLimit: 12, font: { size: 10 } } },
                    y: {
                        beginAtZero: true,
                        ticks: { callback: (v) => fmtShort(v) + 'bps', font: { size: 10 } },
                    },
                },
            },
        });
    }

    // Donut status perangkat
    if (donutEl.value && props.stats) {
        charts.donut?.destroy();
        const s = props.stats;
        const data = [s.devices_up, s.devices_down, s.devices_unknown];
        charts.donut = new Chart(donutEl.value, {
            type: 'doughnut',
            data: {
                labels: ['Online', 'Gangguan', 'Baru'],
                datasets: [
                    { data, backgroundColor: ['#059669', '#dc2626', '#94a3b8'], borderWidth: 0, hoverOffset: 6 },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '66%',
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } },
                },
            },
        });
    }
}

async function rerender() {
    await nextTick();
    await renderCharts();
}

onMounted(async () => {
    await renderCharts();
    // Auto-refresh dashboard admin tiap 30 detik
    if (isAdmin.value) {
        refreshTimer = setInterval(() => window.location.reload(), 30000);
    }
});
onUnmounted(() => {
    clearInterval(refreshTimer);
    Object.values(charts).forEach((c) => c?.destroy());
});
// Pilihan widget berubah (setelah simpan) → canvas muncul/hilang → render ulang
watch(() => props.widgets, () => rerender(), { deep: true });
</script>

<template>
    <component :is="Layout">
        <!-- ══════════ ADMIN ══════════ -->
        <template v-if="isAdmin">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold">Dashboard Administrator</h1>
                    <p class="text-xs text-slate-500">Tampilan dapat disesuaikan — pilih infografik yang ingin ditampilkan.</p>
                </div>
                <div class="flex gap-2">
                    <button
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold hover:bg-slate-100"
                        @click="showWidgetModal = true"
                    >
                        ✨ Sesuaikan Widget
                    </button>
                    <Link
                        :href="route('admin.devices.index')"
                        class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-bold text-white hover:bg-brand-800"
                    >
                        Kelola jaringan
                    </Link>
                </div>
            </div>

            <!-- 1. Kartu status ringkas -->
            <div v-if="isEnabled('status_ringkas')" class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Perangkat</p>
                    <p class="mt-1 text-3xl font-black">{{ stats.devices_total }}</p>
                    <p class="mt-1 text-xs text-slate-500">terpantau</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Online</p>
                    <p class="mt-1 text-3xl font-black text-emerald-700">{{ stats.devices_up }}</p>
                    <p class="mt-1 text-xs text-emerald-700">uptime {{ stats.uptime_pct ?? '—' }}%</p>
                </div>
                <div class="rounded-xl border border-red-200 bg-red-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-red-700">Gangguan</p>
                    <p class="mt-1 text-3xl font-black text-red-700">{{ stats.devices_down }}</p>
                    <p class="mt-1 text-xs text-red-700">perangkat down</p>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Tiket Terbuka</p>
                    <p class="mt-1 text-3xl font-black text-amber-700">{{ stats.open_tickets }}</p>
                    <p class="mt-1 text-xs text-amber-700">{{ stats.resolved_today }} selesai hari ini</p>
                </div>
            </div>

            <!-- 2. Tren trafik 24 jam -->
            <div v-if="isEnabled('trend_trafik')" class="mt-6 rounded-xl border border-slate-200 bg-white p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="font-bold">Tren Trafik 24 Jam</h2>
                        <p class="text-xs text-slate-400">gabungan RX/TX semua perangkat · auto-refresh 30 dtk</p>
                    </div>
                    <div class="flex gap-3 text-xs font-semibold">
                        <span class="flex items-center gap-1.5 text-blue-700"><span class="h-2.5 w-2.5 rounded-full bg-blue-600"></span>RX</span>
                        <span class="flex items-center gap-1.5 text-violet-700"><span class="h-2.5 w-2.5 rounded-full bg-violet-600"></span>TX</span>
                    </div>
                </div>
                <div class="mt-3 h-64">
                    <canvas ref="trendEl"></canvas>
                </div>
            </div>

            <!-- 3. CPU & RTT -->
            <div v-if="isEnabled('cpu_rtt')" class="mt-6 rounded-xl border border-slate-200 bg-white p-4">
                <div class="flex items-center justify-between">
                    <h2 class="font-bold">CPU &amp; Latensi 24 Jam Terakhir</h2>
                    <span class="text-xs text-slate-400">rata-rata per jam</span>
                </div>
                <div class="mt-3 h-64">
                    <canvas ref="cpuEl"></canvas>
                </div>
            </div>

            <!-- 4–6. Widget medium: donut + peringkat -->
            <div
                v-if="isEnabled('donut_status') || isEnabled('top_perangkat') || isEnabled('top_interface')"
                class="mt-6 grid gap-6 lg:grid-cols-2"
            >
                <!-- Donut status -->
                <div v-if="isEnabled('donut_status')" class="rounded-xl border border-slate-200 bg-white p-4">
                    <h2 class="font-bold">Status Perangkat</h2>
                    <p class="text-xs text-slate-400">proporsi online / gangguan / baru</p>
                    <div class="mt-3 h-56">
                        <canvas ref="donutEl"></canvas>
                    </div>
                </div>

                <!-- Top perangkat -->
                <div v-if="isEnabled('top_perangkat')" class="rounded-xl border border-slate-200 bg-white p-4">
                    <div class="flex items-center justify-between">
                        <h2 class="font-bold">Peringkat Trafik Perangkat</h2>
                        <span class="text-xs text-slate-400">RX+TX saat ini</span>
                    </div>
                    <ul class="mt-3 space-y-3">
                        <li v-for="(d, idx) in topDevices" :key="d.name">
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex min-w-0 items-center gap-2">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="devClass(d.status)"></span>
                                    <span class="truncate font-semibold">{{ d.name }}</span>
                                </span>
                                <span class="shrink-0 font-bold tabular-nums">{{ fmt(d.bps) }}</span>
                            </div>
                            <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    class="h-full rounded-full bg-brand-600 transition-all duration-500"
                                    :style="{
                                        width:
                                            (topDevices[0]?.bps > 0
                                                ? Math.max(3, (d.bps / topDevices[0].bps) * 100)
                                                : 0) + '%',
                                    }"
                                ></div>
                            </div>
                        </li>
                        <li v-if="topDevices.length === 0" class="py-4 text-center text-sm text-slate-500">
                            Belum ada data trafik.
                        </li>
                    </ul>
                </div>

                <!-- Top interface -->
                <div v-if="isEnabled('top_interface')" class="rounded-xl border border-slate-200 bg-white p-4 lg:col-span-2">
                    <div class="flex items-center justify-between">
                        <h2 class="font-bold">Top Interface Trafik</h2>
                        <span class="text-xs text-slate-400">lintas perangkat · sampel terakhir</span>
                    </div>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div v-for="(it, idx) in topInterfaces" :key="it.device + '/' + it.name">
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex min-w-0 items-center gap-2">
                                    <span
                                        class="h-2.5 w-2.5 shrink-0 rounded-full"
                                        :style="{ background: ['#2563eb', '#7c3aed', '#059669', '#d97706', '#0891b2', '#dc2626'][idx % 6] }"
                                    ></span>
                                    <span class="truncate font-semibold" :title="it.device + ' · ' + it.name">
                                        {{ it.name }}
                                    </span>
                                </span>
                                <span class="shrink-0 font-bold tabular-nums">{{ fmt(it.bps) }}</span>
                            </div>
                            <div class="mt-1 flex items-center gap-2">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                    <div
                                        class="h-full rounded-full transition-all duration-500"
                                        :style="{
                                            width:
                                                (topInterfaces[0]?.bps > 0
                                                    ? Math.max(3, (it.bps / topInterfaces[0].bps) * 100)
                                                    : 0) + '%',
                                            background: ['#2563eb', '#7c3aed', '#059669', '#d97706', '#0891b2', '#dc2626'][idx % 6],
                                        }"
                                    ></div>
                                </div>
                                <span class="w-28 shrink-0 truncate text-right text-[11px] text-slate-400" :title="it.device">
                                    {{ it.device }}
                                </span>
                            </div>
                            <p class="mt-0.5 text-[11px] text-slate-400">
                                ↓ {{ fmt(it.rx_bps) }} · ↑ {{ fmt(it.tx_bps) }}
                            </p>
                        </div>
                        <p v-if="topInterfaces.length === 0" class="py-4 text-center text-sm text-slate-500 sm:col-span-2">
                            Belum ada data trafik interface — aktifkan SNMP/RouterOS pada perangkat.
                        </p>
                    </div>
                </div>
            </div>

            <!-- 7–8. Daftar status + tiket -->
            <div
                v-if="isEnabled('status_perangkat') || isEnabled('tiket_terbuka')"
                class="mt-6 grid gap-6 lg:grid-cols-2"
            >
                <!-- Status perangkat -->
                <div v-if="isEnabled('status_perangkat')" class="rounded-xl border border-slate-200 bg-white">
                    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                        <h2 class="font-bold">Status Perangkat</h2>
                        <Link :href="route('admin.devices.index')" class="text-xs font-semibold text-brand-700 hover:underline">semua</Link>
                    </div>
                    <ul class="divide-y divide-slate-100">
                        <li v-for="d in devices" :key="d.id" class="flex items-center justify-between px-4 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">{{ d.name }}</p>
                                <p class="truncate text-xs text-slate-500">{{ d.host }}</p>
                            </div>
                            <div class="flex items-center gap-3 text-xs">
                                <span v-if="d.last_cpu !== null" class="text-slate-500">CPU {{ d.last_cpu }}%</span>
                                <span v-if="d.last_rtt_ms !== null" class="text-slate-500">{{ d.last_rtt_ms }} ms</span>
                                <span
                                    class="rounded-full px-2 py-0.5 font-bold"
                                    :class="{
                                        'bg-emerald-100 text-emerald-700': d.status === 'up',
                                        'bg-red-100 text-red-700': d.status === 'down',
                                        'bg-slate-100 text-slate-500': d.status === 'unknown',
                                    }"
                                >
                                    {{ d.status === 'up' ? 'ONLINE' : d.status === 'down' ? 'DOWN' : 'BARU' }}
                                </span>
                            </div>
                        </li>
                        <li v-if="devices.length === 0" class="px-4 py-6 text-center text-sm text-slate-500">
                            Belum ada perangkat —
                            <Link :href="route('admin.devices.index')" class="font-semibold text-brand-700 hover:underline">tambah sekarang</Link>.
                        </li>
                    </ul>
                </div>

                <!-- Tiket terbaru -->
                <div v-if="isEnabled('tiket_terbuka')" class="rounded-xl border border-slate-200 bg-white">
                    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                        <h2 class="font-bold">Tiket Terbaru</h2>
                        <Link :href="route('admin.tickets.index')" class="text-xs font-semibold text-brand-700 hover:underline">semua</Link>
                    </div>
                    <ul class="divide-y divide-slate-100">
                        <li v-for="t in recentTickets" :key="t.id" class="px-4 py-3">
                            <Link :href="route('admin.tickets.show', t.id)" class="block hover:bg-slate-50">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="truncate text-sm font-semibold">{{ t.title }}</p>
                                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-bold" :class="statusClass(t.status)">
                                        {{ statusLabel(t.status) }}
                                    </span>
                                </div>
                                <p class="mt-0.5 text-xs text-slate-500">{{ t.code }} · {{ t.category }}</p>
                            </Link>
                        </li>
                        <li v-if="recentTickets.length === 0" class="px-4 py-6 text-center text-sm text-slate-500">
                            Belum ada tiket.
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Empty: tidak ada widget dipilih -->
            <div
                v-if="widgets.length === 0"
                class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center"
            >
                <p class="text-4xl">🧩</p>
                <h2 class="mt-3 font-bold">Tidak ada widget yang ditampilkan</h2>
                <p class="mt-1 text-sm text-slate-500">Klik <b>Sesuaikan Widget</b> untuk memilih infografik dashboard Anda.</p>
                <button
                    class="mt-4 rounded-lg bg-brand-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-brand-800"
                    @click="showWidgetModal = true"
                >
                    Pilih Widget
                </button>
            </div>

            <!-- ══ Modal sesuaikan widget ══ -->
            <div v-if="showWidgetModal" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4">
                <div class="mt-10 w-full max-w-xl rounded-2xl bg-white p-6 shadow-xl">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-bold">Sesuaikan Widget Dashboard</h2>
                            <p class="text-xs text-slate-500">Pilih infografik yang tampil di halaman utama dashboard.</p>
                        </div>
                        <button class="text-slate-400 hover:text-slate-600" @click="showWidgetModal = false">✕</button>
                    </div>

                    <div class="mt-4 flex gap-2">
                        <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold hover:bg-slate-100" @click="toggleAll">
                            Pilih semua
                        </button>
                        <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold hover:bg-slate-100" @click="clearAll">
                            Kosongkan
                        </button>
                        <span class="ml-auto self-center text-xs font-semibold text-slate-400">
                            {{ selected.length }}/{{ widgetOptions.length }} widget
                        </span>
                    </div>

                    <div class="mt-4 max-h-[55vh] space-y-2 overflow-y-auto pr-1">
                        <label
                            v-for="w in widgetOptions"
                            :key="w.id"
                            class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition"
                            :class="selected.includes(w.id) ? 'border-brand-500 bg-brand-50' : 'border-slate-200 hover:bg-slate-50'"
                        >
                            <input
                                v-model="selected"
                                type="checkbox"
                                :value="w.id"
                                class="mt-1 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                            >
                            <span class="min-w-0">
                                <span class="block text-sm font-bold">{{ w.label }}</span>
                                <span class="block text-xs text-slate-500">{{ w.description }}</span>
                            </span>
                        </label>
                    </div>

                    <p v-if="widgetForm.errors.enabled" class="mt-2 text-xs text-red-600">{{ widgetForm.errors.enabled }}</p>

                    <div class="mt-4 flex justify-end gap-2 border-t border-slate-100 pt-4">
                        <button
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-100"
                            @click="showWidgetModal = false"
                        >
                            Batal
                        </button>
                        <button
                            class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-bold text-white hover:bg-brand-800 disabled:opacity-60"
                            :disabled="widgetForm.processing"
                            @click="saveWidgets"
                        >
                            {{ widgetForm.processing ? 'Menyimpan…' : 'Simpan Tampilan' }}
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- ══════════ USER ══════════ -->
        <template v-else>
            <h1 class="text-xl font-bold">Dashboard Saya</h1>
            <p class="mt-1 text-sm text-slate-500">Laporkan gangguan dan pantau status penanganan laporan Anda.</p>

            <!-- Kartu Pendaftaran: VPS · Domain · Hosting -->
            <div class="mt-4 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4">
                    <div>
                        <p class="text-sm font-bold">Pendaftaran VPS</p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Status akun:
                            <b :class="accountStatusClass(vps.status)">{{ accountStatusLabel(vps.status) }}</b>
                            · {{ vps.total ?? 0 }} pendaftaran
                            <template v-if="vps.pending"> · {{ vps.pending }} menunggu review</template>
                        </p>
                    </div>
                    <Link
                        :href="route('vps.index')"
                        class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800"
                    >
                        Buka Pendaftaran VPS →
                    </Link>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4">
                    <div>
                        <p class="text-sm font-bold">Pendaftaran Domain</p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Status akun:
                            <b :class="accountStatusClass(serviceSummary.status)">
                                {{ accountStatusLabel(serviceSummary.status) }}
                            </b>
                            · {{ serviceSummary.domain?.total ?? 0 }} pendaftaran
                            <template v-if="serviceSummary.domain?.pending">
                                · {{ serviceSummary.domain.pending }} menunggu review
                            </template>
                        </p>
                    </div>
                    <Link
                        :href="route('domain.index')"
                        class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800"
                    >
                        Buka Pendaftaran Domain →
                    </Link>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4">
                    <div>
                        <p class="text-sm font-bold">Pendaftaran Hosting</p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Status akun:
                            <b :class="accountStatusClass(serviceSummary.status)">
                                {{ accountStatusLabel(serviceSummary.status) }}
                            </b>
                            · {{ serviceSummary.hosting?.total ?? 0 }} pendaftaran
                            <template v-if="serviceSummary.hosting?.pending">
                                · {{ serviceSummary.hosting.pending }} menunggu review
                            </template>
                        </p>
                    </div>
                    <Link
                        :href="route('hosting.index')"
                        class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800"
                    >
                        Buka Pendaftaran Hosting →
                    </Link>
                </div>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-5">
                <!-- Form lapor -->
                <div class="lg:col-span-2">
                    <form class="rounded-xl border border-slate-200 bg-white p-5" @submit.prevent="submitReport">
                        <h2 class="font-bold">Lapor Gangguan Baru</h2>

                        <div class="mt-4 space-y-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600">Judul *</label>
                                <input
                                    v-model="report.title"
                                    type="text"
                                    required
                                    maxlength="200"
                                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                                    :class="{ 'border-red-400': report.errors.title }"
                                >
                                <p v-if="report.errors.title" class="mt-1 text-xs text-red-600">{{ report.errors.title }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600">Kategori *</label>
                                <select
                                    v-model="report.category"
                                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                                >
                                    <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600">Lokasi</label>
                                <input
                                    v-model="report.location"
                                    type="text"
                                    maxlength="200"
                                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                                >
                            </div>

                            <div>
                                <span class="block text-xs font-semibold text-slate-600">Titik lokasi (opsional)</span>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    Tekan tombol GPS atau klik peta untuk menandai lokasi.
                                </p>
                                <div class="mt-2">
                                    <LocationPicker v-model="reportPoint" height="220px" />
                                </div>
                                <p v-if="report.errors.lat" class="mt-1 text-xs text-red-600">{{ report.errors.lat }}</p>
                                <p v-else-if="report.errors.lng" class="mt-1 text-xs text-red-600">{{ report.errors.lng }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600">Keterangan *</label>
                                <textarea
                                    v-model="report.description"
                                    required
                                    rows="3"
                                    maxlength="2000"
                                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                                    :class="{ 'border-red-400': report.errors.description }"
                                ></textarea>
                                <p v-if="report.errors.description" class="mt-1 text-xs text-red-600">{{ report.errors.description }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600">Nama pelapor *</label>
                                <input
                                    v-model="report.reporter_name"
                                    type="text"
                                    required
                                    maxlength="100"
                                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                                >
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600">Kontak (HP/email)</label>
                                <input
                                    v-model="report.reporter_contact"
                                    type="text"
                                    maxlength="100"
                                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                                >
                            </div>

                            <button
                                type="submit"
                                :disabled="report.processing"
                                class="w-full rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-brand-800 disabled:opacity-60"
                            >
                                {{ report.processing ? 'Mengirim…' : 'Kirim Laporan' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tiket saya + Riwayat Request VPS (kolom kanan, lebar sama) -->
                <div class="space-y-6 lg:col-span-3">
                    <div class="rounded-xl border border-slate-200 bg-white">
                        <div class="border-b border-slate-100 px-4 py-3">
                            <h2 class="font-bold">Tiket Saya ({{ tickets.length }})</h2>
                        </div>
                        <ul class="divide-y divide-slate-100">
                            <li v-for="t in tickets" :key="t.id" class="px-4 py-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold">{{ t.title }}</p>
                                        <p class="mt-0.5 text-xs text-slate-500">
                                            {{ t.code }} · {{ t.category }} · {{ new Date(t.created_at).toLocaleDateString('id-ID') }}
                                        </p>
                                        <p class="mt-1 text-sm text-slate-600">{{ t.description }}</p>
                                    </div>
                                    <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-bold" :class="statusClass(t.status)">
                                        {{ statusLabel(t.status) }}
                                    </span>
                                </div>

                                <!-- Riwayat status -->
                                <ol v-if="t.activities?.length" class="mt-3 space-y-1 border-l-2 border-slate-100 pl-3">
                                    <li v-for="a in t.activities" :key="a.id" class="text-xs text-slate-500">
                                        <span class="font-semibold text-slate-600">
                                            {{ new Date(a.created_at).toLocaleString('id-ID') }}
                                        </span>
                                        —
                                        <template v-if="a.action === 'created'">Laporan dibuat</template>
                                        <template v-else-if="a.action === 'status'">
                                            Status: {{ statusLabel(a.old_value) }} → <b>{{ statusLabel(a.new_value) }}</b>
                                        </template>
                                        <template v-else-if="a.action === 'note'">{{ a.note }}</template>
                                        <template v-else>{{ a.action }}</template>
                                    </li>
                                </ol>
                            </li>
                            <li v-if="tickets.length === 0" class="px-4 py-8 text-center text-sm text-slate-500">
                                Anda belum pernah melapor.
                            </li>
                        </ul>
                    </div>

                    <!-- Riwayat Pendaftaran VPS — di bawah Tiket Saya, lebar sama -->
                    <div class="rounded-xl border border-slate-200 bg-white">
                        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                            <h2 class="font-bold">Riwayat Pendaftaran VPS ({{ vps.total ?? 0 }})</h2>
                            <Link
                                :href="route('vps.index')"
                                class="text-xs font-semibold text-brand-700 hover:underline"
                            >
                                Pendaftaran baru →
                            </Link>
                        </div>
                        <ul class="flex-1 divide-y divide-slate-100">
                            <li v-for="r in vpsRequests" :key="r.id" class="px-4 py-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold">
                                            <span class="font-mono text-xs font-bold text-indigo-700">{{ r.code }}</span>
                                        </p>
                                        <p class="mt-0.5 text-xs text-slate-500">
                                            {{ r.instansi }} — {{ r.name }}
                                        </p>
                                        <p class="mt-0.5 text-xs text-slate-500">
                                            {{ r.cores }} core / {{ r.ram_gb }} GB / {{ r.public_ips }} IP
                                            <span v-if="r.os"> · {{ vpsOsLabel(r) }}</span> ·
                                            {{ new Date(r.created_at).toLocaleDateString('id-ID') }}
                                        </p>
                                    </div>
                                    <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-bold" :class="vpsStatusClass(r.status)">
                                        {{ vpsStatusLabel(r.status) }}
                                    </span>
                                </div>

                                <p class="mt-2 text-sm text-slate-600">{{ r.purpose }}</p>

                                <p v-if="r.admin_note" class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                    Catatan admin: {{ r.admin_note }}
                                </p>

                                <div
                                    v-if="r.credential_file"
                                    class="mt-2 flex flex-wrap items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2"
                                >
                                    <span class="text-xs font-semibold text-emerald-800">🔑 Kredensial tersedia</span>
                                    <a
                                        :href="route('vps.credentials', r.id)"
                                        class="text-xs font-bold text-emerald-700 hover:underline"
                                    >
                                        Unduh ↓
                                    </a>
                                </div>
                            </li>
                            <li v-if="vpsRequests.length === 0" class="px-4 py-8 text-center text-sm text-slate-500">
                                Belum ada pendaftaran VPS.
                                <Link :href="route('vps.index')" class="font-semibold text-brand-700 hover:underline">Buat sekarang</Link>
                            </li>
                            <li v-if="vps.total > vpsRequests.length" class="px-4 py-3 text-center text-xs text-slate-400">
                                Menampilkan {{ vpsRequests.length }} terbaru dari {{ vps.total }} pendaftaran
                            </li>
                        </ul>
                    </div>

                    <!-- Riwayat Pendaftaran Domain & Hosting -->
                    <div class="rounded-xl border border-slate-200 bg-white">
                        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                            <h2 class="font-bold">Riwayat Pendaftaran Domain &amp; Hosting ({{ svcTotal() }})</h2>
                            <span class="flex gap-3 text-xs font-semibold">
                                <Link :href="route('domain.index')" class="text-brand-700 hover:underline">
                                    Domain →
                                </Link>
                                <Link :href="route('hosting.index')" class="text-brand-700 hover:underline">
                                    Hosting →
                                </Link>
                            </span>
                        </div>
                        <ul class="flex-1 divide-y divide-slate-100">
                            <li v-for="r in serviceRequests" :key="r.id" class="px-4 py-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold">
                                            <span class="font-mono text-xs font-bold text-indigo-700">{{ r.code }}</span>
                                            <span class="ml-1.5 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-semibold uppercase text-slate-500">
                                                {{ r.type }}
                                            </span>
                                        </p>
                                        <p class="mt-0.5 text-xs text-slate-500">
                                            {{ r.instansi }} — {{ r.name }} · {{ svcSpecOf(r) }} ·
                                            {{ new Date(r.created_at).toLocaleDateString('id-ID') }}
                                        </p>
                                    </div>
                                    <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-bold" :class="svcStatusClass(r.status)">
                                        {{ svcStatusLabel(r.status) }}
                                    </span>
                                </div>

                                <p class="mt-2 text-sm text-slate-600">{{ r.purpose }}</p>

                                <p v-if="r.admin_note" class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                    Catatan admin: {{ r.admin_note }}
                                </p>

                                <div
                                    v-if="r.supporting_document"
                                    class="mt-2 flex flex-wrap items-center gap-2 rounded-lg bg-slate-50 px-3 py-2"
                                >
                                    <span class="text-xs font-semibold text-slate-600">📎 Dokumen pendukung</span>
                                    <a
                                        :href="route('service.document', r.id)"
                                        class="text-xs font-bold text-brand-700 hover:underline"
                                    >
                                        Unduh ↓
                                    </a>
                                </div>
                            </li>
                            <li v-if="serviceRequests.length === 0" class="px-4 py-8 text-center text-sm text-slate-500">
                                Belum ada pendaftaran domain/hosting.
                                <Link :href="route('domain.index')" class="font-semibold text-brand-700 hover:underline">Buat sekarang</Link>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </template>
    </component>
</template>
