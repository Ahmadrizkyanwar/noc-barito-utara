<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    users: { type: Array, required: true },
});

const showForm = ref(false);
const editing = ref(null);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'user',
});

function openCreate() {
    editing.value = null;
    form.clearErrors();
    form.reset();
    form.role = 'user';
    showForm.value = true;
}

function openEdit(user) {
    editing.value = user;
    form.clearErrors();
    form.reset();
    form.name = user.name;
    form.email = user.email;
    form.role = user.role;
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
        form.put(route('admin.users.update', editing.value.id), options);
    } else {
        form.post(route('admin.users.store'), options);
    }
}

function remove(user) {
    if (!confirm(`Hapus pengguna "${user.name}"?`)) return;
    router.delete(route('admin.users.destroy', user.id), { preserveScroll: true });
}
</script>

<template>
    <AdminLayout>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold">Pengaturan — Pengguna</h1>
                <p class="text-sm text-slate-500">Admin: akses penuh · User: lapor gangguan &amp; pantau tiket sendiri</p>
            </div>
            <button class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-bold text-white hover:bg-brand-800" @click="openCreate">
                + Tambah Pengguna
            </button>
        </div>

        <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Peran</th>
                        <th class="px-4 py-3">Terdaftar</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="u in users" :key="u.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-semibold">{{ u.name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ u.email }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-bold"
                                :class="{
                                    'bg-brand-100 text-brand-800': u.role === 'admin',
                                    'bg-amber-100 text-amber-800': u.role === 'operator',
                                    'bg-slate-100 text-slate-600': u.role === 'user',
                                }"
                            >
                                {{ ({ admin: 'ADMIN', operator: 'OPERATOR', user: 'USER' })[u.role] ?? u.role }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500">
                            {{ new Date(u.created_at).toLocaleDateString('id-ID') }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex gap-1">
                                <button class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold hover:bg-slate-100" @click="openEdit(u)">
                                    Edit
                                </button>
                                <button class="rounded border border-red-200 px-2 py-1 text-xs font-semibold text-red-600 hover:bg-red-50" @click="remove(u)">
                                    Hapus
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal -->
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4">
            <div class="mt-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold">{{ editing ? 'Edit Pengguna' : 'Tambah Pengguna' }}</h2>
                    <button class="text-slate-400 hover:text-slate-600" @click="showForm = false">✕</button>
                </div>

                <form class="mt-4 space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600">Nama *</label>
                        <input v-model="form.name" type="text" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :class="{ 'border-red-400': form.errors.name }">
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600">Email *</label>
                        <input v-model="form.email" type="email" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :class="{ 'border-red-400': form.errors.email }">
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600">
                            Password {{ editing ? '(kosongkan bila tidak diganti)' : '*' }}
                        </label>
                        <input
                            v-model="form.password"
                            type="password"
                            :required="!editing"
                            minlength="8"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            :class="{ 'border-red-400': form.errors.password }"
                        >
                        <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
                    </div>
                    <div v-if="!editing">
                        <label class="block text-xs font-semibold text-slate-600">Ulangi password *</label>
                        <input v-model="form.password_confirmation" type="password" required minlength="8" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600">Peran</label>
                        <select v-model="form.role" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="user">User — lapor &amp; lacak tiket</option>
                            <option value="operator">Operator — validasi registrasi &amp; review VPS</option>
                            <option value="admin">Admin — akses penuh</option>
                        </select>
                    </div>
                    <p v-if="form.errors.role" class="text-xs text-red-600">{{ form.errors.role }}</p>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-100" @click="showForm = false">
                            Batal
                        </button>
                        <button type="submit" :disabled="form.processing" class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-bold text-white hover:bg-brand-800 disabled:opacity-60">
                            {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
