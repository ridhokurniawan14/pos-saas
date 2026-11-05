<script setup lang="ts">
import MoneyInput from '@/components/MoneyInput.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Pelanggan', href: '/customers' }, { title: 'Riwayat', href: '#' }] },
});

const props = defineProps<{
    customer: { id: string; name: string; phone: string | null; address: string | null };
    can: { receivable: boolean; loyalty: boolean };
    stats: { count: number; total: number; last: string | null; debt: number; points: number };
    unpaid: { id: string; number: string; date: string; total: number; remaining: number }[];
    payments: { batch_id: string; method: string; total: number; at: string; note: string | null }[];
    point_moves: { type: string; points: number; balance_after: number; note: string | null; at: string }[];
    orders: { id: string; number: string; date: string; total: number; due: number; status: string }[];
}>();

const rupiah = (n: number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n || 0);
const methodLabel: Record<string, string> = { cash: 'Tunai', qris: 'QRIS', debit: 'Debit', transfer: 'Transfer' };
const pointLabel: Record<string, string> = { earn: 'Belanja', redeem: 'Tukar poin', reverse: 'Pembatalan', adjust: 'Penyesuaian' };

const amount = ref<number | null>(null);
const method = ref('cash');
const note = ref('');
const error = ref('');
const saving = ref(false);

const pay = () => {
    error.value = '';
    saving.value = true;
    router.post(
        `/customers/${props.customer.id}/payments`,
        { amount: amount.value ?? 0, method: method.value, note: note.value || null },
        {
            preserveScroll: true,
            onSuccess: () => {
                amount.value = null;
                note.value = '';
            },
            onError: (e) => (error.value = Object.values(e)[0] as string),
            onFinish: () => (saving.value = false),
        },
    );
};

const field =
    'w-full rounded-lg border bg-transparent px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-neutral-400 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white dark:[color-scheme:dark]';
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

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border p-4 dark:border-neutral-800">
                <p class="text-xs text-neutral-500">Transaksi</p>
                <p class="text-lg font-bold">{{ stats.count }}</p>
            </div>
            <div class="rounded-xl border p-4 dark:border-neutral-800">
                <p class="text-xs text-neutral-500">Total belanja</p>
                <p class="text-lg font-bold">{{ rupiah(stats.total) }}</p>
            </div>
            <div v-if="can.receivable" class="rounded-xl border p-4 dark:border-neutral-800">
                <p class="text-xs text-neutral-500">Piutang</p>
                <p class="text-lg font-bold" :class="stats.debt > 0 ? 'text-red-500' : ''">{{ rupiah(stats.debt) }}</p>
            </div>
            <div v-if="can.loyalty" class="rounded-xl border p-4 dark:border-neutral-800">
                <p class="text-xs text-neutral-500">Poin</p>
                <p class="text-lg font-bold">{{ stats.points }}</p>
            </div>
            <div v-if="!can.receivable && !can.loyalty" class="rounded-xl border p-4 dark:border-neutral-800">
                <p class="text-xs text-neutral-500">Terakhir belanja</p>
                <p class="text-lg font-bold">{{ stats.last ?? '-' }}</p>
            </div>
        </div>

        <!-- Terima pembayaran piutang -->
        <div v-if="can.receivable && stats.debt > 0" class="space-y-3 rounded-xl border p-4 dark:border-neutral-800">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-semibold">Terima pembayaran bon</h2>
                <button type="button" class="text-sm underline" @click="amount = stats.debt">Lunasi semua ({{ rupiah(stats.debt) }})</button>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <MoneyInput v-model="amount" placeholder="Jumlah dibayar (Rp)" :class="field" />
                <select v-model="method" :class="field">
                    <option v-for="(label, key) in methodLabel" :key="key" :value="key">{{ label }}</option>
                </select>
            </div>
            <input v-model="note" maxlength="100" placeholder="Catatan (opsional)" :class="field" />
            <p v-if="error" class="rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-600">{{ error }}</p>
            <div class="flex justify-end">
                <button type="button" :disabled="saving || !amount" class="rounded-lg bg-black px-5 py-2.5 text-sm font-medium text-white disabled:opacity-40 dark:bg-white dark:text-black" @click="pay">
                    {{ saving ? 'Menyimpan...' : 'Terima Pembayaran' }}
                </button>
            </div>
            <p class="text-xs text-neutral-500">Pembayaran dialokasikan ke struk bon paling lama lebih dulu. Pembayaran tunai masuk ke shift kasir yang sedang terbuka.</p>
        </div>

        <div v-if="can.receivable && unpaid.length" class="space-y-2">
            <h2 class="font-semibold">Struk bon yang belum lunas</h2>
            <div class="divide-y rounded-xl border dark:divide-neutral-800 dark:border-neutral-800">
                <div v-for="u in unpaid" :key="u.id" class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                    <div>
                        <p class="font-medium">{{ u.number }}</p>
                        <p class="text-xs text-neutral-500">{{ u.date }} · total {{ rupiah(u.total) }}</p>
                    </div>
                    <p class="font-semibold text-red-500">sisa {{ rupiah(u.remaining) }}</p>
                </div>
            </div>
        </div>

        <div v-if="can.receivable && payments.length" class="space-y-2">
            <h2 class="font-semibold">Riwayat pembayaran bon</h2>
            <div class="divide-y rounded-xl border dark:divide-neutral-800 dark:border-neutral-800">
                <div v-for="p in payments" :key="p.batch_id" class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                    <div>
                        <p class="font-medium">{{ methodLabel[p.method] ?? p.method }}</p>
                        <p class="text-xs text-neutral-500">{{ p.at }}<span v-if="p.note"> · {{ p.note }}</span></p>
                    </div>
                    <p class="font-semibold text-green-600">+{{ rupiah(p.total) }}</p>
                </div>
            </div>
        </div>

        <div v-if="can.loyalty && point_moves.length" class="space-y-2">
            <h2 class="font-semibold">Riwayat poin</h2>
            <div class="divide-y rounded-xl border dark:divide-neutral-800 dark:border-neutral-800">
                <div v-for="(m, i) in point_moves" :key="i" class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                    <div>
                        <p class="font-medium">{{ pointLabel[m.type] ?? m.type }}</p>
                        <p class="text-xs text-neutral-500">{{ m.at }}<span v-if="m.note"> · {{ m.note }}</span></p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold" :class="m.points >= 0 ? 'text-green-600' : 'text-red-500'">{{ m.points >= 0 ? '+' : '' }}{{ m.points }}</p>
                        <p class="text-xs text-neutral-500">saldo {{ m.balance_after }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-2">
            <h2 class="font-semibold">Riwayat transaksi</h2>
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
                                <p v-if="o.due > 0 && o.status !== 'void'" class="text-xs text-red-500">Bon {{ rupiah(o.due) }}</p>
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
    </div>
</template>