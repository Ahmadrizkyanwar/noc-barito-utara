<script setup>
import LocationPicker from '@/Components/LocationPicker.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    categories: { type: Array, required: true },
    statuses: { type: Object, default: () => ({}) },
});

const form = useForm({
    title: '',
    category: props.categories[0] ?? 'Jaringan',
    description: '',
    location: '',
    lat: null,
    lng: null,
    reporter_name: '',
    reporter_contact: '',
    photo: null,
});

// Jembatan v-model LocationPicker ({lat,lng}|null) ↔ form.lat/form.lng.
const point = computed({
    get: () => (form.lat !== null && form.lng !== null ? { lat: form.lat, lng: form.lng } : null),
    set: (v) => {
        form.lat = v?.lat ?? null;
        form.lng = v?.lng ?? null;
    },
});

const photoPreview = ref(null);

function onPhoto(e) {
    const file = e.target.files?.[0] ?? null;
    form.photo = file;
    photoPreview.value = file ? URL.createObjectURL(file) : null;
}

function submit() {
    form.post(route('lapor.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            photoPreview.value = null;
        },
    });
}
</script>

<template>
    <PublicLayout>
        <section class="mx-auto max-w-2xl px-4 py-10">
            <h1 class="text-2xl font-bold">Lapor Gangguan</h1>
            <p class="mt-1 text-sm text-slate-500">
                Isi formulir berikut untuk melaporkan gangguan jaringan/layanan.
                Anda akan mendapatkan kode tiket untuk melacak penanganan.
            </p>

            <form class="mt-6 space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                <div>
                    <label for="title" class="block text-sm font-semibold">Judul gangguan *</label>
                    <input
                        id="title"
                        v-model="form.title"
                        type="text"
                        required
                        maxlength="200"
                        placeholder="Contoh: Internet kantor terputus"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-100"
                        :class="{ 'border-red-400': form.errors.title }"
                    >
                    <p v-if="form.errors.title" class="mt-1 text-xs text-red-600">{{ form.errors.title }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="category" class="block text-sm font-semibold">Kategori *</label>
                        <select
                            id="category"
                            v-model="form.category"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                            :class="{ 'border-red-400': form.errors.category }"
                        >
                            <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                        </select>
                        <p v-if="form.errors.category" class="mt-1 text-xs text-red-600">{{ form.errors.category }}</p>
                    </div>
                    <div>
                        <label for="location" class="block text-sm font-semibold">Lokasi</label>
                        <input
                            id="location"
                            v-model="form.location"
                            type="text"
                            maxlength="200"
                            placeholder="Contoh: Ruang Server Setda"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                        >
                    </div>
                </div>

                <div>
                    <span class="block text-sm font-semibold">Titik lokasi (opsional)</span>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Bagikan titik lokasi Anda — tekan tombol GPS atau klik peta untuk
                        menandai lokasi gangguan.
                    </p>
                    <div class="mt-2">
                        <LocationPicker v-model="point" />
                    </div>
                    <p v-if="form.errors.lat" class="mt-1 text-xs text-red-600">{{ form.errors.lat }}</p>
                    <p v-else-if="form.errors.lng" class="mt-1 text-xs text-red-600">{{ form.errors.lng }}</p>
                </div>

                <div>
                    <label for="description" class="block text-sm font-semibold">Keterangan *</label>
                    <textarea
                        id="description"
                        v-model="form.description"
                        required
                        rows="4"
                        maxlength="2000"
                        placeholder="Jelaskan gejala gangguan…"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-100"
                        :class="{ 'border-red-400': form.errors.description }"
                    ></textarea>
                    <p v-if="form.errors.description" class="mt-1 text-xs text-red-600">{{ form.errors.description }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="reporter_name" class="block text-sm font-semibold">Nama pelapor *</label>
                        <input
                            id="reporter_name"
                            v-model="form.reporter_name"
                            type="text"
                            required
                            maxlength="100"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                            :class="{ 'border-red-400': form.errors.reporter_name }"
                        >
                        <p v-if="form.errors.reporter_name" class="mt-1 text-xs text-red-600">{{ form.errors.reporter_name }}</p>
                    </div>
                    <div>
                        <label for="reporter_contact" class="block text-sm font-semibold">Kontak (HP/email)</label>
                        <input
                            id="reporter_contact"
                            v-model="form.reporter_contact"
                            type="text"
                            maxlength="100"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                        >
                    </div>
                </div>

                <div>
                    <label for="photo" class="block text-sm font-semibold">Foto pendukung (opsional, maks 2 MB)</label>
                    <input
                        id="photo"
                        type="file"
                        accept="image/*"
                        class="mt-1 w-full text-sm"
                        @change="onPhoto"
                    >
                    <img v-if="photoPreview" :src="photoPreview" alt="Pratinjau" class="mt-2 h-28 rounded-lg border border-slate-200 object-cover">
                    <p v-if="form.errors.photo" class="mt-1 text-xs text-red-600">{{ form.errors.photo }}</p>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-brand-800 disabled:opacity-60"
                >
                    {{ form.processing ? 'Mengirim…' : 'Kirim Laporan' }}
                </button>
            </form>
        </section>
    </PublicLayout>
</template>
