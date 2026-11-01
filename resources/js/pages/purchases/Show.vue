<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Pembelian', href: '/purchases' }, { title: 'Detail', href: '#' }] },
});

defineProps<{ purchase: any }>();

const rupiah = (n: number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n || 0);
</script>

<template>
    <Head :title="purchase.number" />

    <div class="mx-auto w-full max-w-2xl space-y-5 p-4 md:p-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">{{ purchase.number }}</h1>
                <p class="text-sm text-neutral-500">{{ purchase.date }} · {{ purchase.supplier ?? 'Tanpa supplier' }} · {{ purchase.outlet }}</p>
            </div>
            <Link href="/purchases" class="rounded-lg border px-4 py-2 text-sm dark:border-neutral-700">← Kembali</Link>
        </div>

        <div class="divide-y rounded-xl border dark:divide-neutral-800 dark:border-neutral-800">
            <div v-for="(i, k) in purchase.items" :key="k" class="flex items-start justify-between gap-3 px-4 py-3 text-sm">
                <div>
                    <p class="font-medium">{{ i.name }}</p>
                    <p class="text-xs text-neutral-500">
                        {{ i.qty }} {{ i.unit || 'satuan dasar' }} × {{ rupiah(i.cost) }}
                        <span v-if="i.unit"> (stok +{{ i.qty * i.conversion }})</span>
                    </p>
                </div>
                <p class="font-semibold">{{ rupiah(i.subtotal) }}</p>
            </div>
            <div class="flex justify-between px-4 py-3 font-bold"><span>Total</span><span>{{ rupiah(purchase.total) }}</span></div>
        </div>

        <p v-if="purchase.note" class="text-sm text-neutral-500">Catatan: {{ purchase.note }}</p>
        <p class="text-xs text-neutral-500">Dicatat oleh {{ purchase.creator ?? '-' }}</p>
    </div>
</template>