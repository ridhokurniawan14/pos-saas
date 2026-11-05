<script setup lang="ts">
import { UserRound, X } from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';

type C = { id: string; name: string; phone: string | null; points?: number };

defineProps<{ modelValue: C | null }>();
const emit = defineEmits<{ 'update:modelValue': [c: C | null] }>();

const open = ref(false);
const mode = ref<'search' | 'new'>('search');
const q = ref('');
const results = ref<C[]>([]);
const loading = ref(false);
const error = ref('');
const nf = ref({ name: '', phone: '' });
const saving = ref(false);

const xsrf = () =>
    decodeURIComponent(document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');

const load = async () => {
    loading.value = true;
    try {
        const res = await fetch('/pos/customers?search=' + encodeURIComponent(q.value), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        results.value = res.ok ? await res.json() : [];
    } catch {
        results.value = [];
    } finally {
        loading.value = false;
    }
};

let timer: number | undefined;
watch(q, () => {
    clearTimeout(timer);
    timer = window.setTimeout(load, 250);
});
onBeforeUnmount(() => clearTimeout(timer));

const openPicker = () => {
    open.value = true;
    mode.value = 'search';
    q.value = '';
    error.value = '';
    load();
};

const pick = (c: C) => {
    emit('update:modelValue', c);
    open.value = false;
};

const create = async () => {
    error.value = '';
    saving.value = true;
    try {
        const res = await fetch('/pos/customers', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf() },
            body: JSON.stringify({ name: nf.value.name, phone: nf.value.phone || null }),
        });
        const d = await res.json().catch(() => null);
        if (res.ok) {
            nf.value = { name: '', phone: '' };
            pick(d);
            return;
        }
        error.value = d?.errors ? (Object.values(d.errors)[0] as string[])[0] : (d?.message ?? 'Gagal menyimpan pelanggan.');
    } catch {
        error.value = 'Tidak ada koneksi. Coba lagi.';
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <div class="cp-row">
        <template v-if="modelValue">
            <div class="cp-chip">
                <UserRound :size="16" />
                <span class="cp-name">{{ modelValue.name }}</span>
                <span v-if="modelValue.phone" class="cp-muted">{{ modelValue.phone }}</span>
                <span v-if="modelValue.points" class="cp-muted">{{ modelValue.points }} poin</span>
            </div>
            <button type="button" class="cp-link" @click="openPicker">Ganti</button>
            <button type="button" class="cp-icon" aria-label="Hapus pelanggan" @click="emit('update:modelValue', null)"><X :size="16" /></button>
        </template>
        <button v-else type="button" class="cp-pick" @click="openPicker">
            <UserRound :size="16" /> Pilih pelanggan (opsional)
        </button>
    </div>

    <Teleport to="body">
        <div v-if="open" class="cp-overlay" @click.self="open = false">
            <div class="cp-box">
                <div class="cp-head">
                    <h3 class="cp-title">Pelanggan</h3>
                    <button type="button" class="cp-btn" @click="open = false">Tutup</button>
                </div>

                <div class="cp-tabs">
                    <button type="button" :class="{ on: mode === 'search' }" @click="mode = 'search'">Cari</button>
                    <button type="button" :class="{ on: mode === 'new' }" @click="mode = 'new'; error = ''">Pelanggan baru</button>
                </div>

                <template v-if="mode === 'search'">
                    <input v-model="q" type="search" class="cp-input" placeholder="Cari nama atau telepon" autofocus />
                    <p v-if="loading" class="cp-muted cp-center">Memuat...</p>
                    <button v-for="c in results" :key="c.id" type="button" class="cp-item" @click="pick(c)">
                        <span class="cp-name">{{ c.name }}</span>
                        <span class="cp-muted">{{ c.phone || '-' }}</span>
                    </button>
                    <p v-if="!loading && !results.length" class="cp-muted cp-center">
                        Tidak ditemukan.
                        <button type="button" class="cp-link" @click="mode = 'new'; nf.name = q">Tambah sebagai pelanggan baru</button>
                    </p>
                </template>

                <template v-else>
                    <input v-model="nf.name" class="cp-input" placeholder="Nama pelanggan" />
                    <input v-model="nf.phone" inputmode="tel" class="cp-input" placeholder="Telepon / WhatsApp (opsional)" />
                    <p v-if="error" class="cp-error">{{ error }}</p>
                    <div class="cp-actions">
                        <button type="button" class="cp-btn" @click="mode = 'search'">Batal</button>
                        <button type="button" class="cp-btn cp-primary" :disabled="saving || !nf.name.trim()" @click="create">
                            {{ saving ? 'Menyimpan...' : 'Simpan dan pilih' }}
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </Teleport>
</template>

<style>
.cp-row { display: flex; align-items: center; gap: 8px; }
.cp-pick { cursor: pointer; display: flex; align-items: center; gap: 8px; width: 100%; padding: 9px 12px; border: 1px dashed #a1a1aa; border-radius: 10px; background: transparent; color: inherit; font-size: 14px; }
.cp-pick:hover { background: rgba(127, 127, 127, 0.12); }
.cp-chip { display: flex; flex: 1; min-width: 0; align-items: center; gap: 8px; padding: 8px 12px; border: 1px solid #d4d4d8; border-radius: 10px; font-size: 14px; }
.dark .cp-chip { border-color: #3f3f46; }
.cp-name { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cp-muted { color: #71717a; font-size: 13px; }
.cp-center { text-align: center; }
.cp-link { cursor: pointer; border: 0; background: none; color: inherit; text-decoration: underline; font-size: 13px; }
.cp-icon { cursor: pointer; display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border: 0; border-radius: 8px; background: transparent; color: inherit; }
.cp-icon:hover { background: rgba(127, 127, 127, 0.15); }
.cp-overlay { position: fixed; inset: 0; z-index: 80; display: flex; align-items: center; justify-content: center; padding: 16px; background: rgba(0, 0, 0, 0.6); }
.cp-box { width: 100%; max-width: 420px; max-height: calc(100svh - 32px); overflow-y: auto; display: flex; flex-direction: column; gap: 10px; padding: 20px; border: 1px solid #e4e4e7; border-radius: 16px; background: #fff; color: #111; font-size: 14px; }
.dark .cp-box { background: #171717; color: #fafafa; border-color: #2e2e2e; }
.cp-head { display: flex; align-items: center; justify-content: space-between; }
.cp-title { margin: 0; font-size: 17px; font-weight: 600; }
.cp-tabs { display: flex; overflow: hidden; border: 1px solid #d4d4d8; border-radius: 10px; }
.dark .cp-tabs { border-color: #3f3f46; }
.cp-tabs button { flex: 1; cursor: pointer; padding: 9px 10px; border: 0; background: transparent; color: inherit; font-weight: 500; }
.cp-tabs button.on { background: #111; color: #fff; }
.dark .cp-tabs button.on { background: #fafafa; color: #111; }
.cp-input { width: 100%; padding: 11px 12px; border: 1px solid #d4d4d8; border-radius: 10px; background: transparent; color: inherit; font-size: 15px; outline: none; }
.dark .cp-input { border-color: #3f3f46; background: #171717; color-scheme: dark; }
.cp-item { cursor: pointer; display: flex; flex-direction: column; align-items: flex-start; gap: 2px; width: 100%; padding: 10px 12px; border: 1px solid #e4e4e7; border-radius: 10px; background: transparent; color: inherit; text-align: left; }
.dark .cp-item { border-color: #2e2e2e; }
.cp-item:hover { background: rgba(127, 127, 127, 0.12); }
.cp-actions { display: flex; justify-content: flex-end; gap: 10px; }
.cp-btn { cursor: pointer; padding: 9px 16px; border: 1px solid #d4d4d8; border-radius: 10px; background: transparent; color: inherit; font-weight: 500; }
.dark .cp-btn { border-color: #3f3f46; }
.cp-btn:hover { background: rgba(127, 127, 127, 0.15); }
.cp-primary { background: #111; color: #fff; border-color: #111; }
.dark .cp-primary { background: #fafafa; color: #111; border-color: #fafafa; }
.cp-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.cp-error { margin: 0; padding: 10px 12px; border-radius: 10px; background: rgba(220, 38, 38, 0.1); color: #dc2626; }
</style>