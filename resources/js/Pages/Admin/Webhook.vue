<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { onUnmounted, reactive } from 'vue';

const props = defineProps({
    webhooks: { type: Array, required: true },
});

const forms = reactive({});
const testing = reactive({});
const messages = reactive({});
const timers = {};

for (const wh of props.webhooks) {
    forms[wh.id] = useForm({
        enabled: wh.enabled,
        bot_token: wh.bot_token,
        chat_id: wh.chat_id,
    });
}

/** Notifikasi per kartu — sukses otomatis hilang, error tetap sampai ditutup. */
function flash(id, ok, text) {
    messages[id] = { ok, text };
    clearTimeout(timers[id]);
    if (ok) {
        timers[id] = setTimeout(() => (messages[id] = null), 4000);
    }
}

function save(id) {
    messages[id] = null;
    forms[id].put(route('admin.webhooks.update', id), {
        preserveScroll: true,
        onSuccess: () => flash(id, true, 'Pengaturan tersimpan.'),
        onError: () => flash(id, false, 'Gagal menyimpan — periksa isian form.'),
    });
}

async function test(id) {
    testing[id] = true;
    messages[id] = null;
    try {
        const res = await fetch(route('admin.webhooks.test', id), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
        });
        const data = await res.json();
        if (data.ok) {
            flash(id, true, 'Pesan terkirim ke Telegram ✓');
        } else {
            flash(id, false, data.error ?? 'Gagal mengirim pesan.');
        }
    } catch {
        flash(id, false, 'Gagal mengirim — periksa koneksi jaringan.');
    } finally {
        testing[id] = false;
    }
}

onUnmounted(() => Object.values(timers).forEach(clearTimeout));
</script>

<template>
    <AdminLayout>
        <div>
            <h1 class="text-xl font-bold">Pengaturan — Webhook Telegram</h1>
            <p class="text-sm text-slate-500">
                Dua webhook tetap per fungsi. Masukkan <b>Bot Token</b> dan <b>Chat ID</b>, aktifkan, lalu uji kirim.
            </p>
        </div>

        <div class="mt-6 grid gap-5 lg:grid-cols-2">
            <div v-for="wh in webhooks" :key="wh.id" class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="font-bold">{{ wh.label }}</h2>
                        <p class="mt-0.5 text-xs text-slate-500">{{ wh.description }}</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm font-semibold">
                        <input v-model="forms[wh.id].enabled" type="checkbox" class="rounded border-slate-300">
                        {{ forms[wh.id].enabled ? 'Aktif' : 'Nonaktif' }}
                    </label>
                </div>

                <div class="mt-4 space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600">Bot Token</label>
                        <input
                            v-model="forms[wh.id].bot_token"
                            type="password"
                            placeholder="123456:ABC-DEF…"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm focus:border-brand-600 focus:outline-none"
                            :class="{ 'border-red-400': forms[wh.id].errors.bot_token }"
                        >
                        <p v-if="forms[wh.id].errors.bot_token" class="mt-1 text-xs text-red-600">
                            {{ forms[wh.id].errors.bot_token }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600">Chat ID</label>
                        <input
                            v-model="forms[wh.id].chat_id"
                            type="text"
                            placeholder="-1001234567890"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm focus:border-brand-600 focus:outline-none"
                            :class="{ 'border-red-400': forms[wh.id].errors.chat_id }"
                        >
                        <p v-if="forms[wh.id].errors.chat_id" class="mt-1 text-xs text-red-600">
                            {{ forms[wh.id].errors.chat_id }}
                        </p>
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-2">
                    <button
                        class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-bold text-white hover:bg-brand-800 disabled:opacity-60"
                        :disabled="forms[wh.id].processing"
                        @click="save(wh.id)"
                    >
                        {{ forms[wh.id].processing ? 'Menyimpan…' : 'Simpan' }}
                    </button>
                    <button
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-100 disabled:opacity-60"
                        :disabled="testing[wh.id]"
                        @click="test(wh.id)"
                    >
                        {{ testing[wh.id] ? 'Mengirim…' : 'Uji kirim' }}
                    </button>
                </div>

                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 -translate-y-1"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div
                        v-if="messages[wh.id]"
                        class="mt-3 flex items-start gap-2 rounded-lg border px-3 py-2.5 text-sm shadow-sm"
                        :class="messages[wh.id].ok
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                            : 'border-red-200 bg-red-50 text-red-700'"
                        role="status"
                    >
                        <span class="mt-px leading-none">{{ messages[wh.id].ok ? '✅' : '⚠️' }}</span>
                        <span class="flex-1 leading-snug">{{ messages[wh.id].text }}</span>
                        <button
                            type="button"
                            class="shrink-0 rounded p-0.5 leading-none opacity-50 hover:opacity-100"
                            aria-label="Tutup notifikasi"
                            @click="messages[wh.id] = null"
                        >
                            ✕
                        </button>
                    </div>
                </Transition>
            </div>
        </div>
    </AdminLayout>
</template>
