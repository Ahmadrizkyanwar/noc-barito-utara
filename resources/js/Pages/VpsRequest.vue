<script setup>
import UserLayout from '@/Layouts/UserLayout.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    canRequest: { type: Boolean, required: true },
    accountStatus: { type: String, required: true },
    ports: { type: Object, required: true },
    operatingSystems: { type: Object, required: true },
});

const auth = computed(() => usePage().props.auth?.user);

const form = useForm({
    name: auth.value?.name ?? '',
    nip: '',
    jabatan: '',
    instansi: '',
    cores: 2,
    ram_gb: 4,
    public_ips: 1,
    os: '',
    os_other: '',
    ports: ['22', '443'],
    custom_ports: '',
    purpose: '',
    supporting_document: null,
});

const docInput = ref(null);
const docName = ref('');
const docSize = ref(0);
const dragging = ref(false);

// OS "Lainnya" → tampilkan isian bebas; pilihan lain → kosongkan.
watch(
    () => form.os,
    (v) => {
        if (v !== 'lainnya') form.os_other = '';
    }
);

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

function togglePort(key) {
    const i = form.ports.indexOf(key);
    if (i === -1) form.ports.push(key);
    else form.ports.splice(i, 1);
}

function submit() {
    form.post(route('vps.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('nip', 'jabatan', 'instansi', 'purpose', 'custom_ports', 'os_other');
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
                <h1 class="text-xl font-bold">Request VPS</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Ajukan kebutuhan VPS untuk instansi Anda. Fitur terbuka untuk akun yang sudah disetujui admin.
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
                    Setelah disetujui, formulir request VPS akan terbuka di halaman ini.
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
            <h2 class="font-bold">Formulir Request VPS</h2>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="vps-name" class="block text-sm font-semibold">Nama *</label>
                    <input
                        id="vps-name"
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
                    <label for="vps-nip" class="block text-sm font-semibold">NIP *</label>
                    <input
                        id="vps-nip"
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
                    <label for="vps-jabatan" class="block text-sm font-semibold">Jabatan *</label>
                    <input
                        id="vps-jabatan"
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
                    <label for="vps-instansi" class="block text-sm font-semibold">Instansi *</label>
                    <input
                        id="vps-instansi"
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

                <div>
                    <label for="vps-cores" class="block text-sm font-semibold">Jumlah Core CPU *</label>
                    <input
                        id="vps-cores"
                        v-model.number="form.cores"
                        type="number"
                        min="1"
                        max="256"
                        required
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                        :class="{ 'border-red-400': form.errors.cores }"
                    >
                    <p v-if="form.errors.cores" class="mt-1 text-xs text-red-600">{{ form.errors.cores }}</p>
                </div>

                <div>
                    <label for="vps-ram" class="block text-sm font-semibold">RAM (GB) *</label>
                    <input
                        id="vps-ram"
                        v-model.number="form.ram_gb"
                        type="number"
                        min="1"
                        max="1024"
                        required
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                        :class="{ 'border-red-400': form.errors.ram_gb }"
                    >
                    <p v-if="form.errors.ram_gb" class="mt-1 text-xs text-red-600">{{ form.errors.ram_gb }}</p>
                </div>

                <div>
                    <label for="vps-ips" class="block text-sm font-semibold">Jumlah IP Publik *</label>
                    <input
                        id="vps-ips"
                        v-model.number="form.public_ips"
                        type="number"
                        min="1"
                        max="16"
                        required
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                        :class="{ 'border-red-400': form.errors.public_ips }"
                    >
                    <p v-if="form.errors.public_ips" class="mt-1 text-xs text-red-600">{{ form.errors.public_ips }}</p>
                </div>

                <div>
                    <label for="vps-os" class="block text-sm font-semibold">Pilihan Sistem Operasi *</label>
                    <select
                        id="vps-os"
                        v-model="form.os"
                        required
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                        :class="{ 'border-red-400': form.errors.os }"
                    >
                        <option value="" disabled>Pilih sistem operasi…</option>
                        <option v-for="(label, key) in operatingSystems" :key="key" :value="key">
                            {{ label }}
                        </option>
                    </select>
                    <p v-if="form.errors.os" class="mt-1 text-xs text-red-600">{{ form.errors.os }}</p>

                    <div v-if="form.os === 'lainnya'" class="mt-3">
                        <label for="vps-os-other" class="block text-sm font-semibold">Nama Sistem Operasi Lainnya *</label>
                        <input
                            id="vps-os-other"
                            v-model.trim="form.os_other"
                            type="text"
                            required
                            maxlength="100"
                            placeholder="Contoh: Proxmox VE 8, FreeBSD 14, AlmaLinux 10"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                            :class="{ 'border-red-400': form.errors.os_other }"
                        >
                        <p v-if="form.errors.os_other" class="mt-1 text-xs text-red-600">{{ form.errors.os_other }}</p>
                    </div>
                </div>
            </div>

            <!-- Service PORT -->
            <div class="mt-4">
                <span class="block text-sm font-semibold">Service PORT yang dibuka</span>
                <p class="mt-0.5 text-xs text-slate-500">
                    Pilih minimal satu layanan di daftar, atau isi service port tambahan di bawah.
                </p>
                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <label
                        v-for="(label, key) in ports"
                        :key="key"
                        class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm transition"
                        :class="form.ports.includes(key) ? 'border-brand-600 bg-brand-50 font-semibold' : 'border-slate-300 hover:bg-slate-50'"
                    >
                        <input
                            type="checkbox"
                            class="rounded border-slate-300"
                            :checked="form.ports.includes(key)"
                            @change="togglePort(key)"
                        >
                        <span class="truncate">{{ label }}</span>
                        <span class="ml-auto font-mono text-xs text-slate-400">{{ key }}</span>
                    </label>
                </div>
                <p v-if="form.errors.ports" class="mt-1 text-xs text-red-600">{{ form.errors.ports }}</p>
                <p v-if="form.errors['ports.0']" class="mt-1 text-xs text-red-600">{{ form.errors['ports.0'] }}</p>

                <div class="mt-3">
                    <label for="vps-custom-ports" class="block text-sm font-semibold">Service Port tambahan (opsional)</label>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Isi port di luar daftar di atas, gunakan angka atau rentang dipisah koma.
                    </p>
                    <input
                        id="vps-custom-ports"
                        v-model.trim="form.custom_ports"
                        type="text"
                        maxlength="255"
                        placeholder="Contoh: 8443, 9090, 3000-3100"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                        :class="{ 'border-red-400': form.errors.custom_ports }"
                    >
                    <p v-if="form.errors.custom_ports" class="mt-1 text-xs text-red-600">{{ form.errors.custom_ports }}</p>
                </div>
            </div>

            <div class="mt-4">
                <label for="vps-purpose" class="block text-sm font-semibold">Penggunaan Untuk *</label>
                <textarea
                    id="vps-purpose"
                    v-model="form.purpose"
                    required
                    rows="3"
                    maxlength="1000"
                    placeholder="Jelaskan tujuan penggunaan VPS…"
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

                <!-- Sudah memilih file -->
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

                <!-- Dropzone -->
                <label
                    v-else
                    for="vps-doc"
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
                    id="vps-doc"
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
                {{ form.processing ? 'Mengirim…' : 'Kirim Request' }}
            </button>

            <p class="mt-4 text-sm text-slate-500">
                Riwayat & status request Anda tampil di
                <Link :href="route('dashboard')" class="font-semibold text-brand-700 hover:underline">Dashboard</Link>.
            </p>
        </form>
    </UserLayout>
</template>
