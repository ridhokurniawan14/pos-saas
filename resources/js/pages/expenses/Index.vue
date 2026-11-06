<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import MoneyInput from '@/components/MoneyInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Pengeluaran', href: '/expenses' }] } });

type Expense = { id: string; date: string; category: string; amount: number; note: string | null; outlet: string | null };

const props = defineProps<{
    expenses: { data: Expense[]; links: { url: string | null; label: string; active: boolean }[] };
    filters: { from: string; to: string; search?: string | null };
    total: number;
    outlets: { id: string; name: string }[];
}>();

const rupiah = (n: number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n || 0);

const iso = (d: Date) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
const categories = ['Listrik', 'Air', 'Gaji karyawan', 'Sewa tempat', 'Transportasi', 'Perlengkapan', 'Pemasaran', 'Perawatan', 'Lainnya'];

const form = useForm({ expense_date: iso(new Date()), category: '', amount: null as number | null, note: '', outlet_id: '' });
const add = () =>
    form
        .transform((d) => ({ ...d, amount: d.amount ?? 0, note: d.note || null, outlet_id: d.outlet_id || null }))
        .post('/expenses', { preserveScroll: true, onSuccess: () => form.reset('category', 'amount', 'note') });

const from = ref(props.filters.from);
const to = ref(props.filters.to);
const search = ref(props.filters.search ?? '');

let timer: number | undefined;
watch([from, to, search], () => {
    clearTimeout(timer);
    timer = window.setTimeout(
        () =>
            router.get('/expenses', { from: from.value, to: to.value, search: search.value || undefined }, { preserveState: true, preserveScroll: true, replace: true }),
        300,
    );
});
onBeforeUnmount(() => clearTimeout(timer));

const target = ref<Expense | null>(null);
const remove = () => {
    if (!target.value) return;
    router.delete(`/expenses/${target.value.id}`, { preserveScroll: true });
    target.value = null;
};

const input =
    'w-full rounded-lg border bg-transparent px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-neutral-400 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white dark:[color-scheme:dark]';
</script>

<template>
    <Head title="Pengeluaran" />

    <div class="mx-auto w-full max-w-3xl space-y-5 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold">Pengeluaran</h1>
            <p class="text-sm text-neutral-500">Biaya operasional (listrik, gaji, sewa) untuk menghitung laba bersih.</p>
        </div>

        <form class="space-y-3 rounded-xl border p-4 dark:border-neutral-800" @submit.prevent="add">
            <p class="text-sm font-medium">Catat pengeluaran</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <input v-model="form.expense_date" type="date" :class="input" />
                <div>
                    <input v-model="form.category" list="expense-cats" placeholder="Kategori (mis. Listrik)" :class="input" />
                    <datalist id="expense-cats"><option v-for="c in categories" :key="c" :value="c" /></datalist>
                </div>
                <MoneyInput v-model="form.amount" placeholder="Jumlah (Rp)" :class="input" />
                <select v-if="outlets.length > 1" v-model="form.outlet_id" :class="input">
                    <option value="">Semua cabang / umum</option>
                    <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
                </select>
            </div>
            <input v-model="form.note" maxlength="200" placeholder="Catatan (opsional)" :class="input" />
            <p v-if="Object.keys(form.errors).length" class="text-sm text-red-500">{{ Object.values(form.errors)[0] }}</p>
            <div class="flex justify-end">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-black px-5 py-2.5 text-sm font-medium text-white disabled:opacity-50 dark:bg-white dark:text-black">Simpan</button>
            </div>
        </form>

        <div class="flex flex-wrap items-end gap-3">
            <div><label class="mb-1 block text-xs text-neutral-500">Dari</label><input v-model="from" type="date" :class="input" /></div>
            <div><label class="mb-1 block text-xs text-neutral-500">Sampai</label><input v-model="to" type="date" :class="input" /></div>
            <div class="min-w-40 flex-1"><label class="mb-1 block text-xs text-neutral-500">Cari</label><input v-model="search" type="search" placeholder="Kategori atau catatan" :class="input" /></div>
        </div>

        <div class="rounded-xl border p-4 dark:border-neutral-800">
            <p class="text-xs text-neutral-500">Total pengeluaran pada periode ini</p>
            <p class="text-xl font-bold">{{ rupiah(total) }}</p>
        </div>

        <div class="divide-y rounded-xl border dark:divide-neutral-800 dark:border-neutral-800">
            <div v-for="e in expenses.data" :key="e.id" class="flex items-center justify-between gap-3 px-4 py-3">
                <div class="min-w-0">
                    <p class="text-sm font-medium">{{ e.category }}</p>
                    <p class="text-xs text-neutral-500">{{ e.date }}<span v-if="e.outlet"> · {{ e.outlet }}</span><span v-if="e.note"> · {{ e.note }}</span></p>
                </div>
                <div class="flex items-center gap-4 text-sm">
                    <span class="font-semibold">{{ rupiah(e.amount) }}</span>
                    <button class="text-red-500" @click="target = e">Hapus</button>
                </div>
            </div>
            <p v-if="!expenses.data.length" class="px-4 py-8 text-center text-sm text-neutral-500">Belum ada pengeluaran pada periode ini.</p>
        </div>

        <div v-if="expenses.links.length > 3" class="flex flex-wrap gap-1">
            <template v-for="(l, i) in expenses.links" :key="i">
                <Link v-if="l.url" :href="l.url" preserve-state class="rounded-md border px-3 py-1.5 text-sm dark:border-neutral-700"
                    :class="l.active ? 'bg-black text-white dark:bg-white dark:text-black' : ''" v-html="l.label" />
                <span v-else class="px-3 py-1.5 text-sm text-neutral-400" v-html="l.label" />
            </template>
        </div>
    </div>

    <ConfirmDialog
        :open="!!target"
        title="Hapus pengeluaran ini?"
        :message="target ? `${target.category} · ${rupiah(target.amount)}` : ''"
        confirm-text="Ya, hapus"
        danger
        @confirm="remove"
        @cancel="target = null"
    />
</template>