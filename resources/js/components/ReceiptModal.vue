<script setup lang="ts">
withDefaults(defineProps<{ receipt: any; copy?: boolean; closeLabel?: string }>(), { closeLabel: 'Tutup' });
defineEmits<{ close: [] }>();

const rupiah = (n: number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n || 0);
const methodLabel: Record<string, string> = { cash: 'Tunai', qris: 'QRIS', debit: 'Debit', transfer: 'Transfer' };
const print = () => window.print();
</script>

<template>
    <Teleport to="body">
        <div class="rm-overlay" @click.self="$emit('close')">
            <div class="rm-wrap">
                <div id="receipt" class="rm-receipt">
                    <div v-if="receipt.order.status === 'void'" class="rm-center rm-bold">*** DIBATALKAN ***</div>
                    <div v-if="copy" class="rm-center">*** SALINAN ***</div>

                    <div class="rm-center">
                        <img v-if="receipt.logo_url" :src="receipt.logo_url" alt="" class="rm-logo" />
                        <div class="rm-bold">{{ receipt.business }}</div>
                        <div>{{ receipt.outlet.name }}</div>
                        <div v-if="receipt.outlet.address" class="rm-pre">{{ receipt.outlet.address }}</div>
                        <div v-if="receipt.outlet.phone">{{ receipt.outlet.phone }}</div>
                        <div v-if="receipt.outlet.header" class="rm-pre">{{ receipt.outlet.header }}</div>
                    </div>
                    <hr class="rm-hr" />
                    <div>{{ receipt.order.number }}</div>
                    <div>{{ receipt.order.date }} · {{ receipt.cashier }}</div>
                    <hr class="rm-hr" />
                    <div v-for="(it, i) in receipt.order.items" :key="i" class="rm-item">
                        <div>{{ it.name }}</div>
                        <div class="rm-row">
                            <span>{{ it.qty }} {{ it.unit || '' }} x {{ rupiah(it.price) }}</span>
                            <span>{{ rupiah(it.subtotal) }}</span>
                        </div>
                    </div>
                    <hr class="rm-hr" />
                    <div class="rm-row"><span>Subtotal</span><span>{{ rupiah(receipt.order.subtotal) }}</span></div>
                    <div v-if="receipt.order.discount" class="rm-row"><span>Diskon</span><span>-{{ rupiah(receipt.order.discount) }}</span></div>
                    <div class="rm-row rm-bold"><span>TOTAL</span><span>{{ rupiah(receipt.order.total) }}</span></div>
                    <div v-for="(p, i) in receipt.order.payments" :key="i">
                        <div class="rm-row"><span>{{ methodLabel[p.method] ?? p.method }}</span><span>{{ rupiah(p.amount) }}</span></div>
                        <div v-if="p.ref">{{ p.ref }}</div>
                    </div>
                    <div class="rm-row"><span>Kembali</span><span>{{ rupiah(receipt.order.change) }}</span></div>
                    <hr class="rm-hr" />
                    <div class="rm-center rm-pre">{{ receipt.outlet.footer || 'Terima kasih atas kunjungan Anda' }}</div>
                </div>

                <div class="rm-actions">
                    <button type="button" class="rm-white" @click="print">Cetak Struk</button>
                    <slot />
                    <button type="button" class="rm-ghost" @click="$emit('close')">{{ closeLabel }}</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style>
.rm-overlay { position: fixed; inset: 0; z-index: 70; display: flex; align-items: flex-start; justify-content: center; padding: 16px; background: rgba(0, 0, 0, 0.65); overflow-y: auto; }
.rm-wrap { display: flex; flex-direction: column; gap: 14px; margin: auto; }
.rm-receipt { width: 302px; margin: 0 auto; padding: 12px; background: #fff; color: #000; font-family: ui-monospace, 'Courier New', monospace; font-size: 12px; line-height: 1.35; }
.rm-center { text-align: center; }
.rm-pre { white-space: pre-line; }
.rm-bold { font-weight: 700; }
.rm-logo { display: block; margin: 0 auto 6px; max-width: 120px; max-height: 70px; object-fit: contain; filter: grayscale(1) contrast(1.3); }
.rm-hr { border: 0; border-top: 1px dashed #000; margin: 8px 0; }
.rm-row { display: flex; justify-content: space-between; gap: 8px; }
.rm-item { margin-bottom: 4px; }
.rm-actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; }
.rm-actions button { cursor: pointer; padding: 11px 20px; border-radius: 12px; font-size: 14px; font-weight: 600; }
.rm-white { border: 0; background: #fff; color: #000; }
.rm-ghost { border: 1px solid rgba(255, 255, 255, 0.4); background: transparent; color: #fff; }
.rm-danger { border: 0; background: #dc2626; color: #fff; }

@media print {
    body * { visibility: hidden !important; }
    #receipt, #receipt * { visibility: visible !important; }
    .rm-overlay { position: static !important; overflow: visible !important; background: none !important; padding: 0 !important; }
    #receipt { position: absolute; left: 0; top: 0; width: 80mm; padding: 2mm; }
    @page { margin: 0; }
}
</style>