<script setup lang="ts">
import ReceiptModal from '@/components/ReceiptModal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    orders: { data: any[]; links: { url: string | null; label: string; active: boolean }[] };
    filters: { from: string; to: string; status?: string | null; search?: string | null };
    summary: { count: number; total: number };
}>();

const rupiah = (n: number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n || 0);
const methodLabel: Record<string, string> = { cash: 'Tunai', qris: 'QRIS', debit: 'Debit', transfer: 'Transfer' };

const from = ref(props.filters.from);
const to = ref(props.filters.to);
const status = ref(props.filters.status ?? '');
const search = ref(props.filters.search ?? '');

const apply = () =>
    router.get('/orders', { from: from.value, to: to.value, status: status.value || undefined, search: search.value || undefined }, { preserveState: true, replace: true });

const iso = (d: Date) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
const preset = (days: number | 'month') => {
    const end = new Date();
    const start = days === 'month' ? new Date(end.getFullYear(), end.getMonth(), 1) : new Date(end.getTime() - (days as number) * 86400000);
    from.value = iso(start);
    to.value = iso(end);
    apply();
};

/* detail + cetak ulang */
const detail = ref<any>(null);
const loadError = ref('');

const open = async (id: string) => {
    loadError.value = '';
    try {
        const res = await fetch(`/orders/${id}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!res.ok) throw new Error();
        detail.value = await res.json();
    } catch {
        loadError.value = 'Gagal memuat transaksi.';
    }
};

/* void */
const voidOpen = ref(false);
const reason = ref('');
const voidError = ref('');
const voiding = ref(false);

const submitVoid = () => {
    if (!reason.value.trim()) {
        voidError.value = 'Alasan wajib diisi.';
        return;
    }
    voiding.value = true;
    router.post(`/orders/${detail.value.order.id}/void`, { reason: reason.value }, {
        preserveScroll: true,
        onSuccess: () => {
            voidOpen.value = false;
            detail.value = null;
            reason.value = '';
            voidError.value = '';
        },
        onError: (e) => (voidError.value = Object.values(e)[0] as string),
        onFinish: () => (voiding.value = false),
    });
};

const field =
    'rounded-lg border bg-transparent px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-neutral-400 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white dark:[color-scheme:dark]';
</script>

<template>
    <Head title="Riwayat Transaksi" />

    <div class="mx-auto w-full max-w-5xl space-y-5 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold">Riwayat Transaksi</h1>
            <p class="text-sm text-neutral-500">Lihat, cetak ulang struk, atau batalkan transaksi.</p>
        </div>

        <div class="flex flex-wrap items-end gap-3">
            <div><label class="mb-1 block text-xs text-neutral-500">Dari</label><input v-model="from" type="date" :class="field" @change="apply" /></div>
            <div><label class="mb-1 block text-xs text-neutral-500">Sampai</label><input v-model="to" type="date" :class="field" @change="apply" /></div>
            <div>
                <label class="mb-1 block text-xs text-neutral-500">Status</label>
                <select v-model="status" :class="field" @change="apply">
                    <option value="">Semua</option>
                    <option value="completed">Selesai</option>
                    <option value="void">Dibatalkan</option>
                </select>
            </div>
            <div class="min-w-40 flex-1">
                <label class="mb-1 block text-xs text-neutral-500">No. struk</label>
                <input v-model="search" type="search" placeholder="INV-..." :class="field" class="w-full" @keyup.enter="apply" />
            </div>
        </div>

        <div class="flex flex-wrap gap-2 text-sm">
            <button class="rounded-full border px-3 py-1 hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800" @click="preset(0)">Hari ini</button>
            <button class="rounded-full border px-3 py-1 hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800" @click="preset(6)">7 hari</button>
            <button class="rounded-full border px-3 py-1 hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800" @click="preset('month')">Bulan ini</button>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div class="rounded-xl border p-4 dark:border-neutral-800">
                <p class="text-xs text-neutral-500">Transaksi selesai</p>
                <p class="text-xl font-bold">{{ summary.count }}</p>
            </div>
            <div class="rounded-xl border p-4 dark:border-neutral-800">
                <p class="text-xs text-neutral-500">Total penjualan</p>
                <p class="text-xl font-bold">{{ rupiah(summary.total) }}</p>
            </div>
        </div>

        <p v-if="loadError" class="text-sm text-red-500">{{ loadError }}</p>

        <div class="overflow-x-auto rounded-xl border dark:border-neutral-800">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-neutral-50 text-xs uppercase text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900">
                    <tr>
                        <th class="px-4 py-3">Struk</th>
                        <th class="px-4 py-3">Kasir</th>
                        <th class="px-4 py-3">Bayar</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-neutral-800">
                    <tr v-for="o in orders.data" :key="o.id">
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ o.number }}</p>
                            <p class="text-xs text-neutral-500">{{ o.time }}</p>
                        </td>
                        <td class="px-4 py-3">{{ o.cashier ?? '-' }}</td>
                        <td class="px-4 py-3">{{ o.methods.map((m: string) => methodLabel[m] ?? m).join(', ') }}</td>
                        <td class="px-4 py-3 text-right font-medium" :class="o.status === 'void' ? 'text-neutral-400 line-through' : ''">{{ rupiah(o.total) }}</td>
                        <td class="px-4 py-3">
                            <span :class="o.status === 'void' ? 'text-red-500' : 'text-green-600'">{{ o.status === 'void' ? 'Dibatalkan' : 'Selesai' }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button class="text-sm font-medium" @click="open(o.id)">Lihat</button>
                        </td>
                    </tr>
                    <tr v-if="!orders.data.length">
                        <td colspan="6" class="px-4 py-10 text-center text-neutral-500">Tidak ada transaksi pada periode ini.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="orders.links.length > 3" class="flex flex-wrap gap-1">
            <template v-for="(l, i) in orders.links" :key="i">
                <Link v-if="l.url" :href="l.url" preserve-state class="rounded-md border px-3 py-1.5 text-sm dark:border-neutral-700"
                    :class="l.active ? 'bg-black text-white dark:bg-white dark:text-black' : ''" v-html="l.label" />
                <span v-else class="px-3 py-1.5 text-sm text-neutral-400" v-html="l.label" />
            </template>
        </div>
    </div>

    <ReceiptModal v-if="detail" :receipt="detail" copy @close="detail = null">
        <button v-if="detail.can_void" type="button" class="rm-danger" @click="voidOpen = true; voidError = ''">Batalkan</button>
    </ReceiptModal>

    <Teleport to="body">
        <div v-if="voidOpen" class="rm-overlay" style="z-index: 90; align-items: center" @click.self="voidOpen = false">
            <div style="width: 100%; max-width: 400px; padding: 22px; border-radius: 16px; background: #fff; color: #111">
                <h3 style="margin: 0; font-size: 17px; font-weight: 600">Batalkan transaksi ini?</h3>
                <p style="margin: 6px 0 12px; font-size: 14px; color: #71717a">
                    {{ detail?.order.number }}. Stok barang akan dikembalikan. Tindakan ini tidak bisa dibatalkan.
                </p>
                <textarea v-model="reason" rows="3" maxlength="200" placeholder="Alasan pembatalan (wajib)"
                    style="width: 100%; padding: 10px; border: 1px solid #d4d4d8; border-radius: 10px; font-size: 14px; background: #fff; color: #111"></textarea>
                <p v-if="voidError" style="margin: 8px 0 0; font-size: 13px; color: #dc2626">{{ voidError }}</p>
                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px">
                    <button type="button" style="cursor: pointer; padding: 10px 18px; border-radius: 10px; border: 1px solid #d4d4d8; background: #fff; color: #111" @click="voidOpen = false">Kembali</button>
                    <button type="button" :disabled="voiding" style="cursor: pointer; padding: 10px 18px; border-radius: 10px; border: 0; background: #dc2626; color: #fff; font-weight: 600" @click="submitVoid">
                        {{ voiding ? 'Memproses...' : 'Ya, batalkan' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>