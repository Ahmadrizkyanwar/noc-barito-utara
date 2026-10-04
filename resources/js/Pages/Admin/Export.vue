<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { reactive, ref, watch } from 'vue';

const props = defineProps({
    datasets: { type: Object, required: true },
    periods: { type: Object, required: true },
    formats: { type: Object, required: true },
});

const todayStr = () => new Date().toISOString().slice(0, 10);

const form = reactive({
    dataset: 'semua',
    format: 'pdf',
    periode: 'harian',
    dari: todayStr(),
    sampai: todayStr(),
});

const preview = ref({ count: null, from: '', to: '', period_label: '' });
const loading = ref(false);
const exporting = ref(false);

async function loadPreview() {
    if (form.periode === 'custom' && (!form.dari || !form.sampai)) {
        preview.value = { count: null, from: '', to: '', period_label: '' };
        return;
    }

    loading.value = true;

    try {
        const res = await fetch(
            route('admin.exports.preview', {
                dataset: form.dataset,
                format: form.format,
                periode: form.periode,
                dari: form.dari,
                sampai: form.sampai,
            }),
            { headers: { Accept: 'application/json' } }
        );

        if (res.ok) preview.value = await res.json();
    } catch {
        /* polling preview gagal → biarkan */
    } finally {
        loading.value = false;
    }
}

watch(
    () => [form.dataset, form.periode, form.dari, form.sampai],
    loadPreview,
    { immediate: true }
);

function pickDataset(key) {
    form.dataset = key;
}

function download() {
    exporting.value = true;
    window.location.href = route('admin.exports.download', {
        dataset: form.dataset,
        format: form.format,
        periode: form.periode,
        dari: form.dari,
        sampai: form.sampai,
    });
    setTimeout(() => (exporting.value = false), 2500);
}

const formatDesc = {
    pdf: 'Dokumen cetak — cocok untuk arsip/print, kertas A4 lanskap.',
    excel: 'Spreadsheet .xlsx — bisa difilter & diolah lanjut.',
};

const periodeDesc = {
    harian: 'Dari pukul 00.00 hari ini sampai sekarang.',
    mingguan: '7 hari terakhir (termasuk hari ini).',
    custom: 'Dari tanggal yang dipilih sampai akhir hari tersebut.',
};
</script>

<template>
    <AdminLayout>
        <div>
            <h1 class="text-xl font-bold">Export Laporan</h1>
            <p class="mt-1 text-sm text-slate-500">
                Unduh tiket gangguan dan pendaftaran VPS/Domain/Hosting sebagai
                <b>PDF</b> atau <b>Excel</b>, dengan rentang waktu harian, mingguan, atau pilih sendiri.
            </p>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <!-- Pilihan -->
            <div class="space-y-6 lg:col-span-2">
                <!-- Jenis data -->
                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="font-bold">1. Jenis Data</h2>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <button
                            v-for="(label, key) in datasets"
                            :key="key"
                            type="button"
                            class="rounded-lg border px-4 py-3 text-left text-sm font-semibold transition"
                            :class="form.dataset === key
                                ? 'border-brand-600 bg-brand-50 text-brand-800'
                                : 'border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-slate-50'"
                            @click="pickDataset(key)"
                        >
                            {{ label }}
                        </button>
                    </div>
                </div>

                <!-- Format -->
                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="font-bold">2. Format File</h2>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <button
                            v-for="(label, key) in formats"
                            :key="key"
                            type="button"
                            class="rounded-xl border p-4 text-left transition"
                            :class="form.format === key
                                ? 'border-brand-600 bg-brand-50'
                                : 'border-slate-200 hover:border-slate-300'"
                            @click="form.format = key"
                        >
                            <span class="flex items-center gap-2">
                                <span class="text-xl">{{ key === 'pdf' ? '📄' : '📊' }}</span>
                                <span class="font-bold" :class="form.format === key ? 'text-brand-800' : 'text-slate-700'">
                                    {{ label }}
                                </span>
                                <span
                                    v-if="form.format === key"
                                    class="ml-auto rounded-full bg-brand-600 px-2 py-0.5 text-[10px] font-bold text-white"
                                >
                                    dipilih
                                </span>
                            </span>
                            <span class="mt-1.5 block text-xs text-slate-500">{{ formatDesc[key] }}</span>
                        </button>
                    </div>
                </div>

                <!-- Rentang waktu -->
                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="font-bold">3. Rentang Waktu</h2>
                    <div class="mt-3 grid gap-2 sm:grid-cols-3">
                        <button
                            v-for="(label, key) in periods"
                            :key="key"
                            type="button"
                            class="rounded-lg border px-3 py-3 text-sm font-semibold transition"
                            :class="form.periode === key
                                ? 'border-brand-600 bg-brand-50 text-brand-800'
                                : 'border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-slate-50'"
                            @click="form.periode = key"
                        >
                            {{ label.split(' (')[0] }}
                        </button>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">{{ periodeDesc[form.periode] }}</p>

                    <div v-if="form.periode === 'custom'" class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="exp-dari" class="block text-xs font-semibold text-slate-600">Dari tanggal *</label>
                            <input
                                id="exp-dari"
                                v-model="form.dari"
                                type="date"
                                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                            >
                        </div>
                        <div>
                            <label for="exp-sampai" class="block text-xs font-semibold text-slate-600">Sampai tanggal *</label>
                            <input
                                id="exp-sampai"
                                v-model="form.sampai"
                                type="date"
                                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                            >
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel unduh -->
            <div class="lg:col-span-1">
                <div class="sticky top-24 rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="font-bold">Ringkasan Export</h2>

                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-slate-500">Jenis data</dt>
                            <dd class="text-right font-semibold text-slate-800">{{ datasets[form.dataset] }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-slate-500">Format</dt>
                            <dd class="text-right font-semibold text-slate-800">{{ formats[form.format] }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-slate-500">Rentang</dt>
                            <dd class="text-right font-semibold text-slate-800">
                                <template v-if="preview.from">
                                    {{ preview.from }} — {{ preview.to }}
                                </template>
                                <template v-else>—</template>
                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-3 border-t border-slate-100 pt-3">
                            <dt class="text-slate-500">Jumlah data</dt>
                            <dd class="font-mono text-lg font-black text-brand-700">
                                <template v-if="loading">…</template>
                                <template v-else-if="preview.count !== null">{{ preview.count }} baris</template>
                                <template v-else>—</template>
                            </dd>
                        </div>
                    </dl>

                    <button
                        type="button"
                        class="mt-5 w-full rounded-lg bg-brand-700 px-4 py-3 text-sm font-bold text-white transition hover:bg-brand-800 disabled:opacity-60"
                        :disabled="exporting || (form.periode === 'custom' && (!form.dari || !form.sampai))"
                        @click="download"
                    >
                        {{ exporting ? 'Menyiapkan file…' : `Export ${form.format === 'pdf' ? 'PDF' : 'Excel'} ↓` }}
                    </button>

                    <p class="mt-3 text-[11px] leading-relaxed text-slate-400">
                        File otomatis terunduh dengan nama
                        <span class="font-mono">laporan-…-{{ form.periode }}-{{ form.format === 'pdf' ? 'pdf' : 'xlsx' }}</span>.
                        Export dibatasi 10x per menit.
                    </p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
