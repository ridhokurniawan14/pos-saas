<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import MoneyInput from '@/components/MoneyInput.vue';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{ shift: any }>();
defineEmits<{ close: [] }>();

const rupiah = (n: number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n || 0);
const methodLabel: Record<string, string> = { cash: 'Tunai', qris: 'QRIS', debit: 'Debit', transfer: 'Transfer' };

const tab = ref<'kas' | 'tutup'>('kas');

/* kas masuk / keluar */
const type = ref<'in' | 'out'>('out');
const amount = ref<number | null>(null);
const reason = ref('');
const error = ref('');
const saving = ref(false);

const addCash = () => {
    error.value = '';
    saving.value = true;
    router.post(
        `/shifts/${props.shift.id}/cash`,
        { type: type.value, amount: amount.value ?? 0, reason: reason.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                amount.value = null;
                reason.value = '';
            },
            onError: (e) => (error.value = Object.values(e)[0] as string),
            onFinish: () => (saving.value = false),
        },
    );
};

/* tutup kasir */
const counted = ref<number | null>(null);
const note = ref('');
const confirmOpen = ref(false);
const closeError = ref('');
const diff = computed(() => (counted.value ?? 0) - props.shift.expected_cash);

const doClose = () => {
    confirmOpen.value = false;
    closeError.value = '';
    router.post(
        `/shifts/${props.shift.id}/close`,
        { closing_cash: counted.value ?? 0, note: note.value || null },
        { onError: (e) => (closeError.value = Object.values(e)[0] as string) },
    );
};
</script>

<template>
    <Teleport to="body">
        <div class="sp-overlay" @click.self="$emit('close')">
            <div class="sp-box">
                <div class="sp-head">
                    <div>
                        <div class="sp-title">Kas &amp; Tutup Kasir</div>
                        <div class="sp-muted">{{ shift.outlet_name }} · buka {{ shift.opened_at }}</div>
                    </div>
                    <button type="button" class="sp-btn" @click="$emit('close')">Tutup</button>
                </div>

                <div class="sp-card">
                    <div class="sp-row"><span>Modal awal</span><span>{{ rupiah(shift.opening_cash) }}</span></div>
                    <div class="sp-row"><span>Penjualan tunai</span><span>{{ rupiah(shift.cash_sales) }}</span></div>
                    <div class="sp-row"><span>Kas masuk</span><span>+{{ rupiah(shift.cash_in) }}</span></div>
                    <div class="sp-row"><span>Kas keluar</span><span>-{{ rupiah(shift.cash_out) }}</span></div>
                    <div class="sp-row sp-bold sp-line"><span>Uang seharusnya di laci</span><span>{{ rupiah(shift.expected_cash) }}</span></div>
                </div>

                <div class="sp-card">
                    <div class="sp-row"><span>Transaksi</span><span>{{ shift.orders_count }}</span></div>
                    <div class="sp-row"><span>Total penjualan</span><span>{{ rupiah(shift.sales_total) }}</span></div>
                    <div v-for="(v, k) in shift.methods" :key="k" class="sp-row sp-muted">
                        <span>· {{ methodLabel[k as string] ?? k }}</span><span>{{ rupiah(v as number) }}</span>
                    </div>
                </div>

                <div class="sp-tabs">
                    <button type="button" :class="{ on: tab === 'kas' }" @click="tab = 'kas'">Kas masuk / keluar</button>
                    <button type="button" :class="{ on: tab === 'tutup' }" @click="tab = 'tutup'">Tutup kasir</button>
                </div>

                <div v-if="tab === 'kas'" class="sp-stack">
                    <div class="sp-seg">
                        <button type="button" :class="{ on: type === 'out' }" @click="type = 'out'">Kas keluar</button>
                        <button type="button" :class="{ on: type === 'in' }" @click="type = 'in'">Kas masuk</button>
                    </div>
                    <MoneyInput v-model="amount" placeholder="Jumlah (Rp)" class="sp-input" />
                    <input v-model="reason" maxlength="100" placeholder="Keterangan (mis. beli es batu, setor uang)" class="sp-input" />
                    <p v-if="error" class="sp-error">{{ error }}</p>
                    <button type="button" class="sp-primary" :disabled="saving || !amount || !reason.trim()" @click="addCash">Simpan</button>

                    <div v-if="shift.movements.length" class="sp-card">
                        <div v-for="m in shift.movements" :key="m.id" class="sp-row">
                            <span>{{ m.time }} · {{ m.reason }}</span>
                            <span :class="m.type === 'in' ? 'sp-ok' : 'sp-bad'">{{ m.type === 'in' ? '+' : '-' }}{{ rupiah(m.amount) }}</span>
                        </div>
                    </div>
                </div>

                <div v-else class="sp-stack">
                    <p class="sp-muted">Hitung uang fisik di laci, lalu isi di bawah.</p>
                    <MoneyInput v-model="counted" placeholder="Uang fisik di laci (Rp)" class="sp-input" />
                    <div v-if="counted !== null" class="sp-row sp-bold">
                        <span>Selisih</span>
                        <span :class="diff === 0 ? 'sp-ok' : 'sp-bad'">
                            {{ diff === 0 ? 'Pas' : (diff > 0 ? 'Lebih ' : 'Kurang ') + rupiah(Math.abs(diff)) }}
                        </span>
                    </div>
                    <input v-model="note" maxlength="200" placeholder="Catatan (opsional)" class="sp-input" />
                    <p v-if="closeError" class="sp-error">{{ closeError }}</p>
                    <button type="button" class="sp-primary sp-danger" :disabled="counted === null" @click="confirmOpen = true">Tutup Kasir</button>
                </div>
            </div>
        </div>

        <ConfirmDialog
            :open="confirmOpen"
            title="Tutup kasir sekarang?"
            message="Setelah ditutup, kamu harus membuka kasir lagi untuk bertransaksi."
            confirm-text="Ya, tutup kasir"
            danger
            @confirm="doClose"
            @cancel="confirmOpen = false"
        />
    </Teleport>
</template>

<style>
.sp-overlay { position: fixed; inset: 0; z-index: 75; display: flex; align-items: flex-start; justify-content: center; padding: 16px; background: rgba(0, 0, 0, 0.6); overflow-y: auto; }
.sp-box { width: 100%; max-width: 480px; margin: auto; display: flex; flex-direction: column; gap: 14px; padding: 20px; border: 1px solid #e4e4e7; border-radius: 16px; background: #fff; color: #111; font-size: 14px; }
.dark .sp-box { background: #171717; color: #fafafa; border-color: #2e2e2e; }
.sp-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
.sp-title { font-size: 18px; font-weight: 700; }
.sp-muted { color: #71717a; font-size: 13px; }
.sp-bold { font-weight: 700; }
.sp-card { display: flex; flex-direction: column; gap: 6px; padding: 12px; border: 1px solid #e4e4e7; border-radius: 12px; }
.dark .sp-card { border-color: #2e2e2e; }
.sp-row { display: flex; justify-content: space-between; gap: 10px; }
.sp-line { padding-top: 8px; border-top: 1px dashed #a1a1aa; }
.sp-ok { color: #16a34a; }
.sp-bad { color: #dc2626; }
.sp-stack { display: flex; flex-direction: column; gap: 10px; }
.sp-btn { cursor: pointer; padding: 7px 14px; border: 1px solid #d4d4d8; border-radius: 10px; background: transparent; color: inherit; }
.dark .sp-btn { border-color: #3f3f46; }
.sp-tabs, .sp-seg { display: flex; overflow: hidden; border: 1px solid #d4d4d8; border-radius: 10px; }
.dark .sp-tabs, .dark .sp-seg { border-color: #3f3f46; }
.sp-tabs button, .sp-seg button { flex: 1; cursor: pointer; padding: 9px 10px; border: 0; background: transparent; color: inherit; font-weight: 500; }
.sp-tabs button.on, .sp-seg button.on { background: #111; color: #fff; }
.dark .sp-tabs button.on, .dark .sp-seg button.on { background: #fafafa; color: #111; }
.sp-input { width: 100%; padding: 11px 12px; border: 1px solid #d4d4d8; border-radius: 10px; background: transparent; color: inherit; font-size: 15px; outline: none; }
.dark .sp-input { border-color: #3f3f46; background: #171717; color-scheme: dark; }
.sp-primary { cursor: pointer; padding: 13px; border: 0; border-radius: 12px; background: #111; color: #fff; font-size: 15px; font-weight: 600; }
.dark .sp-primary { background: #fafafa; color: #111; }
.sp-primary.sp-danger, .dark .sp-primary.sp-danger { background: #dc2626; color: #fff; }
.sp-primary:disabled { opacity: 0.4; cursor: not-allowed; }
.sp-error { margin: 0; padding: 10px 12px; border-radius: 10px; background: rgba(220, 38, 38, 0.1); color: #dc2626; }
</style>