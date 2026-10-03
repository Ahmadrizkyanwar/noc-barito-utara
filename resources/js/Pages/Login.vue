<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <PublicLayout>
        <section class="mx-auto flex max-w-md flex-col px-4 py-16">
            <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
                <h1 class="text-2xl font-bold">Masuk</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Akses dashboard monitoring dan layanan tiket.
                </p>

                <form class="mt-6 space-y-4" @submit.prevent="submit">
                    <div>
                        <label for="email" class="block text-sm font-semibold">Email</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            autocomplete="username"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-100"
                            :class="{ 'border-red-400': form.errors.email }"
                        >
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-semibold">Password</label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            required
                            autocomplete="current-password"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-100"
                            :class="{ 'border-red-400': form.errors.password }"
                        >
                        <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input v-model="form.remember" type="checkbox" class="rounded border-slate-300">
                        Ingat saya
                    </label>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-brand-800 disabled:opacity-60"
                    >
                        {{ form.processing ? 'Memproses…' : 'Masuk' }}
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-slate-500">
                    Belum punya akun?
                    <Link :href="route('register')" class="font-semibold text-brand-700 hover:underline">Daftar di sini</Link>
                </p>
                <p class="mt-2 text-center text-sm text-slate-500">
                    Email belum diverifikasi?
                    <Link :href="route('verification.notice')" class="font-semibold text-brand-700 hover:underline">Kirim ulang tautan</Link>
                </p>
                <p class="mt-2 text-center text-sm text-slate-500">
                    atau
                    <Link :href="route('lapor.index')" class="font-semibold text-brand-700 hover:underline">lapor gangguan tanpa login</Link>
                </p>
            </div>
        </section>
    </PublicLayout>
</template>
