<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import nocBanner from '@/images/noc-logo-banner.png';

const props = defineProps({
    status: { type: Object, required: true },
});

const live = ref(props.status);
let timer = null;

async function refresh() {
    try {
        const res = await fetch(route('landing.status'), {
            headers: { Accept: 'application/json' },
        });
        if (res.ok) {
            live.value = await res.json();
        }
    } catch {
        /* diam-diam — polling gagal tidak boleh merusak halaman */
    }
}

onMounted(() => {
    timer = setInterval(refresh, 15000);
});
onUnmounted(() => clearInterval(timer));

const features = [
    {
        title: 'SNMP',
        desc: 'Ambil CPU, memori, uptime, dan trafik perangkat lewat SNMP v1/v2c/v3.',
        icon: '📊',
    },
    {
        title: 'ICMP',
        desc: 'Cek keterjangkauan dan waktu respon (RTT) setiap 30 detik.',
        icon: '📡',
    },
    {
        title: 'RouterOS API',
        desc: 'Koneksi langsung ke RouterOS untuk status resource dan interface.',
        icon: '🔌',
    },
    {
        title: 'Ticketing Gangguan',
        desc: 'Laporan gangguan dengan notifikasi Telegram dan riwayat penanganan.',
        icon: '🎫',
    },
];

function uptimeColor(v) {
    if (v === null) return 'text-slate-400';
    if (v >= 99) return 'text-emerald-600';
    if (v >= 90) return 'text-amber-600';
    return 'text-red-600';
}
</script>

<template>
    <PublicLayout>
        <!-- Hero -->
        <section class="bg-gradient-to-br from-brand-900 via-brand-800 to-brand-700 text-white">
            <div class="mx-auto max-w-6xl px-4 py-20 sm:py-28">
                <p class="text-sm font-semibold uppercase tracking-widest text-brand-200">
                    Network Operation Center
                </p>
                <h1 class="mt-3 max-w-3xl text-3xl font-black leading-tight sm:text-5xl">
                    Pemantauan Jaringan Kabupaten Barito Utara
                </h1>
                <p class="mt-4 max-w-2xl text-brand-100">
                    Satu pusat pemantauan infrastruktur jaringan Diskominfosandi —
                    pemantauan real-time SNMP, ICMP, dan RouterOS API, serta layanan
                    pelaporan gangguan terintegrasi.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <Link
                        :href="route('lapor.index')"
                        class="rounded-lg bg-white px-6 py-3 text-sm font-bold text-brand-800 hover:bg-brand-50"
                    >
                        Lapor Gangguan
                    </Link>
                    <Link
                        :href="route('login')"
                        class="rounded-lg border border-white/40 px-6 py-3 text-sm font-bold text-white hover:bg-white/10"
                    >
                        Masuk ke Dashboard
                    </Link>
                </div>
            </div>
        </section>

        <!-- Status live -->
        <section class="mx-auto -mt-10 max-w-6xl px-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold">Status Jaringan Live</h2>
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500">
                        <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span>
                        diperbarui otomatis
                    </span>
                </div>
                <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Perangkat</p>
                        <p class="mt-1 text-3xl font-black">{{ live.total }}</p>
                    </div>
                    <div class="rounded-xl bg-emerald-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Online</p>
                        <p class="mt-1 text-3xl font-black text-emerald-700">{{ live.up }}</p>
                    </div>
                    <div class="rounded-xl bg-red-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-red-700">Gangguan</p>
                        <p class="mt-1 text-3xl font-black text-red-700">{{ live.down }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Uptime</p>
                        <p class="mt-1 text-3xl font-black" :class="uptimeColor(live.uptime_pct)">
                            {{ live.uptime_pct === null ? '—' : live.uptime_pct + '%' }}
                        </p>
                    </div>
                </div>
                <p class="mt-4 text-xs text-slate-500">
                    Tiket gangguan terbuka: <b>{{ live.open_tickets }}</b>
                </p>
            </div>
        </section>

        <!-- Fitur -->
        <section class="mx-auto max-w-6xl px-4 py-16">
            <h2 class="text-center text-2xl font-bold">Fitur Pemantauan</h2>
            <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="f in features" :key="f.title" class="rounded-xl border border-slate-200 bg-white p-5">
                    <div class="text-3xl">{{ f.icon }}</div>
                    <h3 class="mt-3 font-bold">{{ f.title }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ f.desc }}</p>
                </div>
            </div>
        </section>

        <!-- Kontak -->
        <section class="border-t border-slate-200 bg-white">
            <div class="mx-auto max-w-6xl px-4 py-12">
                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <h2 class="text-lg font-bold">Kontak</h2>
                        <p class="mt-2 text-sm text-slate-600">
                            Dinas Komunikasi, Informatika, dan Statistik<br>
                            Kabupaten Barito Utara
                        </p>
                        <p class="mt-2 text-sm text-slate-500">
                            Untuk gangguan jaringan, gunakan halaman
                            <Link :href="route('lapor.index')" class="font-semibold text-brand-700 hover:underline">Lapor Gangguan</Link>.
                        </p>
                    </div>
                    <div class="text-sm text-slate-600 sm:text-right">
                        <p class="font-semibold">Metode Pemantauan</p>
                        <p class="mt-1 text-slate-500">SNMP · ICMP · RouterOS API — interval 30 detik</p>
                        <!-- Logo NOC tepat di bawah Metode Pemantauan -->
                        <img
                            :src="nocBanner"
                            alt="NOC — Network Operation Center"
                            class="mt-4 h-10 w-auto sm:ml-auto"
                            @error="$event.target.style.display='none'"
                        >
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
