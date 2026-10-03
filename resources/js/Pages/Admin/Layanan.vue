<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    tickets: { type: Object, required: true },
    stats: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Object, default: () => ({}) },
    allStatuses: { type: Object, default: () => ({}) },
});

function applyFilter(key, value) {
    const q = { ...props.filters };
    if (value) {
        q[key] = value;
    } else {
        delete q[key];
    }
    router.get(route('admin.tickets.index'), q, { preserveState: true, replace: true });
}

function goTo(page) {
    if (page < 1 || page > props.tickets.last_page) return;
    router.get(route('admin.tickets.index'), { ...props.filters, page }, { preserveScroll: true });
}

const isVps = (t) => t.kind === 'vps';

// Judul baris: request VPS disusun dari instansi + pelapor
const titleOf = (t) =>
    isVps(t) ? `Request VPS — ${t.instansi} · ${t.reporter_name}` : t.title;

const detailHref = (t) =>
    isVps(t) ? route('admin.vps.index') : route('admin.tickets.show', t.id);

const statusClass = (s) =>
    ({
        open: 'bg-blue-100 text-blue-700',
        proses: 'bg-amber-100 text-amber-800',
        selesai: 'bg-emerald-100 text-emerald-700',
        pending: 'bg-indigo-100 text-indigo-700',
        approved: 'bg-emerald-100 text-emerald-700',
        rejected: 'bg-red-100 text-red-700',
    })[s] ?? 'bg-slate-100 text-slate-600';
</script>

<template>
    <AdminLayout>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold">Layanan — Ticketing Gangguan</h1>
                <p class="text-sm text-slate-500">Kelola laporan gangguan dari masyarakat dan user</p>
            </div>
        </div>

        <!-- Statistik -->
        <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Baru</p>
                <p class="mt-1 text-3xl font-black text-blue-700">{{ stats.open }}</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Diproses</p>
                <p class="mt-1 text-3xl font-black text-amber-700">{{ stats.proses }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Selesai</p>
                <p class="mt-1 text-3xl font-black text-emerald-700">{{ stats.selesai }}</p>
            </div>
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700">Request VPS</p>
                <p class="mt-1 text-3xl font-black text-indigo-700">{{ stats.vps_pending ?? 0 }}</p>
            </div>
        </div>

        <!-- Filter -->
        <div class="mt-4 flex flex-wrap gap-2">
            <input
                type="search"
                :value="filters.q ?? ''"
                placeholder="Cari kode/judul/pelapor…"
                class="w-64 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                @input="applyFilter('q', $event.target.value)"
            >
            <select
                :value="filters.status ?? ''"
                class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                @change="applyFilter('status', $event.target.value)"
            >
                <option value="">Semua status</option>
                <option v-for="(label, key) in allStatuses" :key="key" :value="key">{{ label }}</option>
            </select>
        </div>

        <!-- Daftar -->
        <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Judul</th>
                        <th class="px-4 py-3">Kategori</th>
                        <th class="px-4 py-3">Pelapor</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Tanggal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="t in tickets.data" :key="t.kind + '-' + t.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <Link
                                :href="detailHref(t)"
                                class="font-mono text-xs font-bold hover:underline"
                                :class="isVps(t) ? 'text-indigo-700' : 'text-brand-700'"
                            >
                                {{ t.code }}
                            </Link>
                        </td>
                        <td class="px-4 py-3">
                            <Link :href="detailHref(t)" class="font-semibold hover:text-brand-700">
                                {{ titleOf(t) }}
                            </Link>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded px-1.5 py-0.5 text-[11px] font-bold"
                                :class="isVps(t) ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600'"
                            >{{ t.category }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ t.reporter_name ?? t.reporter?.name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-bold" :class="statusClass(t.status)">
                                {{ allStatuses[t.status] ?? statuses[t.status] ?? t.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500">
                            {{ new Date(t.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) }}
                        </td>
                    </tr>
                    <tr v-if="tickets.data.length === 0">
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">Tidak ada data.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="tickets.last_page > 1" class="mt-4 flex items-center justify-between text-sm">
            <p class="text-slate-500">
                Total {{ tickets.total }} tiket · halaman {{ tickets.current_page }}/{{tickets.last_page}}
            </p>
            <div class="flex gap-2">
                <button
                    class="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold disabled:opacity-40"
                    :disabled="tickets.current_page <= 1"
                    @click="goTo(tickets.current_page - 1)"
                >
                    ← Sebelumnya
                </button>
                <button
                    class="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold disabled:opacity-40"
                    :disabled="tickets.current_page >= tickets.last_page"
                    @click="goTo(tickets.current_page + 1)"
                >
                    Berikutnya →
                </button>
            </div>
        </div>
    </AdminLayout>
</template>
