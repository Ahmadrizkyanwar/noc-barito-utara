<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import shadowUrl from 'leaflet/dist/images/marker-shadow.png';
import 'leaflet/dist/leaflet.css';

/**
 * Pemilih titik koordinat (Leaflet + tile OpenStreetMap, tanpa API key).
 *
 * v-model = { lat, lng } | null
 *   - Klik peta / geser marker → memperbarui nilai.
 *   - Tombol GPS → "share lokasi" dari browser.
 *   - readonly = hanya menampilkan marker (admin detail tiket).
 */
const props = defineProps({
    modelValue: { type: Object, default: null },
    readonly: { type: Boolean, default: false },
    height: { type: String, default: '260px' },
});

const emit = defineEmits(['update:modelValue']);

const el = ref(null);
const ready = ref(false);
const geoLoading = ref(false);
const geoError = ref(null);

const point = computed(() =>
    props.modelValue?.lat != null && props.modelValue?.lng != null ? props.modelValue : null
);

const pointText = computed(() =>
    point.value ? `${r6(point.value.lat)}, ${r6(point.value.lng)}` : null
);

// Pusat default: Kabupaten Barito Utara.
const DEFAULT_CENTER = [-1.5, 114.7];
const DEFAULT_ZOOM = 9;

let L = null;
let map = null;
let marker = null;

const r6 = (v) => Math.round(Number(v) * 1e6) / 1e6;

function emitPoint(lat, lng) {
    emit('update:modelValue', { lat: r6(lat), lng: r6(lng) });
}

function applyMarker(value) {
    if (!map || !L) return;

    if (value === null || value?.lat == null || value?.lng == null) {
        if (marker) {
            map.removeLayer(marker);
            marker = null;
        }
        return;
    }

    const ll = L.latLng(value.lat, value.lng);

    if (!marker) {
        marker = L.marker(ll, { draggable: !props.readonly }).addTo(map);

        if (!props.readonly) {
            marker.on('dragend', () => {
                const p = marker.getLatLng();
                emitPoint(p.lat, p.lng);
            });
        }
    } else {
        marker.setLatLng(ll);
    }
}

async function init() {
    const mod = await import('leaflet');
    L = mod.default ?? mod;
    L.Icon.Default.mergeOptions({ iconUrl, iconRetinaUrl, shadowUrl });

    const start = point.value ? [point.value.lat, point.value.lng] : DEFAULT_CENTER;
    const zoom = point.value ? 15 : DEFAULT_ZOOM;

    map = L.map(el.value).setView(start, zoom);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    map.on('click', (e) => {
        if (props.readonly) return;
        if (map.getZoom() < 13) map.setView(e.latlng, 15);
        emitPoint(e.latlng.lat, e.latlng.lng);
    });

    applyMarker(point.value);
    ready.value = true;
}

function geolocate() {
    geoError.value = null;

    if (! navigator.geolocation) {
        geoError.value = 'Perangkat tidak mendukung pembagian lokasi. Silakan klik peta.';
        return;
    }

    geoLoading.value = true;
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            geoLoading.value = false;
            const { latitude, longitude } = pos.coords;
            emitPoint(latitude, longitude);
            map?.setView([latitude, longitude], 16);
        },
        (err) => {
            geoLoading.value = false;
            geoError.value = err.code === err.PERMISSION_DENIED
                ? 'Izin lokasi ditolak. Silakan klik peta untuk memilih titik.'
                : 'Gagal mendapatkan lokasi. Silakan klik peta untuk memilih titik.';
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
    );
}

function clearPoint() {
    geoError.value = null;
    emit('update:modelValue', null);
}

watch(
    () => props.modelValue,
    (v) => {
        if (map) applyMarker(v);
    },
    { deep: true }
);

onMounted(init);

onUnmounted(() => {
    map?.remove();
    map = null;
    marker = null;
});
</script>

<template>
    <div>
        <div
            ref="el"
            class="relative overflow-hidden rounded-lg border border-slate-300 bg-slate-100"
            :style="{ height }"
        >
            <p
                v-if="!ready"
                class="flex h-full items-center justify-center text-xs text-slate-400"
            >
                Memuat peta…
            </p>
        </div>

        <div class="mt-2 flex flex-wrap items-center gap-2">
            <template v-if="!readonly">
                <button
                    type="button"
                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 disabled:opacity-60"
                    :disabled="geoLoading"
                    @click="geolocate"
                >
                    {{ geoLoading ? 'Mencari lokasi…' : '📍 Gunakan lokasi saya' }}
                </button>
                <button
                    v-if="point"
                    type="button"
                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-500 hover:bg-slate-100"
                    @click="clearPoint"
                >
                    Hapus titik
                </button>
            </template>

            <p class="text-xs text-slate-500">
                <template v-if="pointText">
                    Titik terpilih: <b class="font-mono">{{ pointText }}</b>
                </template>
                <template v-else>Klik peta untuk memilih titik lokasi.</template>
            </p>
        </div>

        <p v-if="geoError" class="mt-1 text-xs text-red-600">{{ geoError }}</p>
    </div>
</template>
