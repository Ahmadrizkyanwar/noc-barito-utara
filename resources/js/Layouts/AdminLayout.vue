<script setup>
import NotificationBell from '@/Components/NotificationBell.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import logo from '@/images/noc-logo-colored.png';

const page = usePage();
const auth = computed(() => page.props.auth?.user);
const flash = computed(() => page.props.flash);
const url = computed(() => new URL(page.url, window.location.origin).pathname);

const menus = [
    { label: 'Dashboard', href: route('admin.dashboard'), exact: true, roles: ['admin'] },
    { label: 'Jaringan', href: route('admin.devices.index'), prefix: '/admin/jaringan', roles: ['admin'] },
    { label: 'Layanan (Tiket)', href: route('admin.tickets.index'), prefix: '/admin/layanan', roles: ['admin'] },
    { label: 'Registrasi User', href: route('admin.registrations.index'), prefix: '/admin/registrasi', roles: ['admin', 'operator'] },
    { label: 'Review VPS', href: route('admin.vps.index'), prefix: '/admin/vps', roles: ['admin', 'operator'] },
    { label: 'Review Pendaftaran', href: route('admin.services.index'), prefix: '/admin/pendaftaran', roles: ['admin', 'operator'] },
    { label: 'Pengguna', href: route('admin.users.index'), prefix: '/admin/pengaturan/user', roles: ['admin'] },
    { label: 'Webhook Telegram', href: route('admin.webhooks.index'), prefix: '/admin/pengaturan/webhook', roles: ['admin'] },
];

const visibleMenus = computed(() => {
    const role = auth.value?.role;
    return menus.filter((m) => !m.roles || m.roles.includes(role));
});

function isActive(menu) {
    if (menu.exact) {
        return url.value === '/admin';
    }
    return menu.prefix ? url.value.startsWith(menu.prefix) : false;
}
</script>

<template>
    <div class="min-h-screen bg-slate-100">
        <header class="sticky top-0 z-40 border-b border-slate-200 bg-white">
            <div class="flex h-16 items-center justify-between px-4">
                <div class="flex items-center gap-3">
                    <img :src="logo" alt="NOC" class="h-9 w-9 rounded" @error="$event.target.style.display='none'">
                    <div class="text-sm font-bold leading-tight">
                        NOC Kabupaten Barito Utara
                        <span class="block text-[11px] font-medium text-slate-500">
                            {{ auth?.role === 'operator' ? 'Panel Operator' : 'Panel Administrator' }}
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <NotificationBell />
                    <Link :href="route('landing')" class="text-sm font-semibold text-slate-600 hover:text-brand-700">Situs</Link>
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

        <div class="mx-auto flex max-w-7xl gap-6 px-4 py-6">
            <!-- Sidebar -->
            <aside class="hidden w-56 shrink-0 lg:block">
                <nav class="space-y-1 rounded-xl border border-slate-200 bg-white p-3">
                    <Link
                        v-for="menu in visibleMenus"
                        :key="menu.label"
                        :href="menu.href"
                        class="block rounded-lg px-3 py-2 text-sm font-semibold transition"
                        :class="isActive(menu) ? 'bg-brand-700 text-white' : 'text-slate-600 hover:bg-slate-100'"
                    >
                        {{ menu.label }}
                    </Link>
                </nav>
            </aside>

            <!-- Nav mobile -->
            <div class="w-full lg:hidden">
                <div class="mb-4 flex gap-2 overflow-x-auto rounded-xl border border-slate-200 bg-white p-2">
                    <Link
                        v-for="menu in visibleMenus"
                        :key="menu.label"
                        :href="menu.href"
                        class="whitespace-nowrap rounded-lg px-3 py-1.5 text-xs font-semibold"
                        :class="isActive(menu) ? 'bg-brand-700 text-white' : 'text-slate-600 hover:bg-slate-100'"
                    >
                        {{ menu.label }}
                    </Link>
                </div>
            </div>

            <main class="min-w-0 flex-1">
                <div v-if="flash?.success" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ flash.success }}
                </div>
                <div v-if="flash?.error" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ flash.error }}
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>
