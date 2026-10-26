<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    categories: { id: string; name: string }[];
    product: any | null;
}>();

const page = usePage<any>();
const canWholesale = computed(() => (page.props.business?.features ?? []).includes('wholesale'));

const form = useForm({
    name: props.product?.name ?? '',
    category_id: props.product?.category_id ?? '',
    sku: props.product?.sku ?? '',
    barcode: props.product?.barcode ?? '',
    unit: props.product?.unit ?? 'pcs',
    cost_price: props.product?.cost_price ?? 0,
    sell_price: props.product?.sell_price ?? 0,
    track_stock: props.product?.track_stock ?? true,
    is_active: props.product?.is_active ?? true,
    image: null as File | null,
    remove_image: false,
    units: (props.product?.units ?? []).map((u: any) => ({
        name: u.name,
        conversion: u.conversion,
        sell_price: u.sell_price,
        barcode: u.barcode ?? '',
    })),
    tiers: (props.product?.price_tiers ?? []).map((t: any) => ({
        unit_name: t.unit_name ?? '',
        min_qty: t.min_qty,
        price: t.price,
    })),
});

const preview = ref<string | null>(props.product?.image_url ?? null);

const onFile = (e: Event) => {
    const f = (e.target as HTMLInputElement).files?.[0] ?? null;
    form.image = f;
    form.remove_image = false;
    preview.value = f ? URL.createObjectURL(f) : (props.product?.image_url ?? null);
};

const removeImage = () => {
    form.image = null;
    form.remove_image = true;
    preview.value = null;
};

const addUnit = () => form.units.push({ name: '', conversion: 12, sell_price: 0, barcode: '' });
const addTier = () => form.tiers.push({ unit_name: '', min_qty: 10, price: 0 });

const sortedTiers = computed(() =>
    form.tiers
        .map((t, i) => ({ t, i }))
        .sort(
            (a, b) =>
                (a.t.unit_name || '').localeCompare(b.t.unit_name || '') ||
                (a.t.min_qty || 0) - (b.t.min_qty || 0),
        ),
);

const rangeLabel = (pos: number) => {
    const list = sortedTiers.value;
    const cur = list[pos];
    const unit = cur.t.unit_name || form.unit;
    const from = cur.t.min_qty || '?';
    const next = list[pos + 1];
    const sameUnit = next && (next.t.unit_name || '') === (cur.t.unit_name || '');
    return sameUnit ? `${from} - ${next.t.min_qty - 1} ${unit}` : `${from}+ ${unit}`;
};

const hasTierError = computed(() => Object.keys(form.errors).some((k) => k.startsWith('tiers')));
const hasUnitError = computed(() => Object.keys(form.errors).some((k) => k.startsWith('units')));

const submit = () => {
    form.transform((d) => ({
        ...d,
        category_id: d.category_id || null,
        units: d.units.map((u) => ({ ...u, barcode: u.barcode || null })),
        tiers: d.tiers.map((t) => ({ ...t, unit_name: t.unit_name || null })),
        _method: props.product ? 'put' : 'post',
    })).post(props.product ? `/products/${props.product.id}` : '/products', { forceFormData: true });
};

const input =
    'w-full rounded-lg border bg-transparent px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-neutral-400 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white dark:[color-scheme:dark]';
</script>

<template>
    <Head :title="product ? 'Edit Produk' : 'Tambah Produk'" />

    <div class="mx-auto w-full max-w-2xl space-y-6 p-4 md:p-6">
        <h1 class="text-xl font-semibold">{{ product ? 'Edit Produk' : 'Tambah Produk' }}</h1>

        <p v-if="form.errors.limit" class="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-600">
            {{ form.errors.limit }}
        </p>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium">Foto produk</label>
                <div class="flex items-center gap-4">
                    <div class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-xl border bg-neutral-100 dark:border-neutral-700 dark:bg-neutral-800">
                        <img v-if="preview" :src="preview" class="h-full w-full object-cover" />
                        <span v-else class="text-xs text-neutral-400">Tanpa foto</span>
                    </div>
                    <div class="space-y-2">
                        <input type="file" accept="image/png,image/jpeg,image/webp" class="block text-sm" @change="onFile" />
                        <button v-if="preview" type="button" class="text-sm text-red-500" @click="removeImage">Hapus foto</button>
                        <p class="text-xs text-neutral-500">JPG, PNG, atau WebP. Maksimal 2 MB.</p>
                    </div>
                </div>
                <p v-if="form.errors.image" class="mt-1 text-sm text-red-500">{{ form.errors.image }}</p>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium">Nama produk</label>
                <input v-model="form.name" type="text" :class="input" />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-500">{{ form.errors.name }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Kategori</label>
                    <select v-model="form.category_id" :class="input">
                        <option value="">Tanpa kategori</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Satuan dasar</label>
                    <input v-model="form.unit" type="text" :class="input" />
                    <p v-if="form.errors.unit" class="mt-1 text-sm text-red-500">{{ form.errors.unit }}</p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">SKU</label>
                    <input v-model="form.sku" type="text" :class="input" />
                    <p v-if="form.errors.sku" class="mt-1 text-sm text-red-500">{{ form.errors.sku }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Barcode</label>
                    <input v-model="form.barcode" type="text" :class="input" />
                    <p v-if="form.errors.barcode" class="mt-1 text-sm text-red-500">{{ form.errors.barcode }}</p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Harga modal (Rp)</label>
                    <input v-model.number="form.cost_price" type="number" min="0" :class="input" />
                    <p v-if="form.errors.cost_price" class="mt-1 text-sm text-red-500">{{ form.errors.cost_price }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Harga jual (Rp)</label>
                    <input v-model.number="form.sell_price" type="number" min="0" :class="input" />
                    <p v-if="form.errors.sell_price" class="mt-1 text-sm text-red-500">{{ form.errors.sell_price }}</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-6">
                <label class="flex items-center gap-2 text-sm"><input v-model="form.track_stock" type="checkbox" /> Lacak stok</label>
                <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" /> Aktif dijual</label>
            </div>

            <!-- GROSIR -->
            <div class="space-y-5 rounded-xl border p-4 dark:border-neutral-800">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">Grosir</h2>
                    <span v-if="!canWholesale" class="rounded-full bg-amber-500/15 px-3 py-1 text-xs font-medium text-amber-600">
                        Paket Tumbuh ke atas
                    </span>
                </div>

                <div v-if="canWholesale" class="space-y-6">
                    <!-- Satuan bertingkat -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium">Satuan bertingkat</p>
                                <p class="text-xs text-neutral-500">Contoh: nama "dus", isi 24 → 1 dus = 24 {{ form.unit }}.</p>
                            </div>
                            <button type="button" class="text-sm font-medium" @click="addUnit">+ Tambah</button>
                        </div>

                        <div v-for="(u, i) in form.units" :key="i" class="grid grid-cols-2 items-center gap-2 sm:grid-cols-5">
                            <input v-model="u.name" placeholder="Nama (dus)" :class="input" />
                            <input v-model.number="u.conversion" type="number" min="2" placeholder="Isi" :class="input" />
                            <input v-model.number="u.sell_price" type="number" min="0" placeholder="Harga" :class="input" />
                            <input v-model="u.barcode" placeholder="Barcode" :class="input" />
                            <button type="button" class="text-sm text-red-500" @click="form.units.splice(i, 1)">Hapus</button>
                        </div>
                        <p v-if="hasUnitError" class="text-sm text-red-500">
                            Cek isian satuan: nama, isi (minimal 2), dan harga wajib diisi.
                        </p>
                    </div>

                    <!-- Harga bertingkat -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium">Harga bertingkat</p>
                                <p class="text-xs text-neutral-500">
                                    Pilih satuan, isi "mulai dari" dan harga per satuan itu. Batas atas otomatis.
                                </p>
                            </div>
                            <button type="button" class="text-sm font-medium" @click="addTier">+ Tambah</button>
                        </div>

                        <div
                            v-for="(item, pos) in sortedTiers"
                            :key="item.i"
                            class="grid grid-cols-2 items-center gap-2 sm:grid-cols-[1fr_1fr_1fr_1fr_auto]"
                        >
                            <select v-model="form.tiers[item.i].unit_name" :class="input">
                                <option value="">{{ form.unit }} (dasar)</option>
                                <option v-for="u in form.units.filter((x) => x.name)" :key="u.name" :value="u.name">
                                    {{ u.name }}
                                </option>
                            </select>
                            <input v-model.number="form.tiers[item.i].min_qty" type="number" min="2" placeholder="Mulai dari" :class="input" />
                            <input v-model.number="form.tiers[item.i].price" type="number" min="0" placeholder="Harga/satuan" :class="input" />
                            <span class="text-center text-sm text-neutral-500">{{ rangeLabel(pos) }}</span>
                            <button type="button" class="text-sm text-red-500" @click="form.tiers.splice(item.i, 1)">Hapus</button>
                        </div>

                        <p v-if="hasTierError" class="text-sm text-red-500">
                            Cek isian harga bertingkat: min qty tidak boleh ganda dalam satu satuan.
                        </p>
                    </div>
                </div>

                <p v-else class="text-sm text-neutral-500">
                    Upgrade ke paket Tumbuh untuk menjual per dus/lusin dan memberi harga grosir otomatis.
                </p>
            </div>

            <div class="flex gap-3 pt-2">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-black px-5 py-2.5 text-sm font-medium text-white disabled:opacity-50 dark:bg-white dark:text-black"
                >
                    {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                </button>
                <Link href="/products" class="rounded-lg border px-5 py-2.5 text-sm font-medium dark:border-neutral-700">Batal</Link>
            </div>
        </form>
    </div>
</template>