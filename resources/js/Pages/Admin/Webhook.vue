<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

const props = defineProps({
    webhooks: { type: Array, required: true },
});

const forms = reactive({});
const testing = reactive({});
const messages = reactive({});

for (const wh of props.webhooks) {
    forms[wh.id] = useForm({
        enabled: wh.enabled,
        bot_token: wh.bot_token,
        chat_id: wh.chat_id,
    });
}

function save(id) {
    messages[id] = null;
    forms[id].put(route('admin.webhooks.update', id), {
        preserveScroll: true,
        onSuccess: () => (messages[id] = { ok: true, text: 'Tersimpan.' }),
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
        messages[id] = data.ok
            ? { ok: true, text: 'Pesan terkirim ke Telegram ✓' }
            : { ok: false, text: data.error ?? 'Gagal mengirim.' };
    } catch (e) {
        messages[id] = { ok: false, text: 'Gagal mengirim (jaringan).' };
    } finally {
        testing[id] = false;
    }
}
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

                <p
                    v-if="messages[wh.id]"
                    class="mt-3 rounded-lg px-3 py-2 text-sm"
                    :class="messages[wh.id].ok ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'"
                >
                    {{ messages[wh.id].text }}
                </p>
            </div>
        </div>
    </AdminLayout>
</template>
