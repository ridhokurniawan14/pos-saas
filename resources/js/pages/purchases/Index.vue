<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineOptions({ layout: { breadcrumbs: [{ title: 'Pembelian', href: '/purchases' }] } });

defineProps<{
    purchases: { data: any[]; links: { url: string | null; label: string; active: boolean }[] };
}>();

const rupiah = (n: number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n || 0);
</script>

<template>
    <Head title="Pembelian" />

    <div class="mx-auto w-full max-w-5xl space-y-5 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Pembelian Barang</h1>
                <p class="text-sm text-neutral-500">Barang yang dibeli otomatis menambah stok.</p>
            </div>
            <div class="flex gap-2">
                <Link href="/suppliers" class="rounded-lg border px-4 py-2.5 text-sm font-medium dark:border-neutral-700">Supplier</Link>
                <Link href="/purchases/create" class="rounded-lg bg-black px-4 py-2.5 text-sm font-medium text-white dark:bg-white dark:text-black">+ Pembelian Baru</Link>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border dark:border-neutral-800">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-neutral-50 text-xs uppercase text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900">
                    <tr>
                        <th class="px-4 py-3">No.</th>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Supplier</th>
                        <th class="px-4 py-3">Item</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-neutral-800">
                    <tr v-for="p in purchases.data" :key="p.id">
                        <td class="px-4 py-3 font-medium">{{ p.number }}</td>
                        <td class="px-4 py-3">{{ p.date }}</td>
                        <td class="px-4 py-3">{{ p.supplier ?? '-' }}</td>
                        <td class="px-4 py-3">{{ p.items_count }}</td>
                        <td class="px-4 py-3 text-right font-medium">{{ rupiah(p.total) }}</td>
                        <td class="px-4 py-3 text-right"><Link :href="`/purchases/${p.id}`" class="font-medium">Lihat</Link></td>
                    </tr>
                    <tr v-if="!purchases.data.length">
                        <td colspan="6" class="px-4 py-10 text-center text-neutral-500">Belum ada pembelian.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="purchases.links.length > 3" class="flex flex-wrap gap-1">
            <template v-for="(l, i) in purchases.links" :key="i">
                <Link v-if="l.url" :href="l.url" class="rounded-md border px-3 py-1.5 text-sm dark:border-neutral-700"
                    :class="l.active ? 'bg-black text-white dark:bg-white dark:text-black' : ''" v-html="l.label" />
                <span v-else class="px-3 py-1.5 text-sm text-neutral-400" v-html="l.label" />
            </template>
        </div>
    </div>
</template>