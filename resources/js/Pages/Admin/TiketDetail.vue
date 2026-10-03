<script setup>
import LocationPicker from '@/Components/LocationPicker.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    ticket: { type: Object, required: true },
    statuses: { type: Object, default: () => ({}) },
});

const hasCoords = computed(
    () => props.ticket.lat !== null && props.ticket.lat !== undefined &&
          props.ticket.lng !== null && props.ticket.lng !== undefined
);

const mapUrl = computed(() =>
    hasCoords.value ? `https://www.google.com/maps?q=${props.ticket.lat},${props.ticket.lng}` : null
);

const note = ref('');
const busy = ref(false);

function setStatus(status) {
    if (status === props.ticket.status) return;
    busy.value = true;
    router.patch(
        route('admin.tickets.status', props.ticket.id),
        { status },
        {
            preserveScroll: true,
            onFinish: () => (busy.value = false),
        }
    );
}

function addNote() {
    if (!note.value.trim()) return;
    busy.value = true;
    router.post(
        route('admin.tickets.note', props.ticket.id),
        { note: note.value },
        {
            preserveScroll: true,
            onSuccess: () => (note.value = ''),
            onFinish: () => (busy.value = false),
        }
    );
}

const statusClass = (s) =>
    ({ open: 'bg-blue-100 text-blue-700', proses: 'bg-amber-100 text-amber-800', selesai: 'bg-emerald-100 text-emerald-700' })[s] ??
    'bg-slate-100 text-slate-600';
</script>

<template>
    <AdminLayout>
        <Link :href="route('admin.tickets.index')" class="text-sm font-semibold text-brand-700 hover:underline">← Layanan</Link>

        <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="font-mono text-xs font-bold text-slate-500">{{ ticket.code }}</p>
                <h1 class="text-xl font-bold">{{ ticket.title }}</h1>
                <p class="mt-1 text-sm text-slate-500">
                    {{ ticket.category }} ·
                    {{ new Date(ticket.created_at).toLocaleString('id-ID') }}
                </p>
            </div>
            <span class="rounded-full px-3 py-1 text-sm font-bold" :class="statusClass(ticket.status)">
                {{ statuses[ticket.status] ?? ticket.status }}
            </span>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <!-- Detail -->
            <div class="lg:col-span-2">
                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="font-bold">Keterangan Laporan</h2>
                    <p class="mt-3 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{{ ticket.description }}</p>

                    <div v-if="ticket.location" class="mt-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Lokasi</p>
                        <p class="text-sm">{{ ticket.location }}</p>
                    </div>

                    <div v-if="hasCoords" class="mt-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Titik Lokasi</p>
                        <div class="mt-2">
                            <LocationPicker
                                :model-value="{ lat: ticket.lat, lng: ticket.lng }"
                                readonly
                                height="240px"
                            />
                        </div>
                        <a
                            :href="mapUrl"
                            target="_blank"
                            rel="noopener"
                            class="mt-2 inline-block text-sm font-semibold text-brand-700 hover:underline"
                        >
                            Buka di Google Maps ↗
                        </a>
                    </div>

                    <div v-if="ticket.photo" class="mt-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Foto</p>
                        <a :href="route('ticket.photo', ticket.photo.replace('uploads/', ''))" target="_blank">
                            <img :src="route('ticket.photo', ticket.photo.replace('uploads/', ''))" alt="Foto laporan" class="mt-2 max-h-64 rounded-lg border border-slate-200">
                        </a>
                    </div>
                </div>

                <!-- Riwayat -->
                <div class="mt-5 rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="font-bold">Riwayat Penanganan</h2>
                    <ol class="mt-3 space-y-3 border-l-2 border-slate-100 pl-4">
                        <li v-for="a in ticket.activities" :key="a.id" class="text-sm">
                            <p class="text-xs text-slate-400">
                                {{ new Date(a.created_at).toLocaleString('id-ID') }}
                                <template v-if="a.user"> · {{ a.user.name }}</template>
                            </p>
                            <p class="mt-0.5">
                                <template v-if="a.action === 'created'">Tiket dibuat oleh pelapor</template>
                                <template v-else-if="a.action === 'status'">
                                    Status diubah:
                                    <b>{{ statuses[a.old_value] ?? a.old_value }}</b> →
                                    <b>{{ statuses[a.new_value] ?? a.new_value }}</b>
                                </template>
                                <template v-else-if="a.action === 'assign'">Penugasan: {{ a.note }}</template>
                                <template v-else-if="a.action === 'note'">{{ a.note }}</template>
                                <template v-else>{{ a.action }}</template>
                            </p>
                        </li>
                    </ol>

                    <!-- Tambah catatan -->
                    <div class="mt-4 border-t border-slate-100 pt-4">
                        <label class="block text-xs font-semibold text-slate-600">Catatan penanganan</label>
                        <textarea
                            v-model="note"
                            rows="2"
                            maxlength="1000"
                            placeholder="Tulis catatan…"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                        ></textarea>
                        <button
                            class="mt-2 rounded-lg bg-brand-700 px-4 py-2 text-sm font-bold text-white hover:bg-brand-800 disabled:opacity-60"
                            :disabled="busy || !note.trim()"
                            @click="addNote"
                        >
                            Simpan Catatan
                        </button>
                    </div>
                </div>
            </div>

            <!-- Panel aksi -->
            <div class="space-y-5">
                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="font-bold">Ubah Status</h2>
                    <div class="mt-3 space-y-2">
                        <button
                            v-for="(label, key) in statuses"
                            :key="key"
                            class="w-full rounded-lg border px-4 py-2 text-sm font-semibold transition"
                            :class="ticket.status === key
                                ? 'border-brand-700 bg-brand-700 text-white'
                                : 'border-slate-300 hover:bg-slate-100'"
                            :disabled="busy || ticket.status === key"
                            @click="setStatus(key)"
                        >
                            {{ label }}
                        </button>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5 text-sm">
                    <h2 class="font-bold">Informasi</h2>
                    <dl class="mt-3 space-y-2 text-slate-600">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Pelapor</dt>
                            <dd>{{ ticket.reporter_name ?? ticket.reporter?.name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Kontak</dt>
                            <dd>{{ ticket.reporter_contact ?? ticket.reporter?.email ?? '—' }}</dd>
                        </div>
                        <div v-if="ticket.assignee">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Penanggung jawab</dt>
                            <dd>{{ ticket.assignee.name }}</dd>
                        </div>
                        <div v-if="ticket.resolved_at">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Selesai</dt>
                            <dd>{{ new Date(ticket.resolved_at).toLocaleString('id-ID') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
