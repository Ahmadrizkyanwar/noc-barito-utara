<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    users: { type: Object, required: true },
    statuses: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const busy = ref(null);

const tabs = [
    { key: '', label: 'Semua' },
    { key: 'pending', label: 'Menunggu' },
    { key: 'approved', label: 'Disetujui' },
    { key: 'rejected', label: 'Ditolak' },
];

function filter(status) {
    router.get(route('admin.registrations.index'), status ? { status } : {}, {
        preserveState: true,
        preserveScroll: true,
    });
}

function decide(user, status) {
    busy.value = user.id;
    router.patch(
        route('admin.registrations.status', user.id),
        { status },
        {
            preserveScroll: true,
            onFinish: () => (busy.value = null),
        }
    );
}

function isReviewer(user) {
    return user.role === 'admin' || user.role === 'operator';
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

const roleLabel = (r) => ({ admin: 'Admin', operator: 'Operator', user: 'User' })[r] ?? r;
const fmtDate = (d) => new Date(d).toLocaleString('id-ID');
</script>

<template>
    <AdminLayout>
        <div>
            <h1 class="text-xl font-bold">Validasi Registrasi User</h1>
            <p class="mt-1 text-sm text-slate-500">
                Setujui atau tolak akun yang mendaftar lewat halaman registrasi publik.
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

        <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Terdaftar</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="u in users.data" :key="u.id">
                        <td class="px-4 py-3 font-semibold">{{ u.name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ u.email }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600">
                                {{ roleLabel(u.role) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ fmtDate(u.created_at) }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="statusClass(u.status)">
                                {{ statuses[u.status] ?? u.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <span v-if="isReviewer(u)" class="text-xs text-slate-400">—</span>
                            <div v-else class="flex justify-end gap-2">
                                <button
                                    class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-700 disabled:opacity-60"
                                    :disabled="busy === u.id || u.status === 'approved'"
                                    @click="decide(u, 'approved')"
                                >
                                    Setujui
                                </button>
                                <button
                                    class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-red-700 disabled:opacity-60"
                                    :disabled="busy === u.id || u.status === 'rejected'"
                                    @click="decide(u, 'rejected')"
                                >
                                    Tolak
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="users.data.length === 0">
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">
                            Tidak ada user pada filter ini.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="users.last_page > 1" class="mt-4 flex items-center justify-between text-sm">
            <Link
                v-if="users.prev_page_url"
                :href="users.prev_page_url"
                class="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold hover:bg-slate-100"
            >
                ← Sebelumnya
            </Link>
            <span v-else></span>
            <span class="text-slate-500">Halaman {{ users.current_page }} / {{ users.last_page }}</span>
            <Link
                v-if="users.next_page_url"
                :href="users.next_page_url"
                class="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold hover:bg-slate-100"
            >
                Berikutnya →
            </Link>
            <span v-else></span>
        </div>
    </AdminLayout>
</template>
