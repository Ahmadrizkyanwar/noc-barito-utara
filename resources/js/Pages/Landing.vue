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
        title: 'SNMP v1/v2c/v3',
        desc: 'CPU, memori, uptime, dan trafik per interface diambil langsung dari perangkat setiap siklus poll.',
        icon: '📊',
        tag: 'telemetri',
    },
    {
        title: 'ICMP Probe',
        desc: 'Keterjangkauan dan waktu respon (RTT) tiap 30 detik, gagal-tertutup tanpa menghentikan siklus.',
        icon: '📡',
        tag: 'realtime',
    },
    {
        title: 'RouterOS API',
        desc: 'Koneksi native ke MikroTik RouterOS untuk resource, interface, dan status bridge/route.',
        icon: '🔌',
        tag: 'mikrotik',
    },
    {
        title: 'Ticketing & Telegram',
        desc: 'Laporan gangguan, review pendaftaran VPS/domain/hosting, dan notifikasi Telegram instan.',
        icon: '🎫',
        tag: 'workflow',
    },
];

const stack = [
    'SNMP v1/v2c/v3',
    'ICMP / RTT',
    'RouterOS API',
    'Telegram Bot API',
    'MariaDB',
    'poll 30 detik',
    '24/7 observability',
];

function uptimeColor(v) {
    if (v === null) return 'text-slate-400';
    if (v >= 99) return 'text-emerald-600';
    if (v >= 90) return 'text-amber-600';
    return 'text-red-600';
}

function clock(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    return Number.isNaN(d.getTime()) ? '—' : d.toLocaleTimeString('id-ID');
}
</script>

<template>
    <PublicLayout>
        <!-- ══════════ HERO ══════════ -->
        <section class="hero relative isolate overflow-hidden bg-slate-950 text-white">
            <div class="hero-grid" aria-hidden="true"></div>
            <div class="glow glow-a" aria-hidden="true"></div>
            <div class="glow glow-b" aria-hidden="true"></div>

            <div class="relative mx-auto grid max-w-6xl gap-12 px-4 py-20 sm:py-24 lg:grid-cols-2 lg:items-center">
                <div>
                    <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.2em] text-brand-200 backdrop-blur">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-400"></span>
                        Network Operation Center
                    </span>

                    <h1 class="mt-5 text-4xl font-black leading-[1.05] tracking-tight sm:text-5xl xl:text-6xl">
                        Pemantauan Jaringan
                        <span class="block bg-gradient-to-r from-sky-400 via-brand-400 to-indigo-400 bg-clip-text text-transparent">
                            Kabupaten Barito Utara
                        </span>
                    </h1>

                    <p class="mt-5 max-w-xl text-base leading-relaxed text-slate-300 sm:text-lg">
                        Satu pusat kendali infrastruktur digital Diskominfosandi —
                        observability real-time SNMP, ICMP, dan RouterOS API, dilengkapi
                        ticketing gangguan & pendaftaran layanan terintegrasi.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <Link
                            :href="route('lapor.index')"
                            class="group rounded-xl bg-gradient-to-r from-brand-500 to-sky-500 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-brand-600/30 transition hover:from-brand-400 hover:to-sky-400 hover:shadow-brand-500/40"
                        >
                            Lapor Gangguan
                            <span class="ml-1 inline-block transition group-hover:translate-x-0.5">→</span>
                        </Link>
                        <Link
                            :href="route('login')"
                            class="rounded-xl border border-white/20 bg-white/5 px-6 py-3 text-sm font-bold text-white backdrop-blur transition hover:border-white/40 hover:bg-white/10"
                        >
                            Masuk ke Dashboard
                        </Link>
                    </div>

                    <div class="mt-8 flex flex-wrap gap-2">
                        <span
                            v-for="s in stack"
                            :key="s"
                            class="rounded-md border border-white/10 bg-white/5 px-2.5 py-1 font-mono text-[11px] text-slate-300"
                        >
                            {{ s }}
                        </span>
                    </div>
                </div>

                <!-- Konsol live (data asli dari /status) -->
                <div class="relative lg:justify-self-end">
                    <div class="absolute -inset-4 rounded-3xl bg-gradient-to-tr from-brand-600/20 to-sky-500/20 blur-2xl" aria-hidden="true"></div>
                    <div class="relative w-full max-w-md rounded-2xl border border-white/10 bg-slate-900/80 shadow-2xl backdrop-blur">
                        <div class="flex items-center gap-2 border-b border-white/10 px-4 py-3">
                            <span class="h-2.5 w-2.5 rounded-full bg-red-400/80"></span>
                            <span class="h-2.5 w-2.5 rounded-full bg-amber-400/80"></span>
                            <span class="h-2.5 w-2.5 rounded-full bg-emerald-400/80"></span>
                            <span class="ml-2 font-mono text-[11px] text-slate-400">noc-monitor · live stream</span>
                            <span class="ml-auto inline-flex items-center gap-1.5 font-mono text-[10px] font-bold text-emerald-400">
                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-400"></span>
                                ONLINE
                            </span>
                        </div>
                        <div class="space-y-2.5 px-4 py-4 font-mono text-[13px] leading-relaxed">
                            <p class="text-slate-500">$ <span class="text-slate-300">monitor:poll --interval 30s</span></p>
                            <p class="text-slate-400">
                                perangkat_online <span class="ml-1 font-bold text-emerald-400">{{ live.up }}/{{ live.total }}</span>
                            </p>
                            <p class="text-slate-400">
                                status_down <span class="ml-1 font-bold text-red-400">{{ live.down }}</span>
                            </p>
                            <p class="text-slate-400">
                                uptime <span class="ml-1 font-bold text-sky-400">{{ live.uptime_pct === null ? '—' : live.uptime_pct + '%' }}</span>
                            </p>
                            <p class="text-slate-400">
                                tiket_terbuka <span class="ml-1 font-bold text-amber-400">{{ live.open_tickets }}</span>
                            </p>
                            <p class="text-slate-500">last_sync <span class="text-slate-400">{{ clock(live.updated_at) }}</span></p>
                            <p class="text-slate-500">$ <span class="caret">▋</span></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- pemisah bawah -->
            <div class="h-24 bg-gradient-to-b from-transparent to-slate-50" aria-hidden="true"></div>
        </section>

        <!-- ══════════ STATUS LIVE ══════════ -->
        <section class="mx-auto -mt-16 max-w-6xl px-4">
            <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-xl shadow-slate-900/5 backdrop-blur sm:p-7">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-600">Realtime</p>
                        <h2 class="mt-1 text-lg font-bold text-slate-900">Status Jaringan Live</h2>
                    </div>
                    <span class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">
                        <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span>
                        auto-refresh 15 detik
                    </span>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-4 transition hover:border-brand-200 hover:bg-brand-50/50">
                        <div class="flex items-center justify-between">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Perangkat</p>
                            <span class="text-lg">🖧</span>
                        </div>
                        <p class="mt-2 font-mono text-4xl font-black tabular-nums text-slate-900">{{ live.total }}</p>
                        <p class="mt-1 text-[11px] text-slate-400">terdaftar & aktif dipantau</p>
                    </div>

                    <div class="rounded-2xl border border-emerald-100 bg-emerald-50/70 p-4 transition hover:border-emerald-300">
                        <div class="flex items-center justify-between">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">Online</p>
                            <span class="text-lg">✅</span>
                        </div>
                        <p class="mt-2 font-mono text-4xl font-black tabular-nums text-emerald-700">{{ live.up }}</p>
                        <p class="mt-1 text-[11px] text-emerald-600/80">menjawab probe terakhir</p>
                    </div>

                    <div class="rounded-2xl border border-red-100 bg-red-50/70 p-4 transition hover:border-red-300">
                        <div class="flex items-center justify-between">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-red-700">Gangguan</p>
                            <span class="text-lg">⚠️</span>
                        </div>
                        <p class="mt-2 font-mono text-4xl font-black tabular-nums text-red-700">{{ live.down }}</p>
                        <p class="mt-1 text-[11px] text-red-600/80">perlu penanganan segera</p>
                    </div>

                    <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-4 transition hover:border-brand-200 hover:bg-brand-50/50">
                        <div class="flex items-center justify-between">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Uptime</p>
                            <span class="text-lg">📈</span>
                        </div>
                        <p class="mt-2 font-mono text-4xl font-black tabular-nums" :class="uptimeColor(live.uptime_pct)">
                            {{ live.uptime_pct === null ? '—' : live.uptime_pct + '%' }}
                        </p>
                        <p class="mt-1 text-[11px] text-slate-400">rata-rata perangkat online</p>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-100 bg-white px-4 py-3">
                    <p class="text-sm text-slate-600">
                        Tiket gangguan terbuka:
                        <b class="font-mono text-slate-900">{{ live.open_tickets }}</b>
                    </p>
                    <p class="font-mono text-xs text-slate-400">updated_at {{ clock(live.updated_at) }}</p>
                </div>
            </div>
        </section>

        <!-- ══════════ FITUR ══════════ -->
        <section class="mx-auto max-w-6xl px-4 py-16 sm:py-20">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-600">Kapabilitas</p>
                <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">
                    Dibangun untuk operasional jaringan modern
                </h2>
                <p class="mt-3 text-sm text-slate-500">
                    Probe berjalan otomatis tiap 30 detik dengan desain fail-closed —
                    satu metode gagal tidak pernah menghentikan pemantauan lainnya.
                </p>
            </div>

            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="f in features"
                    :key="f.title"
                    class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 transition duration-300 hover:-translate-y-1 hover:border-brand-300 hover:shadow-xl hover:shadow-brand-500/10"
                >
                    <div class="absolute -right-8 -top-8 h-24 w-24 rounded-full bg-brand-50 opacity-0 transition group-hover:opacity-100" aria-hidden="true"></div>
                    <div class="relative flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-indigo-500 text-xl shadow-lg shadow-brand-500/25">
                        {{ f.icon }}
                    </div>
                    <span class="mt-4 inline-block rounded-md bg-slate-100 px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        {{ f.tag }}
                    </span>
                    <h3 class="mt-2 font-bold text-slate-900">{{ f.title }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-500">{{ f.desc }}</p>
                </div>
            </div>
        </section>

        <!-- ══════════ CTA ══════════ -->
        <section class="relative isolate overflow-hidden bg-slate-950">
            <div class="hero-grid opacity-60" aria-hidden="true"></div>
            <div class="glow glow-c" aria-hidden="true"></div>
            <div class="relative mx-auto flex max-w-6xl flex-col items-start gap-6 px-4 py-14 sm:py-16 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-2xl font-black tracking-tight text-white sm:text-3xl">
                        Siap melaporkan gangguan?
                    </h2>
                    <p class="mt-2 max-w-xl text-sm text-slate-400">
                        Formulir laporan terbuka untuk umum, tanpa login — lengkapi dengan
                        lokasi & foto agar penanganan lebih cepat.
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <Link
                        :href="route('lapor.index')"
                        class="rounded-xl bg-gradient-to-r from-brand-500 to-sky-500 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-brand-600/30 transition hover:from-brand-400 hover:to-sky-400"
                    >
                        Buka Form Lapor →
                    </Link>
                    <Link
                        :href="route('register')"
                        class="rounded-xl border border-white/20 bg-white/5 px-6 py-3 text-sm font-bold text-white transition hover:border-white/40 hover:bg-white/10"
                    >
                        Daftar Akun
                    </Link>
                </div>
            </div>
        </section>

        <!-- ══════════ KONTAK ══════════ -->
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
                        <p class="mt-1 font-mono text-slate-500">SNMP · ICMP · RouterOS API — interval 30 detik</p>
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

<style scoped>
.hero-grid {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(to right, rgba(148, 163, 184, 0.09) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(148, 163, 184, 0.09) 1px, transparent 1px);
    background-size: 46px 46px;
    -webkit-mask-image: radial-gradient(ellipse at 50% 30%, #000 30%, transparent 78%);
    mask-image: radial-gradient(ellipse at 50% 30%, #000 30%, transparent 78%);
}

.glow {
    position: absolute;
    border-radius: 9999px;
    filter: blur(90px);
    opacity: 0.45;
    animation: drift 14s ease-in-out infinite alternate;
}

.glow-a {
    top: -8rem;
    left: 15%;
    width: 22rem;
    height: 22rem;
    background: radial-gradient(circle, rgba(37, 99, 235, 0.75), transparent 70%);
}

.glow-b {
    bottom: -6rem;
    right: 12%;
    width: 20rem;
    height: 20rem;
    background: radial-gradient(circle, rgba(6, 182, 212, 0.55), transparent 70%);
    animation-delay: -6s;
}

.glow-c {
    top: -10rem;
    right: 20%;
    width: 24rem;
    height: 24rem;
    background: radial-gradient(circle, rgba(79, 70, 229, 0.55), transparent 70%);
}

@keyframes drift {
    from {
        transform: translate3d(0, 0, 0) scale(1);
    }
    to {
        transform: translate3d(2.5rem, 1.5rem, 0) scale(1.12);
    }
}

.caret {
    animation: blink 1.1s steps(1) infinite;
}

@keyframes blink {
    50% {
        opacity: 0;
    }
}
</style>
