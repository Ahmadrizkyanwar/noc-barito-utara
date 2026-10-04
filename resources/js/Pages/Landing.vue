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

const place = 'Muara Teweh · Kalimantan Tengah';

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
        <section class="relative isolate overflow-hidden bg-slate-950 text-white">
            <!-- Ornamen motif Dayak (pita atas) -->
            <svg class="motif-band absolute inset-x-0 top-0 h-7 w-full" aria-hidden="true">
                <defs>
                    <pattern id="motif-hero" width="56" height="28" patternUnits="userSpaceOnUse">
                        <path d="M0 28 L14 2 L28 28 L42 2 L56 28" fill="none" stroke="#d97706" stroke-width="1.5" opacity=".85" />
                        <path d="M14 9 L20 15.5 L14 22 L8 15.5 Z" fill="#b91c1c" />
                        <path d="M42 9 L48 15.5 L42 22 L36 15.5 Z" fill="#d97706" />
                        <circle cx="28" cy="9" r="1.8" fill="#f7f1e6" opacity=".8" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#motif-hero)" />
            </svg>

            <div class="hero-grid" aria-hidden="true"></div>
            <div class="hero-glow" aria-hidden="true"></div>

            <div class="relative mx-auto grid max-w-6xl gap-12 px-4 pb-16 pt-20 sm:pt-24 lg:grid-cols-2 lg:items-center">
                <div>
                    <span class="inline-flex items-center gap-2 rounded-full border border-dayak-gold/40 bg-dayak-gold/10 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.2em] text-dayak-gold">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-400"></span>
                        Network Operation Center
                    </span>

                    <h1 class="mt-5 text-4xl font-black leading-[1.08] tracking-tight sm:text-5xl">
                        Pemantauan Jaringan
                        <span class="block text-brand-300">Kabupaten Barito Utara</span>
                    </h1>

                    <!-- Divider motif kecil -->
                    <svg class="mt-4 h-4 w-40" aria-hidden="true">
                        <defs>
                            <pattern id="motif-rule" width="28" height="16" patternUnits="userSpaceOnUse">
                                <path d="M0 16 L7 3 L14 16 L21 3 L28 16" fill="none" stroke="#d97706" stroke-width="1.5" />
                                <path d="M7 7 L10.5 10.5 L7 14 L3.5 10.5 Z" fill="#b91c1c" />
                            </pattern>
                        </defs>
                        <rect width="100%" height="100%" fill="url(#motif-rule)" />
                    </svg>

                    <p class="mt-4 font-mono text-xs uppercase tracking-[0.25em] text-slate-400">
                        {{ place }}
                    </p>

                    <p class="mt-5 max-w-xl text-base leading-relaxed text-slate-300">
                        Satu pusat kendali infrastruktur digital Diskominfosandi —
                        pemantauan real-time SNMP, ICMP, dan RouterOS API, dilengkapi
                        ticketing gangguan & pendaftaran layanan terintegrasi.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <Link
                            :href="route('lapor.index')"
                            class="group rounded-xl bg-brand-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-black/30 transition hover:bg-brand-500"
                        >
                            Lapor Gangguan
                            <span class="ml-1 inline-block transition group-hover:translate-x-0.5">→</span>
                        </Link>
                        <Link
                            :href="route('login')"
                            class="rounded-xl border border-slate-700 bg-slate-900/60 px-6 py-3 text-sm font-bold text-slate-200 transition hover:border-slate-500 hover:text-white"
                        >
                            Masuk ke Dashboard
                        </Link>
                    </div>

                    <div class="mt-8 flex flex-wrap gap-2">
                        <span
                            v-for="s in stack"
                            :key="s"
                            class="rounded-md border border-slate-800 bg-slate-900 px-2.5 py-1 font-mono text-[11px] text-slate-400"
                        >
                            {{ s }}
                        </span>
                    </div>
                </div>

                <!-- Konsol live (data asli dari /status) -->
                <div class="relative lg:justify-self-end">
                    <div class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl shadow-black/40">
                        <div class="flex items-center gap-2 border-b border-slate-800 bg-slate-950/60 px-4 py-3">
                            <span class="h-2.5 w-2.5 rounded-full bg-slate-700"></span>
                            <span class="h-2.5 w-2.5 rounded-full bg-slate-700"></span>
                            <span class="h-2.5 w-2.5 rounded-full bg-slate-700"></span>
                            <span class="ml-2 font-mono text-[11px] text-slate-500">noc-monitor · live stream</span>
                            <span class="ml-auto inline-flex items-center gap-1.5 font-mono text-[10px] font-bold text-emerald-500">
                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>
                                ONLINE
                            </span>
                        </div>
                        <div class="space-y-2.5 px-4 py-4 font-mono text-[13px] leading-relaxed">
                            <p class="text-slate-500">$ <span class="text-slate-300">monitor:poll --interval 30s</span></p>
                            <p class="text-slate-400">
                                perangkat_online <span class="ml-1 font-bold text-emerald-500">{{ live.up }}/{{ live.total }}</span>
                            </p>
                            <p class="text-slate-400">
                                status_down <span class="ml-1 font-bold text-red-400">{{ live.down }}</span>
                            </p>
                            <p class="text-slate-400">
                                uptime <span class="ml-1 font-bold text-brand-300">{{ live.uptime_pct === null ? '—' : live.uptime_pct + '%' }}</span>
                            </p>
                            <p class="text-slate-400">
                                tiket_terbuka <span class="ml-1 font-bold text-amber-500">{{ live.open_tickets }}</span>
                            </p>
                            <p class="text-slate-500">last_sync <span class="text-slate-400">{{ clock(live.updated_at) }}</span></p>
                            <p class="text-slate-500">$ <span class="caret">▋</span></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- pemisah bawah -->
            <div class="h-20 bg-gradient-to-b from-transparent to-slate-50" aria-hidden="true"></div>
        </section>

        <!-- ══════════ STATUS LIVE ══════════ -->
        <section class="mx-auto -mt-14 max-w-6xl px-4">
            <div class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-900/5 sm:p-7">
                <!-- ornamen sudut motif -->
                <svg class="absolute right-0 top-0 h-16 w-16 text-dayak-gold/60" aria-hidden="true" viewBox="0 0 64 64" fill="none">
                    <path d="M64 0 L64 24 L40 0 Z" fill="currentColor" opacity=".35" />
                    <path d="M64 32 L64 48 L48 32 Z" fill="currentColor" opacity=".55" />
                    <path d="M32 0 L48 0 L40 10 Z" fill="#b91c1c" opacity=".4" />
                </svg>

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
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Perangkat</p>
                            <span class="text-lg">🖧</span>
                        </div>
                        <p class="mt-2 font-mono text-4xl font-black tabular-nums text-slate-900">{{ live.total }}</p>
                        <p class="mt-1 text-[11px] text-slate-400">terdaftar &amp; aktif dipantau</p>
                    </div>

                    <div class="rounded-2xl border border-emerald-200/70 bg-emerald-50 p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">Online</p>
                            <span class="text-lg">✅</span>
                        </div>
                        <p class="mt-2 font-mono text-4xl font-black tabular-nums text-emerald-700">{{ live.up }}</p>
                        <p class="mt-1 text-[11px] text-emerald-700/70">menjawab probe terakhir</p>
                    </div>

                    <div class="rounded-2xl border border-red-200/70 bg-red-50 p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-red-700">Gangguan</p>
                            <span class="text-lg">⚠️</span>
                        </div>
                        <p class="mt-2 font-mono text-4xl font-black tabular-nums text-red-700">{{ live.down }}</p>
                        <p class="mt-1 text-[11px] text-red-700/70">perlu penanganan segera</p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
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

                <div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
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
                <p class="mt-3 text-sm leading-relaxed text-slate-500">
                    Probe berjalan otomatis tiap 30 detik dengan desain fail-closed —
                    satu metode gagal tidak pernah menghentikan pemantauan lainnya.
                </p>
            </div>

            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="f in features"
                    :key="f.title"
                    class="rounded-2xl border border-slate-200 bg-white p-5 transition duration-300 hover:-translate-y-1 hover:border-brand-300 hover:shadow-lg hover:shadow-slate-900/5"
                >
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-600 text-xl shadow-md shadow-brand-600/20">
                        {{ f.icon }}
                    </div>
                    <span class="mt-4 inline-block rounded-md bg-dayak-cream px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider text-dayak-gold">
                        {{ f.tag }}
                    </span>
                    <h3 class="mt-2 font-bold text-slate-900">{{ f.title }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-500">{{ f.desc }}</p>
                </div>
            </div>
        </section>

        <!-- ══════════ CTA + motif ══════════ -->
        <section class="relative isolate overflow-hidden bg-slate-950">
            <!-- Pita motif Dayak pemisah -->
            <svg class="motif-band block h-8 w-full" aria-hidden="true">
                <defs>
                    <pattern id="motif-cta" width="56" height="32" patternUnits="userSpaceOnUse">
                        <path d="M0 32 L14 6 L28 32 L42 6 L56 32" fill="none" stroke="#d97706" stroke-width="1.5" opacity=".9" />
                        <path d="M14 12 L20 18 L14 24 L8 18 Z" fill="#b91c1c" />
                        <path d="M42 12 L48 18 L42 24 L36 18 Z" fill="#d97706" />
                        <path d="M28 6 L31 9 L28 12 L25 9 Z" fill="#f7f1e6" opacity=".75" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#motif-cta)" />
            </svg>

            <div class="relative mx-auto flex max-w-6xl flex-col items-start gap-6 px-4 py-14 sm:py-16 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-2xl font-black tracking-tight text-white sm:text-3xl">
                        Siap melaporkan gangguan?
                    </h2>
                    <p class="mt-2 max-w-xl text-sm text-slate-400">
                        Formulir laporan terbuka untuk umum, tanpa login — lengkapi dengan
                        lokasi &amp; foto agar penanganan lebih cepat.
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <Link
                        :href="route('lapor.index')"
                        class="rounded-xl bg-brand-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-black/30 transition hover:bg-brand-500"
                    >
                        Buka Form Lapor →
                    </Link>
                    <Link
                        :href="route('register')"
                        class="rounded-xl border border-slate-700 bg-slate-900/60 px-6 py-3 text-sm font-bold text-slate-200 transition hover:border-slate-500 hover:text-white"
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

                <!-- Pita motif tipis di atas footer -->
                <svg class="mt-8 h-5 w-full text-dayak-gold/70" aria-hidden="true">
                    <defs>
                        <pattern id="motif-foot" width="36" height="20" patternUnits="userSpaceOnUse">
                            <path d="M0 20 L9 4 L18 20 L27 4 L36 20" fill="none" stroke="currentColor" stroke-width="1.2" />
                            <path d="M9 8 L12.5 11.5 L9 15 L5.5 11.5 Z" fill="#b91c1c" opacity=".7" />
                            <path d="M27 8 L30.5 11.5 L27 15 L23.5 11.5 Z" fill="currentColor" opacity=".7" />
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#motif-foot)" />
                </svg>
            </div>
        </section>
    </PublicLayout>
</template>

<style scoped>
.hero-grid {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(to right, rgba(148, 163, 184, 0.06) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(148, 163, 184, 0.06) 1px, transparent 1px);
    background-size: 46px 46px;
    -webkit-mask-image: radial-gradient(ellipse at 50% 30%, #000 30%, transparent 78%);
    mask-image: radial-gradient(ellipse at 50% 30%, #000 30%, transparent 78%);
}

/* Satu cahaya lembut saja (biru) — tanpa gradasi warna bentrok */
.hero-glow {
    position: absolute;
    top: -10rem;
    left: 18%;
    width: 30rem;
    height: 30rem;
    border-radius: 9999px;
    background: radial-gradient(circle, rgba(37, 99, 235, 0.35), transparent 70%);
    filter: blur(100px);
    opacity: 0.5;
}

.motif-band {
    pointer-events: none;
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
