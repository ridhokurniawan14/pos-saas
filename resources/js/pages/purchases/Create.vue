<script setup lang="ts">
import MoneyInput from '@/components/MoneyInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Pembelian', href: '/purchases' }, { title: 'Baru', href: '#' }] },
});

type Unit = { name: string; conversion: number };
type Product = { id: string; name: string; sku: string | null; barcode: string | null; unit: string; cost_price: number; units: Unit[] };
type Line = { product: Product; unit_name: string; qty: number; cost: number | null };

const props = defineProps<{
    outlets: { id: string; name: string }[];
    suppliers: { id: string; name: string }[];
    products: Product[];
}>();

const uuid = () =>
    (crypto as any).randomUUID?.() ??
    'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        return (c === 'x' ? r : (r & 3) | 8).toString(16);
    });

const today = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);

const form = useForm({
    id: uuid(),
    outlet_id: props.outlets[0]?.id ?? '',
    supplier_id: '',
    purchase_date: today,
    note: '',
    update_cost: true,
    items: [] as any[],
});

const lines = ref<Line[]>([]);
const search = ref('');

const results = computed(() => {
    const s = search.value.trim().toLowerCase();
    if (!s) return [];
    return props.products.filter((p) => [p.name, p.sku, p.barcode].some((v) => v?.toLowerCase().includes(s))).slice(0, 8);
});

const conv = (l: Line) => (l.unit_name ? (l.product.units.find((u) => u.name === l.unit_name)?.conversion ?? 1) : 1);

const add = (p: Product) => {
    const found = lines.value.find((l) => l.product.id === p.id && l.unit_name === '');
    if (found) found.qty++;
    else lines.value.push({ product: p, unit_name: '', qty: 1, cost: p.cost_price });
    search.value = '';
};

const onUnit = (l: Line) => (l.cost = l.product.cost_price * conv(l));
const remove = (i: number) => lines.value.splice(i, 1);

const rupiah = (n: number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n || 0);

const total = computed(() => lines.value.reduce((s, l) => s + (l.cost ?? 0) * l.qty, 0));

const errorList = computed(() => Object.values(form.errors));

const submit = () => {
    form.transform((d) => ({
        ...d,
        supplier_id: d.supplier_id || null,
        note: d.note || null,
        items: lines.value.map((l) => ({
            product_id: l.product.id,
            unit_name: l.unit_name || null,
            qty: Math.max(1, Math.floor(Number(l.qty) || 1)),
            cost: l.cost ?? 0,
        })),
    })).post('/purchases');
};

const input =
    'w-full rounded-lg border bg-transparent px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-neutral-400 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white dark:[color-scheme:dark]';
</script>

<template>
    <Head title="Pembelian Baru" />

    <div class="mx-auto w-full max-w-3xl space-y-5 p-4 md:p-6">
        <h1 class="text-xl font-semibold">Pembelian Baru</h1>

        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-sm font-medium">Tanggal</label>
                <input v-model="form.purchase_date" type="date" :class="input" />
            </div>
            <div v-if="outlets.length > 1">
                <label class="mb-1.5 block text-sm font-medium">Masuk ke cabang</label>
                <select v-model="form.outlet_id" :class="input">
                    <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
                </select>
            </div>
            <div :class="outlets.length > 1 ? 'sm:col-span-2' : ''">
                <label class="mb-1.5 block text-sm font-medium">
                    Supplier
                    <Link href="/suppliers" class="ml-2 text-xs font-normal underline">kelola</Link>
                </label>
                <select v-model="form.supplier_id" :class="input">
                    <option value="">Tanpa supplier</option>
                    <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>
        </div>

        <div class="relative">
            <label class="mb-1.5 block text-sm font-medium">Tambah barang</label>
            <input v-model="search" type="search" placeholder="Cari nama, SKU, atau barcode" :class="input" />
            <div v-if="results.length" class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border bg-white shadow-lg dark:border-neutral-700 dark:bg-neutral-900">
                <button v-for="p in results" :key="p.id" type="button" class="flex w-full items-center justify-between px-3 py-2.5 text-left text-sm hover:bg-neutral-100 dark:hover:bg-neutral-800" @click="add(p)">
                    <span>{{ p.name }}</span>
                    <span class="text-xs text-neutral-500">modal {{ rupiah(p.cost_price) }}/{{ p.unit }}</span>
                </button>
            </div>
            <p v-else-if="search.trim()" class="mt-1 text-xs text-neutral-500">Produk tidak ditemukan.</p>
        </div>

        <div class="space-y-2">
            <div v-for="(l, i) in lines" :key="i" class="space-y-2 rounded-xl border p-3 dark:border-neutral-800">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-sm font-medium">{{ l.product.name }}</p>
                    <button type="button" class="text-sm text-red-500" @click="remove(i)">Hapus</button>
                </div>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <input v-model.number="l.qty" type="number" min="1" placeholder="Jumlah" :class="input" />
                    <select v-model="l.unit_name" :class="input" @change="onUnit(l)">
                        <option value="">{{ l.product.unit }}</option>
                        <option v-for="u in l.product.units" :key="u.name" :value="u.name">{{ u.name }} (= {{ u.conversion }} {{ l.product.unit }})</option>
                    </select>
                    <MoneyInput v-model="l.cost" placeholder="Harga beli / satuan" :class="input" />
                    <div class="flex items-center justify-end text-sm font-semibold">{{ rupiah((l.cost ?? 0) * l.qty) }}</div>
                </div>
            </div>
            <p v-if="!lines.length" class="rounded-xl border border-dashed py-8 text-center text-sm text-neutral-500 dark:border-neutral-700">
                Cari dan pilih barang yang dibeli.
            </p>
        </div>

        <div>
            <input v-model="form.note" maxlength="200" placeholder="Catatan (opsional, mis. no. nota supplier)" :class="input" />
        </div>

        <label class="flex cursor-pointer items-start gap-3 text-sm">
            <input v-model="form.update_cost" type="checkbox" class="mt-1" />
            <span>
                <span class="font-medium">Perbarui harga modal produk</span>
                <span class="block text-neutral-500">Harga modal produk diganti dengan harga beli ini (per satuan dasar).</span>
            </span>
        </label>

        <div v-if="errorList.length" class="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-600">
            <ul class="list-disc pl-5">
                <li v-for="(m, i) in errorList" :key="i">{{ m }}</li>
            </ul>
        </div>

        <div class="flex items-center justify-between gap-3 border-t pt-4 dark:border-neutral-800">
            <div>
                <p class="text-xs text-neutral-500">Total pembelian</p>
                <p class="text-xl font-bold">{{ rupiah(total) }}</p>
            </div>
            <div class="flex gap-3">
                <Link href="/purchases" class="rounded-lg border px-5 py-2.5 text-sm font-medium dark:border-neutral-700">Batal</Link>
                <button type="button" :disabled="form.processing || !lines.length" class="rounded-lg bg-black px-5 py-2.5 text-sm font-medium text-white disabled:opacity-40 dark:bg-white dark:text-black" @click="submit">
                    {{ form.processing ? 'Menyimpan...' : 'Simpan & Tambah Stok' }}
                </button>
            </div>
        </div>
    </div>
</template>