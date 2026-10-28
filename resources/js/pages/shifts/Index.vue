<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    shifts: { data: any[]; links: { url: string | null; label: string; active: boolean }[] };
}>();

const rupiah = (n: number | null) =>
    n === null || n === undefined ? '-' : new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n);
</script>

<template>
    <Head title="Riwayat Shift" />

    <div class="mx-auto w-full max-w-5xl space-y-5 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold">Riwayat Shift</h1>
            <p class="text-sm text-neutral-500">Catatan buka dan tutup kasir beserta selisih uang.</p>
        </div>

        <div class="overflow-x-auto rounded-xl border dark:border-neutral-800">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-neutral-50 text-xs uppercase text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900">
                    <tr>
                        <th class="px-4 py-3">Kasir</th>
                        <th class="px-4 py-3">Buka</th>
                        <th class="px-4 py-3">Tutup</th>
                        <th class="px-4 py-3 text-right">Modal awal</th>
                        <th class="px-4 py-3 text-right">Selisih</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-neutral-800">
                    <tr v-for="s in shifts.data" :key="s.id">
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ s.cashier }}</p>
                            <p class="text-xs text-neutral-500">{{ s.outlet }}</p>
                        </td>
                        <td class="px-4 py-3">{{ s.opened_at }}</td>
                        <td class="px-4 py-3">{{ s.closed_at ?? '-' }}</td>
                        <td class="px-4 py-3 text-right">{{ rupiah(s.opening_cash) }}</td>
                        <td class="px-4 py-3 text-right" :class="s.difference ? 'text-red-500' : ''">
                            {{ s.status === 'closed' ? (s.difference === 0 ? 'Pas' : rupiah(s.difference)) : '-' }}
                        </td>
                        <td class="px-4 py-3">
                            <span :class="s.status === 'open' ? 'text-green-600' : 'text-neutral-500'">{{ s.status === 'open' ? 'Berjalan' : 'Ditutup' }}</span>
                        </td>
                        <td class="px-4 py-3 text-right"><Link :href="`/shifts/${s.id}`" class="font-medium">Lihat</Link></td>
                    </tr>
                    <tr v-if="!shifts.data.length">
                        <td colspan="7" class="px-4 py-10 text-center text-neutral-500">Belum ada shift.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="shifts.links.length > 3" class="flex flex-wrap gap-1">
            <template v-for="(l, i) in shifts.links" :key="i">
                <Link v-if="l.url" :href="l.url" class="rounded-md border px-3 py-1.5 text-sm dark:border-neutral-700"
                    :class="l.active ? 'bg-black text-white dark:bg-white dark:text-black' : ''" v-html="l.label" />
                <span v-else class="px-3 py-1.5 text-sm text-neutral-400" v-html="l.label" />
            </template>
        </div>
    </div>
</template>