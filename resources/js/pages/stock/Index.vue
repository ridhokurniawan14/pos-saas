<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

type Unit = { name: string; conversion: number };
type Row = { id: string; name: string; sku: string | null; unit: string; stock_qty: number | null; units: Unit[] };

const props = defineProps<{
    locked: boolean;
    outlets: { id: string; name: string }[];
    outlet_id: string | null;
    products: { data: Row[]; links: { url: string | null; label: string; active: boolean }[] } | null;
    filters: { search?: string };
}>();

const search = ref(props.filters.search ?? '');
const go = (outlet = props.outlet_id) =>
    router.get('/stock', { outlet, search: search.value }, { preserveState: true, replace: true });

const fmt = (r: Row) => {
    const q = r.stock_qty ?? 0;
    const big = [...r.units].sort((a, b) => b.conversion - a.conversion)[0];
    if (!big || Math.abs(q) < big.conversion) return `${q} ${r.unit}`;
    const a = Math.abs(q);
    const n = Math.floor(a / big.conversion);
    const rest = a % big.conversion;
    return `${q} ${r.unit} (${q < 0 ? '-' : ''}${n} ${big.name}${rest ? ` ${rest} ${r.unit}` : ''})`;
};

const active = ref<Row | null>(null);
const form = useForm({ outlet_id: props.outlet_id ?? '', product_id: '', mode: 'add', qty: 0, unit_name: '', note: '' });

const open = (r: Row) => {
    active.value = r;
    form.reset();
    form.outlet_id = props.outlet_id ?? '';
    form.product_id = r.id;
};

const submit = () =>
    form.transform((d) => ({ ...d, unit_name: d.unit_name || null, note: d.note || null })).post('/stock/adjust', {
        preserveScroll: true,
        onSuccess: () => (active.value = null),
    });

const input =
    'w-full rounded-lg border bg-transparent px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-neutral-400 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white dark:[color-scheme:dark]';
</script>

<template>
    <Head title="Stok" />

    <div class="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Stok</h1>
                <p class="text-sm text-neutral-500">Stok dihitung dalam satuan dasar produk.</p>
            </div>
            <select v-if="outlets.length > 1" :value="outlet_id" @change="go(($event.target as HTMLSelectElement).value)" :class="input" class="!w-auto">
                <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
            </select>
        </div>

        <div v-if="locked" class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-6 text-center">
            <p class="font-semibold text-amber-700 dark:text-amber-400">Manajemen stok belum tersedia di paket kamu</p>
            <p class="mt-1 text-sm text-neutral-500">Upgrade ke paket Rintis atau di atasnya untuk melacak stok.</p>
        </div>

        <template v-else-if="products">
            <input
                v-model="search"
                @keyup.enter="go()"
                type="search"
                placeholder="Cari produk lalu tekan Enter"
                :class="input"
            />

            <div class="overflow-x-auto rounded-xl border dark:border-neutral-800">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-neutral-50 text-xs uppercase text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900">
                        <tr>
                            <th class="px-4 py-3">Produk</th>
                            <th class="px-4 py-3 text-right">Stok</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-neutral-800">
                        <tr v-for="r in products.data" :key="r.id">
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ r.name }}</p>
                                <p class="text-xs text-neutral-500">{{ r.sku || '-' }}</p>
                            </td>
                            <td class="px-4 py-3 text-right" :class="(r.stock_qty ?? 0) <= 0 ? 'text-red-500' : ''">{{ fmt(r) }}</td>
                            <td class="px-4 py-3 text-right">
                                <button class="text-sm font-medium" @click="open(r)">Atur stok</button>
                            </td>
                        </tr>
                        <tr v-if="!products.data.length">
                            <td colspan="3" class="px-4 py-10 text-center text-neutral-500">
                                Belum ada produk dengan "Lacak stok" aktif.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="products.links.length > 3" class="flex flex-wrap gap-1">
                <template v-for="(l, i) in products.links" :key="i">
                    <Link
                        v-if="l.url"
                        :href="l.url"
                        preserve-state
                        class="rounded-md border px-3 py-1.5 text-sm dark:border-neutral-700"
                        :class="l.active ? 'bg-black text-white dark:bg-white dark:text-black' : ''"
                        v-html="l.label"
                    />
                    <span v-else class="px-3 py-1.5 text-sm text-neutral-400" v-html="l.label" />
                </template>
            </div>
        </template>
    </div>

    <!-- Modal atur stok -->
    <div v-if="active" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="active = null">
        <form @submit.prevent="submit" class="w-full max-w-md space-y-4 rounded-2xl border bg-white p-6 dark:border-neutral-800 dark:bg-neutral-900">
            <div>
                <h2 class="text-lg font-semibold">{{ active.name }}</h2>
                <p class="text-sm text-neutral-500">Stok saat ini: {{ fmt(active) }}</p>
            </div>

            <div class="grid grid-cols-3 gap-2 text-sm">
                <label v-for="m in [['add', 'Tambah'], ['subtract', 'Kurangi'], ['set', 'Stok Opname']]" :key="m[0]"
                    class="cursor-pointer rounded-lg border px-3 py-2 text-center dark:border-neutral-700"
                    :class="form.mode === m[0] ? 'bg-black text-white dark:bg-white dark:text-black' : ''">
                    <input v-model="form.mode" type="radio" :value="m[0]" class="hidden" />{{ m[1] }}
                </label>
            </div>

            <p class="-mt-2 text-xs text-neutral-500">
                <template v-if="form.mode === 'add'">Isi jumlah barang yang masuk. Contoh: barang datang 10.</template>
                <template v-else-if="form.mode === 'subtract'">Isi jumlah barang yang keluar. Contoh: rusak atau hilang 3.</template>
                <template v-else>Isi jumlah stok <strong>hasil hitung fisik</strong> di rak. Sistem otomatis menyesuaikan selisihnya.</template>
            </p>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">
                        {{ form.mode === 'set' ? 'Stok hasil hitung' : 'Jumlah' }}
                    </label>
                    <input v-model.number="form.qty" type="number" min="0" :class="input" />
                    <p v-if="form.errors.qty" class="mt-1 text-sm text-red-500">{{ form.errors.qty }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Satuan</label>
                    <select v-model="form.unit_name" :class="input">
                        <option value="">{{ active.unit }}</option>
                        <option v-for="u in active.units" :key="u.name" :value="u.name">{{ u.name }} (= {{ u.conversion }} {{ active.unit }})</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium">Catatan (opsional)</label>
                <input v-model="form.note" type="text" placeholder="Mis. stok awal, barang rusak" :class="input" />
            </div>

            <p v-if="Object.keys(form.errors).length && !form.errors.qty" class="text-sm text-red-500">
                {{ Object.values(form.errors)[0] }}
            </p>

            <div class="flex gap-3">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-black px-5 py-2.5 text-sm font-medium text-white disabled:opacity-50 dark:bg-white dark:text-black">
                    {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                </button>
                <button type="button" class="rounded-lg border px-5 py-2.5 text-sm font-medium dark:border-neutral-700" @click="active = null">Batal</button>
            </div>
        </form>
    </div>
</template>