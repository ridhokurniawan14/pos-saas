<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

type Unit = { name: string; conversion: number; sell_price: number };
type Tier = { unit_name: string | null; min_qty: number; price: number };
type Product = {
    id: string; name: string; sku: string | null; barcode: string | null; unit: string;
    sell_price: number; category_id: string | null; image_url: string | null; units: Unit[]; tiers: Tier[];
};
type Line = { product: Product; unit_name: string; qty: number };

const props = defineProps<{
    business_name: string;
    outlets: { id: string; name: string }[];
    categories: { id: string; name: string }[];
    products: Product[];
}>();

const page = usePage<any>();
const readOnly = computed(() => !!page.props.business?.read_only);

const rupiah = (n: number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n || 0);

const uuid = () =>
    (crypto as any).randomUUID?.() ??
    'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        return (c === 'x' ? r : (r & 3) | 8).toString(16);
    });

const xsrf = () =>
    decodeURIComponent(document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');

/* ---------- state ---------- */
const outletId = ref(props.outlets[0]?.id ?? '');
const search = ref('');
const categoryId = ref<string | null>(null);
const cart = ref<Line[]>([]);
const discount = ref<number>(0);
const searchEl = ref<HTMLInputElement | null>(null);

const payOpen = ref(false);
const method = ref<'cash' | 'qris' | 'debit' | 'transfer'>('cash');
const cashInput = ref<number | null>(null);
const processing = ref(false);
const error = ref('');
const orderId = ref(uuid());
const receipt = ref<any>(null);

onMounted(() => searchEl.value?.focus());

/* ---------- katalog ---------- */
const filtered = computed(() => {
    const s = search.value.trim().toLowerCase();
    return props.products
        .filter((p) => !categoryId.value || p.category_id === categoryId.value)
        .filter((p) => !s || [p.name, p.sku, p.barcode].some((v) => v?.toLowerCase().includes(s)))
        .slice(0, 60);
});

/* ---------- harga (tampilan saja, server menghitung ulang) ---------- */
const priceOf = (l: Line) => {
    const unit = l.unit_name ? l.product.units.find((u) => u.name === l.unit_name) : null;
    const base = unit ? unit.sell_price : l.product.sell_price;
    const tier = l.product.tiers
        .filter((t) => (t.unit_name || '') === (l.unit_name || '') && t.min_qty <= l.qty)
        .sort((a, b) => b.min_qty - a.min_qty)[0];
    return tier ? tier.price : base;
};
const lineTotal = (l: Line) => priceOf(l) * l.qty;

const subtotal = computed(() => cart.value.reduce((s, l) => s + lineTotal(l), 0));
const discountApplied = computed(() => Math.min(Math.max(discount.value || 0, 0), subtotal.value));
const total = computed(() => subtotal.value - discountApplied.value);
const itemCount = computed(() => cart.value.reduce((s, l) => s + l.qty, 0));

/* ---------- keranjang ---------- */
const add = (p: Product) => {
    const found = cart.value.find((l) => l.product.id === p.id && l.unit_name === '');
    if (found) found.qty++;
    else cart.value.push({ product: p, unit_name: '', qty: 1 });
};
const dec = (i: number) => {
    if (cart.value[i].qty <= 1) cart.value.splice(i, 1);
    else cart.value[i].qty--;
};
const setQty = (i: number, v: number) => {
    cart.value[i].qty = Math.max(1, Math.floor(Number(v) || 1));
};
const clearCart = () => {
    if (cart.value.length && confirm('Kosongkan keranjang?')) {
        cart.value = [];
        discount.value = 0;
    }
};

// Scanner mengirim Enter otomatis: barcode/SKU persis atau hanya 1 hasil -> langsung masuk keranjang
const onEnter = () => {
    const s = search.value.trim().toLowerCase();
    if (!s) return;
    const exact = props.products.find((p) => p.barcode?.toLowerCase() === s || p.sku?.toLowerCase() === s);
    const target = exact ?? (filtered.value.length === 1 ? filtered.value[0] : null);
    if (target) {
        add(target);
        search.value = '';
    }
};

/* ---------- pembayaran ---------- */
const paid = computed(() => (method.value === 'cash' ? cashInput.value || 0 : total.value));
const change = computed(() => paid.value - total.value);
const canPay = computed(() => total.value > 0 && paid.value >= total.value && !readOnly.value);
const quick = computed(() => {
    const t = total.value;
    const set = new Set<number>([t]);
    [10000, 50000, 100000].forEach((s) => set.add(Math.ceil(t / s) * s));
    return [...set].sort((a, b) => a - b).slice(0, 4);
});

const openPay = () => {
    error.value = '';
    method.value = 'cash';
    cashInput.value = null;
    payOpen.value = true;
};

const checkout = async () => {
    if (processing.value || !canPay.value) return;
    processing.value = true;
    error.value = '';

    try {
        const res = await fetch('/pos/orders', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrf(),
            },
            body: JSON.stringify({
                id: orderId.value,
                outlet_id: outletId.value,
                created_at_client: new Date().toISOString(),
                discount: discountApplied.value,
                items: cart.value.map((l) => ({
                    product_id: l.product.id,
                    unit_name: l.unit_name || null,
                    qty: l.qty,
                })),
                payments: [{ method: method.value, amount: paid.value }],
            }),
        });

        if (res.ok) {
            receipt.value = await res.json();
            cart.value = [];
            discount.value = 0;
            payOpen.value = false;
            orderId.value = uuid();
            return;
        }

        if (res.status === 403) error.value = 'Masa trial/langganan habis. Upgrade paket untuk melanjutkan.';
        else if (res.status === 419) error.value = 'Sesi habis. Muat ulang halaman ini.';
        else {
            const d = await res.json().catch(() => null);
            error.value = d?.errors ? (Object.values(d.errors)[0] as string[])[0] : (d?.message ?? 'Gagal menyimpan transaksi.');
        }
    } catch {
        error.value = 'Tidak ada koneksi. Coba lagi.';
    } finally {
        processing.value = false;
    }
};

const closeReceipt = () => {
    receipt.value = null;
    searchEl.value?.focus();
};

const printReceipt = () => window.print();

const methodLabel: Record<string, string> = { cash: 'Tunai', qris: 'QRIS', debit: 'Debit', transfer: 'Transfer' };

const field =
    'rounded-lg border bg-transparent px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-neutral-400 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white dark:[color-scheme:dark]';
</script>

<template>
    <Head title="Kasir" />

    <div class="flex h-svh flex-col bg-neutral-100 dark:bg-neutral-950">
        <!-- Header -->
        <header class="flex items-center justify-between gap-3 border-b bg-white px-4 py-2.5 dark:border-neutral-800 dark:bg-neutral-900">
            <div class="flex items-center gap-3">
                <Link href="/dashboard" class="rounded-lg border px-3 py-1.5 text-sm dark:border-neutral-700">← Keluar</Link>
                <div>
                    <p class="text-sm font-semibold leading-tight">{{ business_name }}</p>
                    <p class="text-xs text-neutral-500">Kasir</p>
                </div>
            </div>
            <select v-if="outlets.length > 1" v-model="outletId" :class="field" class="!py-1.5">
                <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
            </select>
        </header>

        <p v-if="readOnly" class="bg-red-500/10 px-4 py-2 text-center text-sm text-red-600">
            Masa trial/langganan habis. Transaksi baru dinonaktifkan. Upgrade paket untuk melanjutkan.
        </p>

        <div class="grid min-h-0 flex-1 grid-rows-[1fr_auto] md:grid-cols-[1fr_380px] md:grid-rows-1">
            <!-- Katalog -->
            <section class="flex min-h-0 flex-col gap-3 p-3">
                <input
                    ref="searchEl"
                    v-model="search"
                    @keyup.enter="onEnter"
                    type="search"
                    placeholder="Cari produk, atau scan barcode"
                    :class="field"
                    class="w-full bg-white dark:bg-neutral-900"
                />

                <div class="flex gap-2 overflow-x-auto pb-1">
                    <button
                        class="shrink-0 rounded-full border px-4 py-1.5 text-sm dark:border-neutral-700"
                        :class="!categoryId ? 'bg-black text-white dark:bg-white dark:text-black' : 'bg-white dark:bg-neutral-900'"
                        @click="categoryId = null"
                    >Semua</button>
                    <button
                        v-for="c in categories"
                        :key="c.id"
                        class="shrink-0 rounded-full border px-4 py-1.5 text-sm dark:border-neutral-700"
                        :class="categoryId === c.id ? 'bg-black text-white dark:bg-white dark:text-black' : 'bg-white dark:bg-neutral-900'"
                        @click="categoryId = c.id"
                    >{{ c.name }}</button>
                </div>

                <div class="grid min-h-0 flex-1 auto-rows-min grid-cols-2 content-start gap-3 overflow-y-auto sm:grid-cols-3 xl:grid-cols-5">
                    <button
                        v-for="p in filtered"
                        :key="p.id"
                        class="overflow-hidden rounded-xl border bg-white text-left shadow-sm transition hover:border-neutral-400 active:scale-[0.97] dark:border-neutral-800 dark:bg-neutral-900"
                        @click="add(p)"
                    >
                        <div class="aspect-square w-full bg-neutral-100 dark:bg-neutral-800">
                            <img v-if="p.image_url" :src="p.image_url" :alt="p.name" loading="lazy" class="h-full w-full object-cover" />
                            <div v-else class="flex h-full w-full items-center justify-center text-4xl font-bold text-neutral-300 dark:text-neutral-600">
                                {{ p.name.charAt(0).toUpperCase() }}
                            </div>
                        </div>
                        <div class="p-3">
                            <p class="line-clamp-2 min-h-[2.5rem] text-sm font-medium leading-tight">{{ p.name }}</p>
                            <p class="mt-1 text-sm font-bold">{{ rupiah(p.sell_price) }}</p>
                            <p v-if="p.units.length" class="mt-0.5 truncate text-xs text-neutral-500">
                                + {{ p.units.map((u) => u.name).join(', ') }}
                            </p>
                        </div>
                    </button>
                    <p v-if="!filtered.length" class="col-span-full py-10 text-center text-sm text-neutral-500">
                        Produk tidak ditemukan.
                    </p>
                </div>
            </section>

            <!-- Keranjang -->
            <aside class="flex max-h-[55svh] min-h-0 flex-col border-t bg-white md:max-h-none md:border-l md:border-t-0 dark:border-neutral-800 dark:bg-neutral-900">
                <div class="flex items-center justify-between px-4 py-3">
                    <h2 class="font-semibold">Keranjang <span class="text-sm font-normal text-neutral-500">({{ itemCount }})</span></h2>
                    <button class="text-sm text-red-500" @click="clearCart">Kosongkan</button>
                </div>

                <div class="min-h-0 flex-1 space-y-2 overflow-y-auto px-4 pb-3">
                    <div v-for="(l, i) in cart" :key="i" class="rounded-xl border p-3 dark:border-neutral-800">
                        <div class="flex gap-3">
                            <div class="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-neutral-100 dark:bg-neutral-800">
                                <img v-if="l.product.image_url" :src="l.product.image_url" class="h-full w-full object-cover" />
                                <div v-else class="flex h-full w-full items-center justify-center font-bold text-neutral-400">
                                    {{ l.product.name.charAt(0).toUpperCase() }}
                                </div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-sm font-medium leading-tight">{{ l.product.name }}</p>
                                    <p class="shrink-0 text-sm font-semibold">{{ rupiah(lineTotal(l)) }}</p>
                                </div>
                                <p class="text-xs text-neutral-500">{{ rupiah(priceOf(l)) }} / {{ l.unit_name || l.product.unit }}</p>
                            </div>
                        </div>

                        <div class="mt-2 flex items-center gap-2">
                            <button class="h-9 w-9 rounded-lg border text-lg dark:border-neutral-700" @click="dec(i)">−</button>
                            <input
                                :value="l.qty"
                                @change="setQty(i, +($event.target as HTMLInputElement).value)"
                                type="number" min="1"
                                class="h-9 w-16 rounded-lg border bg-transparent text-center text-sm dark:border-neutral-700"
                            />
                            <button class="h-9 w-9 rounded-lg border text-lg dark:border-neutral-700" @click="l.qty++">+</button>

                            <select v-if="l.product.units.length" v-model="l.unit_name" :class="field" class="!ml-auto !py-1.5">
                                <option value="">{{ l.product.unit }}</option>
                                <option v-for="u in l.product.units" :key="u.name" :value="u.name">{{ u.name }}</option>
                            </select>
                        </div>
                    </div>
                    <p v-if="!cart.length" class="py-12 text-center text-sm text-neutral-500">Pilih produk untuk memulai.</p>
                </div>

                <div class="space-y-3 border-t p-4 dark:border-neutral-800">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-neutral-500">Subtotal</span><span>{{ rupiah(subtotal) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="text-neutral-500">Diskon (Rp)</span>
                        <input v-model.number="discount" type="number" min="0" :class="field" class="!w-32 !py-1.5 text-right" />
                    </div>
                    <div class="flex items-center justify-between text-lg font-bold">
                        <span>Total</span><span>{{ rupiah(total) }}</span>
                    </div>
                    <button
                        :disabled="!cart.length || readOnly"
                        class="w-full rounded-xl bg-black py-3.5 text-base font-semibold text-white disabled:opacity-40 dark:bg-white dark:text-black"
                        @click="openPay"
                    >Bayar</button>
                </div>
            </aside>
        </div>
    </div>

    <!-- Modal bayar -->
    <div v-if="payOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="payOpen = false">
        <div class="w-full max-w-md space-y-4 rounded-2xl border bg-white p-6 dark:border-neutral-800 dark:bg-neutral-900">
            <div class="text-center">
                <p class="text-sm text-neutral-500">Total bayar</p>
                <p class="text-3xl font-bold">{{ rupiah(total) }}</p>
            </div>

            <div class="grid grid-cols-4 gap-2 text-sm">
                <button
                    v-for="(label, key) in methodLabel" :key="key"
                    class="rounded-lg border py-2 dark:border-neutral-700"
                    :class="method === key ? 'bg-black text-white dark:bg-white dark:text-black' : ''"
                    @click="method = key as any"
                >{{ label }}</button>
            </div>

            <div v-if="method === 'cash'" class="space-y-3">
                <input v-model.number="cashInput" type="number" min="0" placeholder="Uang diterima" :class="field" class="w-full text-lg" autofocus />
                <div class="flex flex-wrap gap-2">
                    <button v-for="q in quick" :key="q" class="rounded-lg border px-3 py-1.5 text-sm dark:border-neutral-700" @click="cashInput = q">
                        {{ q === total ? 'Uang pas' : rupiah(q) }}
                    </button>
                </div>
                <div class="flex items-center justify-between text-lg">
                    <span class="text-neutral-500">Kembalian</span>
                    <span class="font-bold" :class="change < 0 ? 'text-red-500' : 'text-green-600'">{{ rupiah(Math.max(change, 0)) }}</span>
                </div>
            </div>
            <p v-else class="text-center text-sm text-neutral-500">
                Pastikan pembayaran {{ methodLabel[method] }} sebesar {{ rupiah(total) }} sudah diterima.
            </p>

            <p v-if="error" class="rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-600">{{ error }}</p>

            <div class="flex gap-3">
                <button
                    :disabled="!canPay || processing"
                    class="flex-1 rounded-xl bg-black py-3 font-semibold text-white disabled:opacity-40 dark:bg-white dark:text-black"
                    @click="checkout"
                >{{ processing ? 'Menyimpan...' : 'Selesaikan' }}</button>
                <button class="rounded-xl border px-5 py-3 dark:border-neutral-700" @click="payOpen = false">Batal</button>
            </div>
        </div>
    </div>

    <!-- Struk -->
    <div v-if="receipt" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/60 p-4" @click.self="closeReceipt">
        <div class="w-full max-w-sm space-y-4">
            <div id="receipt" class="mx-auto w-[302px] bg-white p-3 font-mono text-[12px] leading-snug text-black">
                <div class="text-center">
                    <p class="text-sm font-bold">{{ receipt.business }}</p>
                    <p>{{ receipt.outlet.name }}</p>
                    <p v-if="receipt.outlet.address">{{ receipt.outlet.address }}</p>
                    <p v-if="receipt.outlet.phone">{{ receipt.outlet.phone }}</p>
                </div>
                <hr class="my-2 border-dashed border-black" />
                <p>{{ receipt.order.number }}</p>
                <p>{{ receipt.order.date }} · {{ receipt.cashier }}</p>
                <hr class="my-2 border-dashed border-black" />
                <div v-for="(it, i) in receipt.order.items" :key="i" class="mb-1">
                    <p>{{ it.name }}</p>
                    <div class="flex justify-between">
                        <span>{{ it.qty }} {{ it.unit || '' }} x {{ rupiah(it.price) }}</span>
                        <span>{{ rupiah(it.subtotal) }}</span>
                    </div>
                </div>
                <hr class="my-2 border-dashed border-black" />
                <div class="flex justify-between"><span>Subtotal</span><span>{{ rupiah(receipt.order.subtotal) }}</span></div>
                <div v-if="receipt.order.discount" class="flex justify-between"><span>Diskon</span><span>-{{ rupiah(receipt.order.discount) }}</span></div>
                <div class="flex justify-between text-sm font-bold"><span>TOTAL</span><span>{{ rupiah(receipt.order.total) }}</span></div>
                <div v-for="(p, i) in receipt.order.payments" :key="i" class="flex justify-between">
                    <span>{{ methodLabel[p.method] }}</span><span>{{ rupiah(p.amount) }}</span>
                </div>
                <div class="flex justify-between"><span>Kembali</span><span>{{ rupiah(receipt.order.change) }}</span></div>
                <hr class="my-2 border-dashed border-black" />
                <p class="text-center">{{ receipt.outlet.footer || 'Terima kasih atas kunjungan Anda' }}</p>
            </div>

            <div class="flex justify-center gap-3">
                <button class="rounded-xl bg-white px-6 py-3 font-semibold text-black" @click="printReceipt">Cetak Struk</button>
                <button class="rounded-xl border border-white/40 px-6 py-3 font-semibold text-white" @click="closeReceipt">Transaksi Baru</button>
            </div>
        </div>
    </div>
</template>

<style>
@media print {
    body * { visibility: hidden !important; }
    #receipt, #receipt * { visibility: visible !important; }
    #receipt { position: absolute; left: 0; top: 0; width: 80mm; padding: 2mm; }
    @page { margin: 0; }
}
</style>