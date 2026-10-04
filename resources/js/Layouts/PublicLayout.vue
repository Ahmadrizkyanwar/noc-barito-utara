<script setup>
import NotificationBell from '@/Components/NotificationBell.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import logo from '@/images/noc-logo-colored.png';

const page = usePage();
const auth = computed(() => page.props.auth?.user);
const flash = computed(() => page.props.flash);
</script>

<template>
    <div class="min-h-screen flex flex-col bg-slate-50">
        <!-- Navbar publik -->
        <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/90 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4">
                <Link :href="route('landing')" class="flex items-center gap-3">
                    <img :src="logo" alt="NOC" class="h-9 w-9 rounded" @error="$event.target.style.display='none'">
                    <span class="text-sm font-bold leading-tight">
                        NOC Kabupaten Barito Utara
                        <span class="block text-[11px] font-medium text-slate-500">Diskominfosandi</span>
                    </span>
                </Link>

                <nav class="flex items-center gap-2">
                    <NotificationBell v-if="auth" />
                    <Link
                        v-if="!auth"
                        :href="route('register')"
                        class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100"
                    >
                        Daftar
                    </Link>
                    <Link
                        v-if="!auth"
                        :href="route('login')"
                        class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100"
                    >
                        Masuk
                    </Link>
                    <Link
                        :href="route('lapor.index')"
                        class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800"
                    >
                        Lapor Gangguan
                    </Link>
                    <Link
                        v-if="auth"
                        :href="auth.role === 'admin' ? route('admin.dashboard') : route('dashboard')"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-100"
                    >
                        Dashboard
                    </Link>
                </nav>
            </div>
        </header>

        <!-- Flash -->
        <div v-if="flash?.success" class="mx-auto mt-4 w-full max-w-6xl px-4">
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ flash.success }}
            </div>
        </div>
        <div v-if="flash?.error" class="mx-auto mt-4 w-full max-w-6xl px-4">
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ flash.error }}
            </div>
        </div>

        <main class="flex-1">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="border-t border-slate-200 bg-white">
            <div class="mx-auto max-w-6xl px-4 py-6">
                <div class="flex flex-col items-center gap-1 text-center text-xs text-slate-500">
                    <p>© {{ new Date().getFullYear() }} Diskominfosandi Kabupaten Barito Utara — Network Operation Center</p>
                    <p>Pemantauan jaringan: SNMP · ICMP · RouterOS API</p>
                </div>
            </div>
        </footer>
    </div>
</template>
