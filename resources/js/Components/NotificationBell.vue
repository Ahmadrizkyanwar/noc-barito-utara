<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

/**
 * Lonceng notifikasi in-app.
 * Props datang dari HandleInertiaRequests (`notifications.unread` + `items`).
 *
 * Transpakai Inertia `router.post` (bukan fetch manual) → CSRF, redirect,
 * dan refresh props ditangani framework — badge selalu sinkron dari server.
 */
const page = usePage();

const local = ref(page.props.notifications ?? { unread: 0, items: [] });
const open = ref(false);
const root = ref(null);

watch(
    () => page.props.notifications,
    (v) => {
        local.value = v ?? { unread: 0, items: [] };
    },
    { deep: true }
);

function onDocClick(e) {
    if (open.value && root.value && !root.value.contains(e.target)) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener('click', onDocClick));
onUnmounted(() => document.removeEventListener('click', onDocClick));

const iconOf = (kind) =>
    ({ register: '👤', vps_request: '🖥️', vps_status: '✅', vps_credentials: '🔑', service_request: '🌐', service_status: '✅' })[kind] ?? '🔔';

const fmtTime = (d) => new Date(d).toLocaleString('id-ID', {
    day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit',
});

/**
 * Ambil path relatif — link lama tersimpan sebagai URL absolut APP_URL,
 * bisa menunjuk ke domain lain bila user membuka via IP/LAN.
 */
function toPath(url) {
    if (!url) return null;
    try {
        const u = new URL(url, window.location.origin);
        return u.origin === window.location.origin ? u.pathname + u.search : url;
    } catch {
        return url;
    }
}

function markReadLocal(id) {
    const item = local.value.items.find((i) => i.id === id);
    if (item && !item.read) {
        item.read = true;
        local.value.unread = Math.max(0, local.value.unread - 1);
    }
    return item;
}

function openItem(item) {
    open.value = false;
    const link = toPath(item.link);

    const go = () => {
        const target = link ?? window.location.pathname;
        if (target !== window.location.pathname + window.location.search) {
            router.visit(target);
        }
    };

    if (item.read) {
        go();
        return;
    }

    const previousUnread = local.value.unread;
    const updated = markReadLocal(item.id);

    router.post(toPath(route('notifications.read', item.id)), {}, {
        preserveScroll: true,
        onSuccess: go,
        onError: () => {
            // Gagal menandai → kembalikan badge seperti semula.
            if (updated) updated.read = false;
            local.value.unread = previousUnread;
        },
    });
}

function readAll() {
    local.value.items.forEach((i) => (i.read = true));
    local.value.unread = 0;

    router.post(toPath(route('notifications.readAll')), {}, {
        preserveScroll: true,
    });
}
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="relative rounded-lg p-2 text-lg hover:bg-slate-100"
            aria-label="Notifikasi"
            @click="open = !open"
        >
            🔔
            <span
                v-if="local.unread > 0"
                class="absolute -right-0.5 -top-0.5 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-none text-white"
            >
                {{ local.unread > 99 ? '99+' : local.unread }}
            </span>
        </button>

        <div
            v-if="open"
            class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg"
        >
            <div class="flex items-center justify-between border-b border-slate-100 px-3 py-2">
                <p class="text-sm font-bold">Notifikasi</p>
                <button
                    v-if="local.unread > 0"
                    type="button"
                    class="text-xs font-semibold text-brand-700 hover:underline"
                    @click="readAll"
                >
                    Tandai semua dibaca
                </button>
            </div>

            <ul class="max-h-80 overflow-y-auto">
                <li v-if="local.items.length === 0" class="px-3 py-6 text-center text-sm text-slate-400">
                    Tidak ada notifikasi.
                </li>
                <li
                    v-for="item in local.items"
                    :key="item.id"
                    class="cursor-pointer border-b border-slate-50 px-3 py-2.5 last:border-0 hover:bg-slate-50"
                    @click="openItem(item)"
                >
                    <div class="flex items-start gap-2">
                        <span class="mt-0.5 text-base leading-none">{{ iconOf(item.kind) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-sm font-semibold text-slate-800">
                                <span
                                    v-if="!item.read"
                                    class="h-1.5 w-1.5 shrink-0 rounded-full bg-brand-600"
                                ></span>
                                <span class="truncate">{{ item.title }}</span>
                            </p>
                            <p class="mt-0.5 line-clamp-2 text-xs text-slate-500">{{ item.body }}</p>
                            <p class="mt-1 text-[11px] text-slate-400">{{ fmtTime(item.created_at) }}</p>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>
