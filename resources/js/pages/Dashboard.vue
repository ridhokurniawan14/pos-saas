<script setup lang="ts">
import BarChart from '@/components/BarChart.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Lock } from '@lucide/vue';
import { computed } from 'vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }] } });

const props = defineProps<{
    today: any;
    yesterday_revenue: number;
    week: { label: string; revenue: number; orders: number }[];
    recent: { id: string; number: string; time: string; total: number; status: string; cashier: string | null }[];
    receivable: number | null;
    manager: boolean;
    advanced: boolean;
}>();

const page = usePage<any>();
const lowCount = computed<number | null>(() => page.props.low_stock?.count ?? null);

const rupiah = (n: number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n || 0);

const delta = computed(() =>
    props.yesterday_revenue > 0
        ? Math.round(((props.today.revenue - props.yesterday_revenue) / props.yesterday_revenue) * 100)
        : null,
);
</script>

<template>
    <Head title="Dashboard" />

    <div class="mx-auto w-full max-w-6xl space-y-5 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Dashboard</h1>
                <p class="text-sm text-neutral-500">Ringkasan penjualan hari ini.</p>
            </div>
            <Link href="/pos" class="rounded-lg bg-black px-5 py-2.5 text-sm font-medium text-white dark:bg-white dark:text-black">Buka Kasir</Link>
        </div>

        <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
            <div class="rounded-xl border p-4 dark:border-neutral-800">
                <p class="text-xs text-neutral-500">Omzet hari ini</p>
                <p class="text-xl font-bold">{{ rupiah(today.revenue) }}</p>
                <p v-if="delta !== null" class="text-xs" :class="delta >= 0 ? 'text-green-600' : 'text-red-500'">
                    {{ delta >= 0 ? 'Naik' : 'Turun' }} {{ Math.abs(delta) }}% dari kemarin
                </p>
                <p v-else class="text-xs text-neutral-500">Belum ada pembanding kemarin</p>
            </div>
            <div class="rounded-xl border p-4 dark:border-neutral-800">
                <p class="text-xs text-neutral-500">Transaksi</p>
                <p class="text-xl font-bold">{{ today.orders }}</p>
            </div>
            <div class="rounded-xl border p-4 dark:border-neutral-800">
                <p class="text-xs text-neutral-500">Rata-rata per transaksi</p>
                <p class="text-xl font-bold">{{ rupiah(today.avg) }}</p>
            </div>
            <div v-if="advanced" class="rounded-xl border p-4 dark:border-neutral-800">
                <p class="text-xs text-neutral-500">Laba kotor hari ini</p>
                <p class="text-xl font-bold">{{ rupiah(today.gross_profit) }}</p>
                <p class="text-xs text-neutral-500">Margin {{ today.margin }}%</p>
            </div>
            <div v-else-if="manager" class="rounded-xl border border-dashed p-4 dark:border-neutral-700">
                <p class="flex items-center gap-1.5 text-xs text-neutral-500"><Lock :size="12" /> Laba kotor</p>
                <p class="text-sm font-medium">Tersedia di paket Tumbuh</p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-xl border p-4 dark:border-neutral-800 lg:col-span-2">
                <p class="mb-3 text-sm font-semibold">Omzet 7 hari terakhir</p>
                <BarChart :items="week.map((d) => ({ label: d.label, value: d.revenue }))" :format="rupiah" />
            </div>

            <div class="space-y-3">
                <Link v-if="receivable !== null" href="/customers" class="block rounded-xl border p-4 hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-neutral-900">
                    <p class="text-xs text-neutral-500">Piutang pelanggan (bon)</p>
                    <p class="text-xl font-bold" :class="receivable > 0 ? 'text-red-500' : ''">{{ rupiah(receivable) }}</p>
                </Link>
                <Link v-if="lowCount !== null" href="/stock" class="block rounded-xl border p-4 hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-neutral-900">
                    <p class="text-xs text-neutral-500">Stok menipis</p>
                    <p class="text-xl font-bold" :class="lowCount > 0 ? 'text-amber-600' : ''">{{ lowCount }} produk</p>
                </Link>
                <Link v-if="manager" href="/reports" class="block rounded-xl border p-4 hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-neutral-900">
                    <p class="text-sm font-semibold">Lihat laporan lengkap</p>
                    <p class="text-xs text-neutral-500">Harian, mingguan, bulanan.</p>
                </Link>
            </div>
        </div>

        <div class="rounded-xl border dark:border-neutral-800">
            <p class="border-b px-4 py-3 text-sm font-semibold dark:border-neutral-800">Transaksi terakhir</p>
            <div class="divide-y dark:divide-neutral-800">
                <div v-for="o in recent" :key="o.id" class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                    <div>
                        <p class="font-medium">{{ o.number }}</p>
                        <p class="text-xs text-neutral-500">{{ o.time }}<span v-if="o.cashier"> · {{ o.cashier }}</span></p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold" :class="o.status === 'void' ? 'text-neutral-400 line-through' : ''">{{ rupiah(o.total) }}</p>
                        <p v-if="o.status === 'void'" class="text-xs text-red-500">Dibatalkan</p>
                    </div>
                </div>
                <p v-if="!recent.length" class="px-4 py-8 text-center text-sm text-neutral-500">Belum ada transaksi.</p>
            </div>
        </div>
    </div>
</template>