<script setup>
import UserLayout from '@/Layouts/UserLayout.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    type: { type: String, required: true },
    label: { type: String, required: true },
    durations: { type: Object, default: () => ({}) },
    packages: { type: Object, default: () => ({}) },
    canRequest: { type: Boolean, required: true },
    accountStatus: { type: String, required: true },
});

const isHosting = computed(() => props.type === 'hosting');

const auth = computed(() => usePage().props.auth?.user);

const form = useForm({
    name: auth.value?.name ?? '',
    nip: '',
    jabatan: '',
    instansi: '',
    domain_name: '',
    hosting_package: isHosting.value ? Object.keys(props.packages)[0] ?? '' : '',
    duration: Number(Object.keys(props.durations)[0] ?? 1),
    purpose: '',
    supporting_document: null,
});

const docInput = ref(null);
const docName = ref('');
const docSize = ref(0);
const dragging = ref(false);

function setDoc(file) {
    form.supporting_document = file;
    docName.value = file ? file.name : '';
    docSize.value = file ? file.size : 0;
}

function onDocChange(e) {
    setDoc(e.target.files?.[0] ?? null);
}

function onDocDrop(e) {
    dragging.value = false;
    setDoc(e.dataTransfer?.files?.[0] ?? null);
}

function clearDoc() {
    setDoc(null);
    if (docInput.value) docInput.value.value = '';
}

function fmtSize(bytes) {
    if (bytes >= 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    return Math.max(1, Math.round(bytes / 1024)) + ' KB';
}

const accountLabel = computed(
    () =>
        ({
            pending: 'Menunggu validasi admin',
            approved: 'Disetujui',
            rejected: 'Ditolak',
        })[props.accountStatus] ?? props.accountStatus
);

function submit() {
    form.post(route(props.type + '.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('nip', 'jabatan', 'instansi', 'domain_name', 'purpose');
            clearDoc();
        },
    });
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
</script>

<template>
    <UserLayout>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold">{{ label }}</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Ajukan kebutuhan {{ type === 'domain' ? 'nama domain' : 'hosting' }} untuk instansi Anda.
                    Fitur terbuka untuk akun yang sudah disetujui admin.
                </p>
            </div>
            <span
                class="rounded-full px-3 py-1 text-xs font-bold"
                :class="statusClass(accountStatus)"
            >
                Akun: {{ accountLabel }}
            </span>
        </div>

        <!-- Terkunci -->
        <div
            v-if="!canRequest"
            class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-6"
        >
            <h2 class="font-bold text-amber-800">🔒 Fitur terkunci</h2>
            <p class="mt-1 text-sm text-amber-700">
                <template v-if="accountStatus === 'pending'">
                    Registrasi Anda masih <b>menunggu validasi</b> admin/operator.
                    Setelah disetujui, formulir {{ label.toLowerCase() }} akan terbuka di halaman ini.
                </template>
                <template v-else>
                    Registrasi Anda <b>ditolak</b> oleh admin. Silakan hubungi admin
                    untuk informasi lebih lanjut.
                </template>
            </p>
        </div>

        <!-- Formulir -->
        <form
            v-else
            class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
            @submit.prevent="submit"
        >
            <h2 class="font-bold">Formulir {{ label }}</h2>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="svc-name" class="block text-sm font-semibold">Nama *</label>
                    <input
                        id="svc-name"
                        v-model="form.name"
                        type="text"
                        required
                        maxlength="100"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                        :class="{ 'border-red-400': form.errors.name }"
                    >
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label for="svc-nip" class="block text-sm font-semibold">NIP *</label>
                    <input
                        id="svc-nip"
                        v-model="form.nip"
                        type="text"
                        required
                        maxlength="30"
                        placeholder="Contoh: 198701012010011001"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                        :class="{ 'border-red-400': form.errors.nip }"
                    >
                    <p v-if="form.errors.nip" class="mt-1 text-xs text-red-600">{{ form.errors.nip }}</p>
                </div>

                <div>
                    <label for="svc-jabatan" class="block text-sm font-semibold">Jabatan *</label>
                    <input
                        id="svc-jabatan"
                        v-model="form.jabatan"
                        type="text"
                        required
                        maxlength="100"
                        placeholder="Contoh: Analis Kebijakan"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                        :class="{ 'border-red-400': form.errors.jabatan }"
                    >
                    <p v-if="form.errors.jabatan" class="mt-1 text-xs text-red-600">{{ form.errors.jabatan }}</p>
                </div>

                <div>
                    <label for="svc-instansi" class="block text-sm font-semibold">Instansi *</label>
                    <input
                        id="svc-instansi"
                        v-model="form.instansi"
                        type="text"
                        required
                        maxlength="150"
                        placeholder="Contoh: Dinas Pendidikan"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                        :class="{ 'border-red-400': form.errors.instansi }"
                    >
                    <p v-if="form.errors.instansi" class="mt-1 text-xs text-red-600">{{ form.errors.instansi }}</p>
                </div>

                <!-- Spesifik tipe -->
                <div>
                    <label for="svc-package" class="block text-sm font-semibold">
                        {{ isHosting ? 'Paket Hosting *' : 'Nama Domain *' }}
                    </label>

                    <template v-if="isHosting">
                        <select
                            id="svc-package"
                            v-model="form.hosting_package"
                            required
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                            :class="{ 'border-red-400': form.errors.hosting_package }"
                        >
                            <option v-for="(label2, key) in packages" :key="key" :value="key">{{ label2 }}</option>
                        </select>
                        <p v-if="form.errors.hosting_package" class="mt-1 text-xs text-red-600">
                            {{ form.errors.hosting_package }}
                        </p>
                    </template>

                    <template v-else>
                        <input
                            id="svc-package"
                            v-model.trim="form.domain_name"
                            type="text"
                            required
                            maxlength="255"
                            placeholder="Contoh: diskominfosandi.go.id"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                            :class="{ 'border-red-400': form.errors.domain_name }"
                        >
                        <p v-if="form.errors.domain_name" class="mt-1 text-xs text-red-600">{{ form.errors.domain_name }}</p>
                    </template>
                </div>

                <div>
                    <label for="svc-duration" class="block text-sm font-semibold">Durasi *</label>
                    <select
                        id="svc-duration"
                        v-model.number="form.duration"
                        required
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                        :class="{ 'border-red-400': form.errors.duration }"
                    >
                        <option v-for="(label2, key) in durations" :key="key" :value="Number(key)">{{ label2 }}</option>
                    </select>
                    <p v-if="form.errors.duration" class="mt-1 text-xs text-red-600">{{ form.errors.duration }}</p>
                </div>

                <div v-if="isHosting" class="sm:col-span-2">
                    <label for="svc-domain" class="block text-sm font-semibold">Nama Domain (opsional)</label>
                    <p class="mt-0.5 text-xs text-slate-500">Domain yang akan diarahkan ke hosting ini, bila sudah ada.</p>
                    <input
                        id="svc-domain"
                        v-model.trim="form.domain_name"
                        type="text"
                        maxlength="255"
                        placeholder="Contoh: portal.baritoutarakab.go.id"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                        :class="{ 'border-red-400': form.errors.domain_name }"
                    >
                    <p v-if="form.errors.domain_name" class="mt-1 text-xs text-red-600">{{ form.errors.domain_name }}</p>
                </div>
            </div>

            <div class="mt-4">
                <label for="svc-purpose" class="block text-sm font-semibold">Penggunaan Untuk *</label>
                <textarea
                    id="svc-purpose"
                    v-model="form.purpose"
                    required
                    rows="3"
                    maxlength="1000"
                    placeholder="Jelaskan tujuan penggunaan…"
                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                    :class="{ 'border-red-400': form.errors.purpose }"
                ></textarea>
                <p v-if="form.errors.purpose" class="mt-1 text-xs text-red-600">{{ form.errors.purpose }}</p>
            </div>

            <!-- Dokumen pendukung -->
            <div class="mt-4">
                <span class="block text-sm font-semibold">Dokumen Pendukung (opsional)</span>
                <p class="mt-0.5 text-xs text-slate-500">
                    Surat permohonan / pendukung lain — PDF, DOC, DOCX, JPG, atau PNG, maks 5 MB.
                </p>

                <div
                    v-if="docName"
                    class="mt-2 flex flex-wrap items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3"
                >
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white text-xl shadow-sm">📄</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-emerald-800">{{ docName }}</p>
                        <p class="text-xs text-emerald-600">{{ fmtSize(docSize) }} · siap dikirim</p>
                    </div>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-emerald-300 bg-white px-3 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-100"
                            @click="docInput?.click()"
                        >
                            Ganti
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-500 hover:bg-slate-50"
                            @click="clearDoc"
                        >
                            Hapus
                        </button>
                    </div>
                </div>

                <label
                    v-else
                    for="svc-doc"
                    class="mt-2 flex cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed px-4 py-7 text-center transition"
                    :class="dragging
                        ? 'border-brand-500 bg-brand-50'
                        : 'border-slate-300 bg-slate-50 hover:border-brand-400 hover:bg-brand-50/50'"
                    @dragover.prevent="dragging = true"
                    @dragleave.prevent="dragging = false"
                    @drop.prevent="onDocDrop"
                >
                    <span class="text-3xl">📎</span>
                    <span class="text-sm font-semibold text-brand-700">Klik untuk memilih file</span>
                    <span class="text-xs text-slate-500">atau seret &amp; letakkan file di sini</span>
                    <span class="mt-1 rounded-full bg-white px-3 py-1 text-[11px] font-semibold text-slate-500 shadow-sm">
                        PDF · DOC · DOCX · JPG · PNG — maks 5 MB
                    </span>
                </label>

                <input
                    id="svc-doc"
                    ref="docInput"
                    type="file"
                    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                    class="sr-only"
                    @change="onDocChange"
                >
                <p v-if="form.errors.supporting_document" class="mt-1 text-xs text-red-600">
                    {{ form.errors.supporting_document }}
                </p>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="mt-5 w-full rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-brand-800 disabled:opacity-60 sm:w-auto"
            >
                {{ form.processing ? 'Mengirim…' : 'Kirim Pendaftaran' }}
            </button>

            <p class="mt-4 text-sm text-slate-500">
                Riwayat & status pendaftaran Anda tampil di
                <Link :href="route('dashboard')" class="font-semibold text-brand-700 hover:underline">Dashboard</Link>.
            </p>
        </form>
    </UserLayout>
</template>
