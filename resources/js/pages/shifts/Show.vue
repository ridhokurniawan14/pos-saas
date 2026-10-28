<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{ shift: any }>();

const rupiah = (n: number | null) =>
    n === null || n === undefined ? '-' : new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n);
const methodLabel: Record<string, string> = { cash: 'Tunai', qris: 'QRIS', debit: 'Debit', transfer: 'Transfer' };
</script>

<template>
    <Head title="Detail Shift" />

    <div class="mx-auto w-full max-w-2xl space-y-5 p-4 md:p-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Detail Shift</h1>
                <p class="text-sm text-neutral-500">{{ shift.cashier }} · {{ shift.outlet_name }}</p>
            </div>
            <Link href="/shifts" class="rounded-lg border px-4 py-2 text-sm dark:border-neutral-700">← Riwayat</Link>
        </div>

        <div class="space-y-1.5 rounded-xl border p-4 text-sm dark:border-neutral-800">
            <div class="flex justify-between"><span class="text-neutral-500">Buka</span><span>{{ shift.opened_at }}</span></div>
            <div class="flex justify-between"><span class="text-neutral-500">Tutup</span><span>{{ shift.closed_at ?? 'Masih berjalan' }}</span></div>
        </div>

        <div class="space-y-1.5 rounded-xl border p-4 text-sm dark:border-neutral-800">
            <h2 class="mb-1 font-semibold">Uang di laci</h2>
            <div class="flex justify-between"><span>Modal awal</span><span>{{ rupiah(shift.opening_cash) }}</span></div>
            <div class="flex justify-between"><span>Penjualan tunai</span><span>{{ rupiah(shift.cash_sales) }}</span></div>
            <div class="flex justify-between"><span>Kas masuk</span><span>+{{ rupiah(shift.cash_in) }}</span></div>
            <div class="flex justify-between"><span>Kas keluar</span><span>-{{ rupiah(shift.cash_out) }}</span></div>
            <div class="flex justify-between border-t border-dashed pt-2 font-bold"><span>Seharusnya</span><span>{{ rupiah(shift.expected_cash) }}</span></div>
            <template v-if="shift.status === 'closed'">
                <div class="flex justify-between"><span>Hitungan fisik</span><span>{{ rupiah(shift.closing_cash) }}</span></div>
                <div class="flex justify-between font-bold" :class="shift.difference === 0 ? 'text-green-600' : 'text-red-500'">
                    <span>Selisih</span>
                    <span>{{ shift.difference === 0 ? 'Pas' : rupiah(shift.difference) }}</span>
                </div>
                <p v-if="shift.note" class="pt-1 text-neutral-500">Catatan: {{ shift.note }}</p>
            </template>
        </div>

        <div class="space-y-1.5 rounded-xl border p-4 text-sm dark:border-neutral-800">
            <h2 class="mb-1 font-semibold">Penjualan</h2>
            <div class="flex justify-between"><span>Transaksi selesai</span><span>{{ shift.orders_count }}</span></div>
            <div class="flex justify-between"><span>Dibatalkan</span><span>{{ shift.voided_count }}</span></div>
            <div class="flex justify-between font-bold"><span>Total penjualan</span><span>{{ rupiah(shift.sales_total) }}</span></div>
            <div v-for="(v, k) in shift.methods" :key="k" class="flex justify-between text-neutral-500">
                <span>· {{ methodLabel[k as string] ?? k }}</span><span>{{ rupiah(v as number) }}</span>
            </div>
        </div>

        <div v-if="shift.movements.length" class="space-y-1.5 rounded-xl border p-4 text-sm dark:border-neutral-800">
            <h2 class="mb-1 font-semibold">Kas masuk / keluar</h2>
            <div v-for="m in shift.movements" :key="m.id" class="flex justify-between">
                <span>{{ m.time }} · {{ m.reason }}</span>
                <span :class="m.type === 'in' ? 'text-green-600' : 'text-red-500'">{{ m.type === 'in' ? '+' : '-' }}{{ rupiah(m.amount) }}</span>
            </div>
        </div>
    </div>
</template>