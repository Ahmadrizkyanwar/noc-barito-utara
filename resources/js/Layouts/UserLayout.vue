<script setup>
import NotificationBell from '@/Components/NotificationBell.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const auth = computed(() => page.props.auth?.user);
const flash = computed(() => page.props.flash);
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <header class="sticky top-0 z-40 border-b border-slate-200 bg-white">
            <div class="mx-auto flex h-16 max-w-5xl items-center justify-between px-4">
                <Link :href="route('landing')" class="flex items-center gap-3">
                    <img src="/noc-logo.png" alt="NOC" class="h-9 w-9 rounded" @error="$event.target.style.display='none'">
                    <span class="text-sm font-bold leading-tight">
                        NOC Kabupaten Barito Utara
                        <span class="block text-[11px] font-medium text-slate-500">Dashboard Pengguna</span>
                    </span>
                </Link>
                <div class="flex items-center gap-3">
                    <NotificationBell />
                    <span class="hidden text-sm text-slate-500 sm:inline">{{ auth?.name }}</span>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-semibold hover:bg-slate-100"
                    >
                        Keluar
                    </Link>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-6">
            <div v-if="flash?.success" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ flash.success }}
            </div>
            <div v-if="flash?.error" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ flash.error }}
            </div>
            <slot />
        </main>
    </div>
</template>
