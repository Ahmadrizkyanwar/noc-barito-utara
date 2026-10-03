<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <PublicLayout>
        <section class="mx-auto flex max-w-md flex-col px-4 py-16">
            <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
                <h1 class="text-2xl font-bold">Registrasi Akun</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Daftarkan akun untuk mengakses dashboard dan fitur Request VPS.
                    Registrasi divalidasi oleh admin/operator.
                </p>

                <form class="mt-6 space-y-4" @submit.prevent="submit">
                    <div>
                        <label for="name" class="block text-sm font-semibold">Nama Lengkap *</label>
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            required
                            maxlength="100"
                            autocomplete="name"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-100"
                            :class="{ 'border-red-400': form.errors.name }"
                        >
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-semibold">Email *</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            maxlength="255"
                            autocomplete="username"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-100"
                            :class="{ 'border-red-400': form.errors.email }"
                        >
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-semibold">Password *</label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            required
                            minlength="8"
                            autocomplete="new-password"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-100"
                            :class="{ 'border-red-400': form.errors.password }"
                        >
                        <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-semibold">Ulangi Password *</label>
                        <input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            required
                            minlength="8"
                            autocomplete="new-password"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-100"
                        >
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-brand-800 disabled:opacity-60"
                    >
                        {{ form.processing ? 'Mendaftar…' : 'Daftar' }}
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-slate-500">
                    Sudah punya akun?
                    <Link :href="route('login')" class="font-semibold text-brand-700 hover:underline">Masuk</Link>
                </p>
                <p class="mt-2 text-center text-xs text-slate-400">
                    Akun aktif setelah divalidasi admin/operator.
                </p>
            </div>
        </section>
    </PublicLayout>
</template>
