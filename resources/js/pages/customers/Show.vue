<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Pelanggan', href: '/customers' }, { title: 'Riwayat', href: '#' }] },
});

defineProps<{
    customer: { id: string; name: string; phone: string | null; address: string | null };
    stats: { count: number; total: number; last: string | null };
    orders: { id: string; number: string; date: string; total: number; status: string }[];
}>();

const rupiah = (n: number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n || 0);
</script>

<template>
    <Head :title="customer.name" />

    <div class="mx-auto w-full max-w-3xl space-y-5 p-4 md:p-6">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">{{ customer.name }}</h1>
                <p class="text-sm text-neutral-500">{{ [customer.phone, customer.address].filter(Boolean).join(' · ') || 'Tanpa kontak' }}</p>
            </div>
            <Link href="/customers" class="rounded-lg border px-4 py-2 text-sm dark:border-neutral-700">← Kembali</Link>
        </div>

        <div class="grid grid-cols-3 gap-3">
            <div class="rounded-xl border p-4 dark:border-neutral-800">
                <p class="text-xs text-neutral-500">Transaksi</p>
                <p class="text-lg font-bold">{{ stats.count }}</p>
            </div>
            <div class="rounded-xl border p-4 dark:border-neutral-800">
                <p class="text-xs text-neutral-500">Total belanja</p>
                <p class="text-lg font-bold">{{ rupiah(stats.total) }}</p>
            </div>
            <div class="rounded-xl border p-4 dark:border-neutral-800">
                <p class="text-xs text-neutral-500">Terakhir belanja</p>
                <p class="text-lg font-bold">{{ stats.last ?? '-' }}</p>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border dark:border-neutral-800">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-neutral-50 text-xs uppercase text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900">
                    <tr>
                        <th class="px-4 py-3">Struk</th>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-neutral-800">
                    <tr v-for="o in orders" :key="o.id">
                        <td class="px-4 py-3 font-medium">{{ o.number }}</td>
                        <td class="px-4 py-3">{{ o.date }}</td>
                        <td class="px-4 py-3 text-right" :class="o.status === 'void' ? 'text-neutral-400 line-through' : 'font-medium'">{{ rupiah(o.total) }}</td>
                        <td class="px-4 py-3">
                            <span :class="o.status === 'void' ? 'text-red-500' : 'text-green-600'">{{ o.status === 'void' ? 'Dibatalkan' : 'Selesai' }}</span>
                        </td>
                    </tr>
                    <tr v-if="!orders.length">
                        <td colspan="4" class="px-4 py-10 text-center text-neutral-500">Belum ada transaksi.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="text-xs text-neutral-500">Menampilkan maksimal 30 transaksi terakhir.</p>
    </div>
</template>