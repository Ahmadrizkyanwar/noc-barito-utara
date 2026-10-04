<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

const props = defineProps({
    requests: { type: Object, required: true },
    statuses: { type: Object, required: true },
    ports: { type: Object, required: true },
    operatingSystems: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const tabs = [
    { key: '', label: 'Semua' },
    { key: 'pending', label: 'Menunggu' },
    { key: 'approved', label: 'Disetujui' },
    { key: 'rejected', label: 'Ditolak' },
];

const busy = ref(null);
const expanded = ref(null);
const notes = reactive({}); // admin_note per request id
const credFiles = reactive({}); // file kredensial terpilih per request id

function filter(status) {
    router.get(route('admin.vps.index'), status ? { status } : {}, {
        preserveState: true,
        preserveScroll: true,
    });
}

function onCredChange(req, e) {
    credFiles[req.id] = e.target.files?.[0] ?? null;
}

function uploadCred(req) {
    const file = credFiles[req.id];
    if (!file) return;

    busy.value = req.id;
    router.post(
        route('admin.vps.credentials.upload', req.id),
        { credential: file },
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => delete credFiles[req.id],
            onFinish: () => (busy.value = null),
        }
    );
}

function decide(req, status) {
    busy.value = req.id;
    router.patch(
        route('admin.vps.status', req.id),
        { status, admin_note: notes[req.id] ?? req.admin_note ?? '' },
        {
            preserveScroll: true,
            onFinish: () => (busy.value = null),
        }
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
const portLabel = (key) => (props.ports[key] ? `${key} · ${props.ports[key]}` : key);
const osLabel = (r) => {
    if (!r.os) return '—';
    if (r.os === 'lainnya') return r.os_other || 'Lainnya';
    return props.operatingSystems[r.os] ?? r.os;
};
</script>

<template>
    <AdminLayout>
        <div>
            <h1 class="text-xl font-bold">Review Request VPS</h1>
            <p class="mt-1 text-sm text-slate-500">
                Persetujuan request VPS dari user terdaftar — disetujui/ditolak beserta catatan.
            </p>
        </div>

        <!-- Filter status -->
        <div class="mt-5 flex flex-wrap gap-2">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                class="rounded-lg px-3 py-1.5 text-sm font-semibold transition"
                :class="filters.status === tab.key ? 'bg-brand-700 text-white' : 'bg-white text-slate-600 hover:bg-slate-100'"
                @click="filter(tab.key)"
            >
                {{ tab.label }}
            </button>
        </div>

        <p v-if="requests.data.length === 0" class="mt-6 text-sm text-slate-500">
            Tidak ada request VPS pada filter ini.
        </p>

        <div class="mt-4 space-y-3">
            <div
                v-for="r in requests.data"
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
                            {{ r.instansi }} — {{ r.name }}
                            <span class="ml-1 font-normal text-slate-400">({{ r.user?.email }})</span>
                        </p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            NIP {{ r.nip }} · {{ r.jabatan }} ·
                            {{ r.cores }} core / {{ r.ram_gb }} GB / {{ r.public_ips }} IP publik
                            <span v-if="r.os"> · {{ osLabel(r) }}</span>
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
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Service PORT</p>
                            <p class="mt-1 text-sm">
                                <span
                                    v-for="p in r.ports"
                                    :key="p"
                                    class="mr-1 mb-1 inline-block rounded bg-slate-100 px-2 py-0.5 font-mono text-xs"
                                >{{ portLabel(p) }}</span>
                                <span
                                    v-if="r.custom_ports"
                                    class="mr-1 mb-1 inline-block rounded bg-indigo-50 px-2 py-0.5 font-mono text-xs text-indigo-700"
                                >
                                    tambahan: {{ r.custom_ports }}
                                </span>
                                <span v-if="!r.ports?.length && !r.custom_ports" class="text-sm text-slate-400">—</span>
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sistem Operasi</p>
                            <p class="mt-1 text-sm text-slate-700">{{ osLabel(r) }}</p>
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
                                :href="route('vps.document', r.id)"
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
                            placeholder="Mis. disetujui, hubungi NOC untuk serah terima…"
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

                    <!-- Dokumen kredensial -->
                    <div v-if="r.status === 'approved'" class="mt-4 border-t border-slate-100 pt-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kredensial VPS</p>

                        <div
                            v-if="r.credential_file"
                            class="mt-2 flex flex-wrap items-center gap-3 rounded-lg bg-emerald-50 px-3 py-2"
                        >
                            <span class="text-sm text-emerald-800">
                                📄 Dokumen terunggah
                                <span v-if="r.credential_uploaded_at" class="text-emerald-600">
                                    · {{ fmtDate(r.credential_uploaded_at) }}
                                </span>
                            </span>
                            <a
                                :href="route('vps.credentials', r.id)"
                                class="text-sm font-semibold text-emerald-700 hover:underline"
                            >
                                Unduh ↓
                            </a>
                        </div>

                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <input
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png,.txt"
                                class="text-sm"
                                @change="onCredChange(r, $event)"
                            >
                            <button
                                class="rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-bold text-white hover:bg-brand-800 disabled:opacity-60"
                                :disabled="!credFiles[r.id] || busy === r.id"
                                @click="uploadCred(r)"
                            >
                                {{ busy === r.id && credFiles[r.id] ? 'Mengunggah…' : 'Upload kredensial' }}
                            </button>
                        </div>
                        <p class="mt-1 text-[11px] text-slate-400">
                            PDF/JPG/PNG/TXT maks 5 MB — pemilik request otomatis menerima notifikasi.
                        </p>
                    </div>
                    <p v-else class="mt-3 text-xs text-slate-400">
                        Kredensial dapat diunggah setelah request disetujui.
                    </p>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="requests.last_page > 1" class="mt-4 flex items-center justify-between text-sm">
            <Link
                v-if="requests.prev_page_url"
                :href="requests.prev_page_url"
                class="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold hover:bg-slate-100"
            >
                ← Sebelumnya
            </Link>
            <span v-else></span>
            <span class="text-slate-500">Halaman {{ requests.current_page }} / {{ requests.last_page }}</span>
            <Link
                v-if="requests.next_page_url"
                :href="requests.next_page_url"
                class="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold hover:bg-slate-100"
            >
                Berikutnya →
            </Link>
            <span v-else></span>
        </div>
    </AdminLayout>
</template>
