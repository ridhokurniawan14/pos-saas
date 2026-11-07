<script setup lang="ts">
import BarChart from '@/components/BarChart.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Lock } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Laporan', href: '/reports' }] } });

const printPage = () => window.print();

const props = defineProps<{
    report: any;
    filters: { from: string; to: string; outlet: string | null };
    outlets: { id: string; name: string }[];
    advanced: boolean;
    capped: boolean;
}>();

const page = usePage<any>();
const rupiah = (n: number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n || 0);
const nf = (n: number) => new Intl.NumberFormat('id-ID').format(n || 0);
const methodLabel: Record<string, string> = { cash: 'Tunai', qris: 'QRIS', debit: 'Debit', transfer: 'Transfer' };

const from = ref(props.filters.from);
const to = ref(props.filters.to);
const outlet = ref(props.filters.outlet ?? '');

let timer: number | undefined;
watch([from, to, outlet], () => {
    clearTimeout(timer);
    timer = window.setTimeout(
        () =>
            router.get(
                '/reports',
                { from: from.value, to: to.value, outlet: outlet.value || undefined },
                { preserveState: true, preserveScroll: true, replace: true },
            ),
        300,
    );
});
onBeforeUnmount(() => clearTimeout(timer));

watch(
    () => props.filters,
    (f) => {
        if (props.capped) {
            from.value = f.from;
            to.value = f.to;
        }
    },
);

const iso = (d: Date) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
const preset = (p: 'today' | '7' | 'month' | 'last') => {
    const now = new Date();
    if (p === 'today') [from.value, to.value] = [iso(now), iso(now)];
    if (p === '7') [from.value, to.value] = [iso(new Date(now.getTime() - 6 * 86400000)), iso(now)];
    if (p === 'month') [from.value, to.value] = [iso(new Date(now.getFullYear(), now.getMonth(), 1)), iso(now)];
    if (p === 'last') [from.value, to.value] = [iso(new Date(now.getFullYear(), now.getMonth() - 1, 1)), iso(new Date(now.getFullYear(), now.getMonth(), 0))];
};

const exportUrl = computed(() => {
    const q = new URLSearchParams({ from: props.filters.from, to: props.filters.to });
    if (props.filters.outlet) q.set('outlet', props.filters.outlet);
    return `/reports/export?${q.toString()}`;
});

const s = computed(() => props.report.summary);
const chart = computed(() => props.report.days.map((d: any) => ({ label: d.label, value: d.revenue })));
const fmtDate = (d: string) => d.split('-').reverse().join('/');

const field =
    'rounded-lg border bg-transparent px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-neutral-400 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white dark:[color-scheme:dark]';
const chip = 'rounded-full border px-3 py-1 text-sm hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800';
</script>

<template>
    <Head title="Laporan" />

    <div class="mx-auto w-full max-w-5xl space-y-5 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Laporan</h1>
                <p class="text-sm text-neutral-500">Penjualan, laba, dan produk terlaris.</p>
            </div>
            <div class="flex gap-2">
                <button type="button" class="rounded-lg border px-4 py-2.5 text-sm font-medium hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800" @click="printPage">
                    Cetak / Simpan PDF
                </button>
                <a v-if="advanced" :href="exportUrl" class="rounded-lg bg-black px-4 py-2.5 text-sm font-medium text-white dark:bg-white dark:text-black">Unduh Excel</a>
                <span v-else class="flex items-center gap-1.5 rounded-lg border border-dashed px-4 py-2.5 text-sm text-neutral-500 dark:border-neutral-700">
                    <Lock :size="14" /> Excel (Tumbuh)
                </span>
            </div>
        </div>

        <div class="flex flex-wrap items-end gap-3">
            <div><label class="mb-1 block text-xs text-neutral-500">Dari</label><input v-model="from" type="date" :class="field" /></div>
            <div><label class="mb-1 block text-xs text-neutral-500">Sampai</label><input v-model="to" type="date" :class="field" /></div>
            <div v-if="outlets.length > 1">
                <label class="mb-1 block text-xs text-neutral-500">Cabang</label>
                <select v-model="outlet" :class="field">
                    <option value="">Semua cabang</option>
                    <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
                </select>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <button :class="chip" @click="preset('today')">Hari ini</button>
            <button :class="chip" @click="preset('7')">7 hari</button>
            <button :class="chip" @click="preset('month')">Bulan ini</button>
            <button :class="chip" @click="preset('last')">Bulan lalu</button>
        </div>

        <p v-if="capped" class="rounded-lg bg-amber-500/10 px-3 py-2 text-sm text-amber-700 dark:text-amber-400 no-print">
            Rentang laporan maksimal 1 tahun (366 hari). Tanggal awal disesuaikan menjadi {{ fmtDate(filters.from) }}.
        </p>

        <div id="report-print" class="space-y-5">
            <div class="print-only">
                <p class="text-lg font-bold">Laporan Penjualan: {{ page.props.business?.name }}</p>
                <p class="text-sm">Periode {{ fmtDate(filters.from) }} sampai {{ fmtDate(filters.to) }}</p>
            </div>

            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <div class="rounded-xl border p-4 dark:border-neutral-800">
                    <p class="text-xs text-neutral-500">Omzet</p>
                    <p class="text-lg font-bold">{{ rupiah(s.revenue) }}</p>
                </div>
                <div class="rounded-xl border p-4 dark:border-neutral-800">
                    <p class="text-xs text-neutral-500">Transaksi</p>
                    <p class="text-lg font-bold">{{ nf(s.orders) }}</p>
                </div>
                <div class="rounded-xl border p-4 dark:border-neutral-800">
                    <p class="text-xs text-neutral-500">Rata-rata per transaksi</p>
                    <p class="text-lg font-bold">{{ rupiah(s.avg) }}</p>
                </div>
                <div class="rounded-xl border p-4 dark:border-neutral-800">
                    <p class="text-xs text-neutral-500">Diskon dan poin</p>
                    <p class="text-lg font-bold">{{ rupiah(s.discount) }}</p>
                </div>
            </div>

            <div v-if="advanced" class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <div class="rounded-xl border p-4 dark:border-neutral-800">
                    <p class="text-xs text-neutral-500">HPP (modal terjual)</p>
                    <p class="text-lg font-bold">{{ rupiah(s.cogs) }}</p>
                </div>
                <div class="rounded-xl border p-4 dark:border-neutral-800">
                    <p class="text-xs text-neutral-500">Laba kotor</p>
                    <p class="text-lg font-bold">{{ rupiah(s.gross_profit) }}</p>
                    <p class="text-xs text-neutral-500">Margin {{ s.margin }}%</p>
                </div>
                <div class="rounded-xl border p-4 dark:border-neutral-800">
                    <p class="text-xs text-neutral-500">Pengeluaran</p>
                    <p class="text-lg font-bold">{{ rupiah(s.expenses) }}</p>
                </div>
                <div class="rounded-xl border p-4 dark:border-neutral-800">
                    <p class="text-xs text-neutral-500">Laba bersih</p>
                    <p class="text-lg font-bold" :class="s.net_profit < 0 ? 'text-red-500' : 'text-green-600'">{{ rupiah(s.net_profit) }}</p>
                </div>
            </div>
            <div v-else class="flex items-center gap-3 rounded-xl border border-dashed p-4 text-sm dark:border-neutral-700 no-print">
                <Lock :size="18" class="shrink-0 text-amber-600" />
                <p class="text-neutral-500">HPP, laba kotor, laba bersih, produk terlaris, dan ekspor Excel tersedia mulai paket <strong>Tumbuh</strong>.</p>
            </div>

            <div v-if="s.due > 0" class="rounded-xl border p-4 text-sm dark:border-neutral-800">
                Dari omzet periode ini, <strong>{{ rupiah(s.due) }}</strong> masih berupa bon (belum dibayar saat transaksi).
            </div>

            <div v-if="report.days.length > 1" class="rounded-xl border p-4 dark:border-neutral-800">
                <p class="mb-3 text-sm font-semibold">Omzet per hari</p>
                <BarChart :items="chart" :format="rupiah" />
            </div>

            <div class="space-y-2">
                <p class="text-sm font-semibold">Rincian per hari</p>
                <div class="overflow-x-auto rounded-xl border dark:border-neutral-800">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-neutral-50 text-xs uppercase text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900">
                            <tr>
                                <th class="px-4 py-3">Tanggal</th>
                                <th class="px-4 py-3 text-right">Transaksi</th>
                                <th class="px-4 py-3 text-right">Omzet</th>
                                <template v-if="advanced">
                                    <th class="px-4 py-3 text-right">HPP</th>
                                    <th class="px-4 py-3 text-right">Laba kotor</th>
                                    <th class="px-4 py-3 text-right">Pengeluaran</th>
                                    <th class="px-4 py-3 text-right">Laba bersih</th>
                                </template>
                            </tr>
                        </thead>
                        <tbody class="divide-y dark:divide-neutral-800">
                            <tr v-for="d in report.days" :key="d.date">
                                <td class="px-4 py-2.5">{{ fmtDate(d.date) }}</td>
                                <td class="px-4 py-2.5 text-right">{{ d.orders }}</td>
                                <td class="px-4 py-2.5 text-right font-medium">{{ rupiah(d.revenue) }}</td>
                                <template v-if="advanced">
                                    <td class="px-4 py-2.5 text-right">{{ rupiah(d.cogs) }}</td>
                                    <td class="px-4 py-2.5 text-right">{{ rupiah(d.gross_profit) }}</td>
                                    <td class="px-4 py-2.5 text-right">{{ rupiah(d.expenses) }}</td>
                                    <td class="px-4 py-2.5 text-right font-medium" :class="d.net_profit < 0 ? 'text-red-500' : ''">{{ rupiah(d.net_profit) }}</td>
                                </template>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                    <p class="text-sm font-semibold">Metode pembayaran</p>
                    <div class="divide-y rounded-xl border dark:divide-neutral-800 dark:border-neutral-800">
                        <div v-for="m in report.methods" :key="m.method" class="flex justify-between px-4 py-2.5 text-sm">
                            <span>{{ methodLabel[m.method] ?? m.method }}</span><span class="font-medium">{{ rupiah(m.amount) }}</span>
                        </div>
                        <p v-if="!report.methods.length" class="px-4 py-6 text-center text-sm text-neutral-500">Belum ada pembayaran.</p>
                    </div>
                    <p class="text-xs text-neutral-500">Tunai sudah dikurangi uang kembalian. Transaksi bon tidak masuk sampai dibayar.</p>
                </div>

                <div v-if="advanced" class="space-y-2">
                    <p class="text-sm font-semibold">Pengeluaran per kategori</p>
                    <div class="divide-y rounded-xl border dark:divide-neutral-800 dark:border-neutral-800">
                        <div v-for="e in report.expense_by_category" :key="e.name" class="flex justify-between px-4 py-2.5 text-sm">
                            <span>{{ e.name }}</span><span class="font-medium">{{ rupiah(e.amount) }}</span>
                        </div>
                        <p v-if="!report.expense_by_category.length" class="px-4 py-6 text-center text-sm text-neutral-500">Belum ada pengeluaran tercatat.</p>
                    </div>
                </div>
            </div>

            <div v-if="advanced" class="space-y-2">
                <p class="text-sm font-semibold">Produk terlaris (10 teratas berdasarkan pendapatan)</p>
                <div class="overflow-x-auto rounded-xl border dark:border-neutral-800">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-neutral-50 text-xs uppercase text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900">
                            <tr>
                                <th class="px-4 py-3">Produk</th>
                                <th class="px-4 py-3 text-right">Terjual</th>
                                <th class="px-4 py-3 text-right">Pendapatan</th>
                                <th class="px-4 py-3 text-right">Laba kotor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y dark:divide-neutral-800">
                            <tr v-for="(p, i) in report.top" :key="i">
                                <td class="px-4 py-2.5 font-medium">{{ p.name }}</td>
                                <td class="px-4 py-2.5 text-right">{{ nf(p.qty) }} {{ p.unit }}</td>
                                <td class="px-4 py-2.5 text-right">{{ rupiah(p.revenue) }}</td>
                                <td class="px-4 py-2.5 text-right">{{ rupiah(p.profit) }}</td>
                            </tr>
                            <tr v-if="!report.top.length">
                                <td colspan="4" class="px-4 py-8 text-center text-neutral-500">Belum ada penjualan pada periode ini.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-neutral-500">Laba per produk dihitung sebelum diskon tingkat transaksi, jadi totalnya bisa sedikit berbeda dari laba kotor di atas.</p>
            </div>
        </div>
    </div>
</template>

<style>
.print-only { display: none; }

@media print {
    body * { visibility: hidden !important; }
    #report-print, #report-print * { visibility: visible !important; color: #000 !important; background: #fff !important; border-color: #999 !important; }
    #report-print .bc-bar { background: #444 !important; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
    #report-print { position: absolute; left: 0; top: 0; width: 100%; padding: 8mm; }
    #report-print .print-only { display: block !important; }
    #report-print .no-print { display: none !important; }
    @page { margin: 8mm; }
}
</style>