<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';

const props = defineProps<{
    products: { data: any[]; links: { url: string | null; label: string; active: boolean }[] };
    filters: { search?: string };
    usage: { count: number; limit: number };
}>();

const search = ref(props.filters.search ?? '');
let timer: number | undefined;

watch(search, () => {
    clearTimeout(timer);
    timer = window.setTimeout(
        () => router.get('/products', { search: search.value || undefined }, { preserveState: true, preserveScroll: true, replace: true }),
        300,
    );
});
onBeforeUnmount(() => clearTimeout(timer));

const rupiah = (n: number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n);

const target = ref<any>(null);

const remove = () => {
    if (!target.value) return;
    router.delete(`/products/${target.value.id}`, { preserveScroll: true });
    target.value = null;
};
</script>

<template>
    <Head title="Produk" />

    <div class="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Produk</h1>
                <p class="text-sm text-neutral-500">
                    {{ usage.count }} / {{ usage.limit === -1 ? 'Tak terbatas' : usage.limit }} produk terpakai
                </p>
            </div>
            <div class="flex gap-2">
                <Link href="/categories" class="rounded-lg border px-4 py-2.5 text-sm font-medium dark:border-neutral-700">Kategori</Link>
                <Link href="/products/create" class="rounded-lg bg-black px-4 py-2.5 text-sm font-medium text-white dark:bg-white dark:text-black">
                    + Tambah Produk
                </Link>
            </div>
        </div>

        <input
            v-model="search"
            type="search"
            placeholder="Cari nama, SKU, atau barcode"
            class="w-full rounded-lg border bg-transparent px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-neutral-400 dark:border-neutral-700"
        />

        <div class="overflow-x-auto rounded-xl border dark:border-neutral-800">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-neutral-50 text-xs uppercase text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900">
                    <tr>
                        <th class="px-4 py-3">Produk</th>
                        <th class="px-4 py-3">Kategori</th>
                        <th class="px-4 py-3 text-right">Harga Jual</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-neutral-800">
                    <tr v-for="p in products.data" :key="p.id">
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ p.name }}</p>
                            <p class="text-xs text-neutral-500">{{ p.sku || p.barcode || '-' }}</p>
                        </td>
                        <td class="px-4 py-3">{{ p.category?.name ?? '-' }}</td>
                        <td class="px-4 py-3 text-right">{{ rupiah(p.sell_price) }}</td>
                        <td class="px-4 py-3">
                            <span :class="p.is_active ? 'text-green-600' : 'text-neutral-400'">
                                {{ p.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="space-x-3 px-4 py-3 text-right">
                            <Link :href="`/products/${p.id}/edit`" class="text-neutral-500 hover:text-neutral-900 dark:hover:text-white">Edit</Link>
                            <button class="text-red-500" @click="target = p">Hapus</button>
                        </td>
                    </tr>
                    <tr v-if="!products.data.length">
                        <td colspan="5" class="px-4 py-10 text-center text-neutral-500">Belum ada produk.</td>
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
    </div>
    <ConfirmDialog
        :open="!!target"
        title="Hapus produk ini?"
        :message="target?.name"
        confirm-text="Ya, hapus"
        danger
        @confirm="remove"
        @cancel="target = null"
    />
</template>