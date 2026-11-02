<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps<{
    mode: 'save' | 'list' | null;
    drafts: any[];
    products: { id: string; name: string }[];
    error?: string;
}>();
const emit = defineEmits<{ close: []; save: [label: string]; resume: [draft: any] }>();

const label = ref('');
const target = ref<any>(null);

watch(() => props.mode, (m) => {
    if (m === 'save') label.value = '';
});

const preview = (d: any) => {
    const names = d.items.slice(0, 2).map((i: any) => props.products.find((p) => p.id === i.product_id)?.name ?? 'Produk dihapus');
    return names.join(', ') + (d.items.length > 2 ? ` +${d.items.length - 2} lainnya` : '');
};
const qty = (d: any) => d.items.reduce((s: number, i: any) => s + i.qty, 0);

const remove = () => {
    if (!target.value) return;
    router.delete(`/pos/drafts/${target.value.id}`, { preserveState: true, preserveScroll: true });
    target.value = null;
};
</script>

<template>
    <Teleport to="body">
        <!-- Simpan draft -->
        <div v-if="mode === 'save'" class="dm-overlay" @click.self="emit('close')">
            <div class="dm-box">
                <h3 class="dm-title">Simpan sebagai draft</h3>
                <p class="dm-muted">Keranjang disimpan dan bisa dilanjutkan nanti. Draft dihapus otomatis setelah 7 hari.</p>
                <input v-model="label" maxlength="60" class="dm-input" placeholder="Nama / label (opsional, mis. Bu Ani)" @keyup.enter="emit('save', label)" />
                <p v-if="error" class="dm-error">{{ error }}</p>
                <div class="dm-actions">
                    <button type="button" class="dm-btn" @click="emit('close')">Batal</button>
                    <button type="button" class="dm-btn dm-primary" @click="emit('save', label)">Simpan draft</button>
                </div>
            </div>
        </div>

        <!-- Daftar draft -->
        <div v-if="mode === 'list'" class="dm-overlay" @click.self="emit('close')">
            <div class="dm-box dm-wide">
                <div class="dm-head">
                    <h3 class="dm-title">Draft keranjang</h3>
                    <button type="button" class="dm-btn" @click="emit('close')">Tutup</button>
                </div>

                <p v-if="!drafts.length" class="dm-empty">Belum ada draft.</p>

                <div v-for="d in drafts" :key="d.id" class="dm-item">
                    <div class="dm-grow">
                        <div class="dm-name">{{ d.label || 'Tanpa nama' }}</div>
                        <div class="dm-muted">{{ preview(d) }}</div>
                        <div class="dm-muted">{{ d.items.length }} jenis · {{ qty(d) }} barang · {{ d.time }}<span v-if="d.by"> · {{ d.by }}</span></div>
                    </div>
                    <div class="dm-side">
                        <button type="button" class="dm-btn dm-primary" @click="emit('resume', d)">Lanjutkan</button>
                        <button type="button" class="dm-link" @click="target = d">Hapus</button>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmDialog
            :open="!!target"
            title="Hapus draft ini?"
            :message="target?.label || 'Tanpa nama'"
            confirm-text="Ya, hapus"
            danger
            @confirm="remove"
            @cancel="target = null"
        />
    </Teleport>
</template>

<style>
.dm-overlay { position: fixed; inset: 0; z-index: 75; display: flex; align-items: center; justify-content: center; padding: 16px; background: rgba(0, 0, 0, 0.6); overflow-y: auto; }
.dm-box { width: 100%; max-width: 400px; max-height: calc(100svh - 32px); overflow-y: auto; display: flex; flex-direction: column; gap: 12px; padding: 22px; border: 1px solid #e4e4e7; border-radius: 16px; background: #fff; color: #111; font-size: 14px; }
.dark .dm-box { background: #171717; color: #fafafa; border-color: #2e2e2e; }
.dm-wide { max-width: 520px; }
.dm-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.dm-title { margin: 0; font-size: 17px; font-weight: 600; }
.dm-muted { margin: 0; color: #71717a; font-size: 13px; }
.dm-empty { margin: 0; padding: 24px 0; text-align: center; color: #71717a; }
.dm-input { width: 100%; padding: 11px 12px; border: 1px solid #d4d4d8; border-radius: 10px; background: transparent; color: inherit; font-size: 15px; outline: none; }
.dark .dm-input { border-color: #3f3f46; background: #171717; }
.dm-error { margin: 0; padding: 10px 12px; border-radius: 10px; background: rgba(220, 38, 38, 0.1); color: #dc2626; }
.dm-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 6px; }
.dm-btn { cursor: pointer; padding: 9px 16px; border: 1px solid #d4d4d8; border-radius: 10px; background: transparent; color: inherit; font-weight: 500; }
.dark .dm-btn { border-color: #3f3f46; }
.dm-btn:hover { background: rgba(127, 127, 127, 0.15); }
.dm-primary, .dark .dm-primary { background: #111; color: #fff; border-color: #111; }
.dark .dm-primary { background: #fafafa; color: #111; border-color: #fafafa; }
.dm-item { display: flex; align-items: center; gap: 12px; padding: 12px; border: 1px solid #e4e4e7; border-radius: 12px; }
.dark .dm-item { border-color: #2e2e2e; }
.dm-grow { flex: 1; min-width: 0; }
.dm-name { font-weight: 600; }
.dm-side { display: flex; flex-direction: column; align-items: stretch; gap: 6px; text-align: center; }
.dm-link { cursor: pointer; border: 0; background: none; color: #dc2626; font-size: 13px; }
</style>