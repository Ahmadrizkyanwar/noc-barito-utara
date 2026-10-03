<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    devices: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
});

const showForm = ref(false);
const editing = ref(null);

const empty = {
    name: '',
    host: '',
    type: 'router',
    location: '',
    enabled: true,
    use_icmp: true,
    use_snmp: false,
    use_routeros: false,
    snmp_version: '2c',
    snmp_community: 'public',
    snmp_port: 161,
    routeros_port: 8728,
    routeros_user: 'admin',
    routeros_password: '',
    routeros_timeout: 5,
};

const form = useForm({ ...empty });

function openCreate() {
    editing.value = null;
    form.clearErrors();
    form.reset();
    Object.assign(form, empty);
    showForm.value = true;
}

function openEdit(device) {
    editing.value = device;
    form.clearErrors();
    form.reset();
    Object.assign(form, {
        name: device.name,
        host: device.host,
        type: device.type,
        location: device.location ?? '',
        enabled: device.enabled,
        use_icmp: device.use_icmp,
        use_snmp: device.use_snmp,
        use_routeros: device.use_routeros,
        snmp_version: device.snmp_version ?? '2c',
        snmp_community: device.snmp_community ?? 'public',
        snmp_port: device.snmp_port ?? 161,
        routeros_port: device.routeros_port ?? 8728,
        routeros_user: device.routeros_user ?? 'admin',
        routeros_password: device.routeros_password ?? '',
        routeros_timeout: device.routeros_timeout ?? 5,
    });
    showForm.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            editing.value = null;
        },
    };
    if (editing.value) {
        form.put(route('admin.devices.update', editing.value.id), options);
    } else {
        form.post(route('admin.devices.store'), options);
    }
}

function remove(device) {
    if (!confirm(`Hapus perangkat "${device.name}"? Riwayat metrics ikut terhapus.`)) return;
    router.delete(route('admin.devices.destroy', device.id), { preserveScroll: true });
}

function checkNow(device) {
    device._checking = true;
    router.post(
        route('admin.devices.check', device.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => (device._checking = false),
        }
    );
}

function applyFilter(key, value) {
    const q = { ...props.filters };
    if (value) {
        q[key] = value;
    } else {
        delete q[key];
    }
    router.get(route('admin.devices.index'), q, { preserveState: true, replace: true });
}
</script>

<template>
    <AdminLayout>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold">Jaringan</h1>
                <p class="text-sm text-slate-500">Perangkat terpantau via SNMP · ICMP · RouterOS API (poll 30 detik)</p>
            </div>
            <button
                class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-bold text-white hover:bg-brand-800"
                @click="openCreate"
            >
                + Tambah Perangkat
            </button>
        </div>

        <!-- Filter -->
        <div class="mt-4 flex flex-wrap gap-2">
            <input
                type="search"
                :value="filters.q ?? ''"
                placeholder="Cari nama/host…"
                class="w-56 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                @input="applyFilter('q', $event.target.value)"
            >
            <select
                :value="filters.status ?? ''"
                class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none"
                @change="applyFilter('status', $event.target.value)"
            >
                <option value="">Semua status</option>
                <option value="up">Online</option>
                <option value="down">Gangguan</option>
                <option value="unknown">Baru</option>
            </select>
        </div>

        <!-- Tabel -->
        <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Perangkat</th>
                        <th class="px-4 py-3">Metode</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">CPU</th>
                        <th class="px-4 py-3 text-right">RTT</th>
                        <th class="px-4 py-3">Cek terakhir</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="d in devices" :key="d.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <Link :href="route('admin.devices.show', d.id)" class="font-semibold text-brand-700 hover:underline">
                                {{ d.name }}
                            </Link>
                            <p class="text-xs text-slate-500">{{ d.host }} · {{ d.type }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                <span v-if="d.use_icmp" class="rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-bold text-slate-600">ICMP</span>
                                <span v-if="d.use_snmp" class="rounded bg-blue-100 px-1.5 py-0.5 text-[11px] font-bold text-blue-700">SNMP</span>
                                <span v-if="d.use_routeros" class="rounded bg-purple-100 px-1.5 py-0.5 text-[11px] font-bold text-purple-700">RouterOS</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-bold"
                                :class="{
                                    'bg-emerald-100 text-emerald-700': d.status === 'up',
                                    'bg-red-100 text-red-700': d.status === 'down',
                                    'bg-slate-100 text-slate-500': d.status === 'unknown',
                                }"
                            >
                                {{ d.status === 'up' ? 'ONLINE' : d.status === 'down' ? 'DOWN' : 'BARU' }}
                            </span>
                            <span v-if="d.enabled === false" class="ml-1 rounded bg-slate-200 px-1.5 py-0.5 text-[11px] font-bold text-slate-500">NONAKTIF</span>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ d.last_cpu ?? '—' }}<span v-if="d.last_cpu !== null">%</span></td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ d.last_rtt_ms !== null ? d.last_rtt_ms + ' ms' : '—' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">
                            {{ d.last_checked_at ? new Date(d.last_checked_at).toLocaleString('id-ID') : 'belum pernah' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex gap-1">
                                <button
                                    class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold hover:bg-slate-100 disabled:opacity-50"
                                    :disabled="d._checking"
                                    @click="checkNow(d)"
                                >
                                    {{ d._checking ? '…' : 'Cek' }}
                                </button>
                                <Link
                                    :href="route('admin.devices.traffic', d.id)"
                                    class="rounded border border-brand-200 bg-brand-50 px-2 py-1 text-xs font-semibold text-brand-700 hover:bg-brand-100"
                                >
                                    Trafik
                                </Link>
                                <button
                                    class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold hover:bg-slate-100"
                                    @click="openEdit(d)"
                                >
                                    Edit
                                </button>
                                <button
                                    class="rounded border border-red-200 px-2 py-1 text-xs font-semibold text-red-600 hover:bg-red-50"
                                    @click="remove(d)"
                                >
                                    Hapus
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="devices.length === 0">
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">
                            Belum ada perangkat. Klik <b>+ Tambah Perangkat</b> untuk memulai.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal form -->
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4">
            <div class="mt-10 w-full max-w-2xl rounded-2xl bg-white p-6 shadow-xl">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold">{{ editing ? 'Edit Perangkat' : 'Tambah Perangkat' }}</h2>
                    <button class="text-slate-400 hover:text-slate-600" @click="showForm = false">✕</button>
                </div>

                <form class="mt-4 space-y-4" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600">Nama *</label>
                            <input v-model="form.name" type="text" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :class="{ 'border-red-400': form.errors.name }">
                            <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600">Host / IP *</label>
                            <input v-model="form.host" type="text" required placeholder="192.168.1.1" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :class="{ 'border-red-400': form.errors.host }">
                            <p v-if="form.errors.host" class="mt-1 text-xs text-red-600">{{ form.errors.host }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600">Tipe</label>
                            <select v-model="form.type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="router">Router</option>
                                <option value="switch">Switch</option>
                                <option value="server">Server</option>
                                <option value="website">Website</option>
                                <option value="lainnya">Lainnya</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600">Lokasi</label>
                            <input v-model="form.location" type="text" maxlength="150" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        </div>
                    </div>

                    <!-- Metode -->
                    <fieldset class="rounded-lg border border-slate-200 p-4">
                        <legend class="px-1 text-xs font-bold uppercase tracking-wide text-slate-500">Metode Pemantauan</legend>
                        <div class="flex flex-wrap gap-5">
                            <label class="flex items-center gap-2 text-sm font-semibold">
                                <input v-model="form.use_icmp" type="checkbox" class="rounded border-slate-300">
                                ICMP (ping)
                            </label>
                            <label class="flex items-center gap-2 text-sm font-semibold">
                                <input v-model="form.use_snmp" type="checkbox" class="rounded border-slate-300">
                                SNMP
                            </label>
                            <label class="flex items-center gap-2 text-sm font-semibold">
                                <input v-model="form.use_routeros" type="checkbox" class="rounded border-slate-300">
                                RouterOS API
                            </label>
                        </div>

                        <!-- SNMP -->
                        <div v-if="form.use_snmp" class="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600">Versi</label>
                                <select v-model="form.snmp_version" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option value="1">v1</option>
                                    <option value="2c">v2c</option>
                                    <option value="3">v3</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600">Community</label>
                                <input v-model="form.snmp_community" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600">Port</label>
                                <input v-model.number="form.snmp_port" type="number" min="1" max="65535" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </div>
                        </div>

                        <!-- RouterOS -->
                        <div v-if="form.use_routeros" class="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600">Port API</label>
                                <input v-model.number="form.routeros_port" type="number" min="1" max="65535" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600">Timeout (dtk)</label>
                                <input v-model.number="form.routeros_timeout" type="number" min="1" max="60" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600">User</label>
                                <input v-model="form.routeros_user" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600">Password</label>
                                <input v-model="form.routeros_password" type="password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </div>
                        </div>
                    </fieldset>

                    <label class="flex items-center gap-2 text-sm font-semibold">
                        <input v-model="form.enabled" type="checkbox" class="rounded border-slate-300">
                        Aktifkan pemantauan
                    </label>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-100" @click="showForm = false">
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-bold text-white hover:bg-brand-800 disabled:opacity-60"
                        >
                            {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
