<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
});

function submit() {
    form.post(route('verification.resend'), {
        onFinish: () => form.reset('email'),
    });
}
</script>

<template>
    <PublicLayout>
        <section class="mx-auto flex max-w-md flex-col px-4 py-16">
            <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
                <div class="text-3xl">✉️</div>
                <h1 class="mt-3 text-2xl font-bold">Verifikasi Email</h1>
                <p class="mt-2 text-sm text-slate-600">
                    Kami sudah mengirim tautan verifikasi ke alamat email Anda.
                    Klik tautan tersebut untuk memverifikasi email — setelah itu
                    Anda bisa login. Fitur Request VPS terbuka setelah akun
                    divalidasi admin/operator.
                </p>

                <ul class="mt-4 space-y-1.5 text-sm text-slate-500">
                    <li>• Periksa kotak masuk dan folder <b>spam</b>.</li>
                    <li>• Tautan berlaku <b>60 menit</b>.</li>
                    <li>• Belum menerima email? Kirim ulang di bawah.</li>
                </ul>

                <form class="mt-6 space-y-4 border-t border-slate-100 pt-5" @submit.prevent="submit">
                    <div>
                        <label for="resend-email" class="block text-sm font-semibold">Alamat email terdaftar</label>
                        <input
                            id="resend-email"
                            v-model="form.email"
                            type="email"
                            required
                            autocomplete="email"
                            placeholder="nama@instansi.go.id"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-100"
                            :class="{ 'border-red-400': form.errors.email }"
                        >
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-brand-800 disabled:opacity-60"
                    >
                        {{ form.processing ? 'Mengirim…' : 'Kirim ulang tautan verifikasi' }}
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-slate-500">
                    <Link :href="route('login')" class="font-semibold text-brand-700 hover:underline">
                        ← Kembali ke halaman Masuk
                    </Link>
                </p>
            </div>
        </section>
    </PublicLayout>
</template>
