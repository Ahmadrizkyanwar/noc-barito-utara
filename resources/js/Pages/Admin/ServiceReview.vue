<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

const props = defineProps({
    registrations: { type: Object, required: true },
    statuses: { type: Object, required: true },
    types: { type: Object, required: true },
    packages: { type: Object, required: true },
    durations: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const typeTabs = [
    { key: '', label: 'Semua' },
    ...Object.entries(props.types).map(([key, label]) => ({ key, label })),
];

const statusTabs = [
    { key: '', label: 'Semua' },
    { key: 'pending', label: 'Menunggu' },
    { key: 'approved', label: 'Disetujui' },
    { key: 'rejected', label: 'Ditolak' },
];

const busy = ref(null);
const expanded = ref(null);
const notes = reactive({}); // admin_note per id

function filter(patch) {
    router.get(
        route('admin.services.index'),
        { ...props.filters, ...patch },
        { preserveState: true, preserveScroll: true }
    );
}

function decide(r, status) {
    busy.value = r.id;
    router.patch(
        route('admin.services.status', r.id),
        { status, admin_note: notes[r.id] ?? r.admin_note ?? '' },
        { preserveScroll: true, onFinish: () => (busy.value = null) }
    );
}

function statusClass(s) {
    return (
        {
            pending: 'bg-amber-100 text-amber-800',
            approved: 'bg-emerald-100 text-emerald-700',
            rejected: 'bg-red-100 text-red-700',
        }[s] ?? 'bg-slate-100 text-slate-600'
    );
}

const fmtDate = (d) => new Date(d).toLocaleString('id-ID');

function specOf(r) {
    const parts = [];
    if (r.domain_name) parts.push(r.domain_name);
    if (r.hosting_package) parts.push(props.packages[r.hosting_package] ?? r.hosting_package);
    if (r.duration) {
        const d = props.durations?.[r.type] ?? {};
        parts.push(d[r.duration] ?? `${r.duration} ${r.type === 'domain' ? 'tahun' : 'bulan'}`);
    }
    return parts.join(' · ') || '—';
}
</script>

<template>
    <AdminLayout>
        <div>
            <h1 class="text-xl font-bold">Review Pendaftaran Domain &amp; Hosting</h1>
            <p class="mt-1 text-sm text-slate-500">
                Pendaftaran dari user terdaftar — disetujui/ditolak beserta catatan.
            </p>
        </div>

        <!-- Filter tipe -->
        <div class="mt-5 flex flex-wrap gap-2">
            <button
                v-for="tab in typeTabs"
                :key="tab.key || 'all'"
                class="rounded-lg px-3 py-1.5 text-sm font-semibold transition"
                :class="filters.type === tab.key ? 'bg-brand-700 text-white' : 'bg-white text-slate-600 hover:bg-slate-100'"
                @click="filter({ type: tab.key })"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- Filter status -->
        <div class="mt-2 flex flex-wrap gap-2">
            <button
                v-for="tab in statusTabs"
                :key="tab.key || 'all-status'"
                class="rounded-lg px-3 py-1.5 text-sm font-semibold transition"
                :class="filters.status === tab.key ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-100'"
                @click="filter({ status: tab.key })"
            >
                {{ tab.label }}
            </button>
        </div>

        <p v-if="registrations.data.length === 0" class="mt-6 text-sm text-slate-500">
            Tidak ada pendaftaran pada filter ini.
        </p>

        <div class="mt-4 space-y-3">
            <div
                v-for="r in registrations.data"
                :key="r.id"
                class="rounded-xl border border-slate-200 bg-white p-4"
            >
                <!-- Ringkasan -->
                <div
                    class="flex cursor-pointer flex-wrap items-start justify-between gap-3"
                    @click="expanded = expanded === r.id ? null : r.id"
                >
                    <div>
                        <p class="text-sm font-bold">
                            <span class="mr-1.5 font-mono text-xs font-bold text-indigo-700">{{ r.code }}</span>
                            <span class="mr-1.5 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-semibold uppercase text-slate-500">
                                {{ types[r.type] ?? r.type }}
                            </span>
                            {{ r.instansi }} — {{ r.name }}
                            <span class="ml-1 font-normal text-slate-400">({{ r.user?.email }})</span>
                        </p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            NIP {{ r.nip }} · {{ r.jabatan }} · {{ specOf(r) }}
                            · {{ fmtDate(r.created_at) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="statusClass(r.status)">
                            {{ statuses[r.status] ?? r.status }}
                        </span>
                        <span class="text-xs text-slate-400">{{ expanded === r.id ? '▲' : '▼' }}</span>
                    </div>
                </div>

                <!-- Detail -->
                <div v-if="expanded === r.id" class="mt-3 border-t border-slate-100 pt-3">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Spesifikasi</p>
                            <p class="mt-1 text-sm text-slate-700">{{ specOf(r) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reviewer</p>
                            <p class="mt-1 text-sm text-slate-600">
                                <template v-if="r.reviewer">
                                    {{ r.reviewer.name }} · {{ fmtDate(r.reviewed_at) }}
                                </template>
                                <template v-else>—</template>
                            </p>
                        </div>
                    </div>

                    <div class="mt-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Penggunaan Untuk</p>
                        <p class="mt-1 whitespace-pre-wrap text-sm text-slate-700">{{ r.purpose }}</p>
                    </div>

                    <!-- Dokumen pendukung dari user -->
                    <div v-if="r.supporting_document" class="mt-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dokumen Pendukung</p>
                        <div class="mt-1 flex flex-wrap items-center gap-3 rounded-lg bg-slate-50 px-3 py-2">
                            <span class="text-sm text-slate-700">
                                📎 Terunggah
                                <span v-if="r.supporting_document_uploaded_at" class="text-slate-500">
                                    · {{ fmtDate(r.supporting_document_uploaded_at) }}
                                </span>
                            </span>
                            <a
                                :href="route('service.document', r.id)"
                                class="text-sm font-semibold text-brand-700 hover:underline"
                            >
                                Unduh ↓
                            </a>
                        </div>
                    </div>

                    <!-- Aksi (hanya pending) -->
                    <div v-if="r.status === 'pending'" class="mt-4 border-t border-slate-100 pt-3">
                        <label class="block text-xs font-semibold text-slate-600">Catatan admin (opsional)</label>
                        <textarea
                            v-model="notes[r.id]"
                            rows="2"
                            maxlength="1000"
                            placeholder="Mis. disetujui, hubungi NOC untuk kelanjutan…"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                        ></textarea>
                        <div class="mt-3 flex gap-2">
                            <button
                                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-60"
                                :disabled="busy === r.id"
                                @click="decide(r, 'approved')"
                            >
                                Setujui
                            </button>
                            <button
                                class="rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-60"
                                :disabled="busy === r.id"
                                @click="decide(r, 'rejected')"
                            >
                                Tolak
                            </button>
                        </div>
                    </div>

                    <p v-else-if="r.admin_note" class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">
                        Catatan: {{ r.admin_note }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="registrations.last_page > 1" class="mt-4 flex items-center justify-between text-sm">
            <Link
                v-if="registrations.prev_page_url"
                :href="registrations.prev_page_url"
                class="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold hover:bg-slate-100"
            >
                ← Sebelumnya
            </Link>
            <span v-else></span>
            <span class="text-slate-500">Halaman {{ registrations.current_page }} / {{ registrations.last_page }}</span>
            <Link
                v-if="registrations.next_page_url"
                :href="registrations.next_page_url"
                class="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold hover:bg-slate-100"
            >
                Berikutnya →
            </Link>
            <span v-else></span>
        </div>
    </AdminLayout>
</template>
