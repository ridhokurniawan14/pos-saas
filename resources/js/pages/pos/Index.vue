<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import MoneyInput from '@/components/MoneyInput.vue';
import ShiftGate from '@/components/ShiftGate.vue';
import ShiftPanel from '@/components/ShiftPanel.vue';
import ReceiptModal from '@/components/ReceiptModal.vue';

type Unit = { name: string; conversion: number; sell_price: number };
type Tier = { unit_name: string | null; min_qty: number; price: number };
type Product = {
    id: string; name: string; sku: string | null; barcode: string | null; unit: string;
    sell_price: number; category_id: string | null; image_url: string | null; units: Unit[]; tiers: Tier[];
    stock: number | null;
};
type Line = { product: Product; unit_name: string; qty: number };
type Bank = { id: string; bank_name: string; account_number: string; account_name: string };
type PayMethod = { key: string; label: string; image_url?: string; banks?: Bank[] };

const props = defineProps<{
    business_name: string;
    outlets: { id: string; name: string }[];
    categories: { id: string; name: string }[];
    products: Product[];
    payment_methods: PayMethod[];
    shift: any | null;
    allow_negative_stock: boolean;
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
const outletId = ref(props.shift?.outlet_id ?? '');
const panelOpen = ref(false);
const search = ref('');
const categoryId = ref<string | null>(null);
const cart = ref<Line[]>([]);
const discountType = ref<'rp' | 'percent'>('rp');
const discountValue = ref<number | null>(null);

const setDiscountType = (t: 'rp' | 'percent') => {
    discountType.value = t;
    discountValue.value = null;
};

watch(discountValue, (v) => {
    if (discountType.value === 'percent' && v && v > 100) discountValue.value = 100;
});
const searchEl = ref<HTMLInputElement | null>(null);
const cartOpen = ref(false);
const confirmClear = ref(false);

const payOpen = ref(false);
const method = ref('');
const bankId = ref('');
const cashInput = ref<number | null>(null);
const processing = ref(false);
const error = ref('');
const orderId = ref(uuid());
const receipt = ref<any>(null);
const copied = ref('');

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
const discountApplied = computed(() => {
    const v = discountValue.value || 0;
    const raw = discountType.value === 'percent' ? Math.round((subtotal.value * Math.min(v, 100)) / 100) : v;
    return Math.min(Math.max(raw, 0), subtotal.value);
});
const total = computed(() => subtotal.value - discountApplied.value);
const itemCount = computed(() => cart.value.reduce((s, l) => s + l.qty, 0));

const conv = (l: Line) => (l.unit_name ? (l.product.units.find((u) => u.name === l.unit_name)?.conversion ?? 1) : 1);
const usedBase = (productId: string) =>
    cart.value.filter((l) => l.product.id === productId).reduce((s, l) => s + l.qty * conv(l), 0);
const soldOut = (p: Product) => p.stock !== null && !props.allow_negative_stock && p.stock <= 0;
const overStock = (l: Line) =>
    l.product.stock !== null && !props.allow_negative_stock && usedBase(l.product.id) > l.product.stock;
const hasOver = computed(() => cart.value.some(overStock));

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
const askClear = () => {
    if (cart.value.length) confirmClear.value = true;
};
const doClear = () => {
    cart.value = [];
    discountValue.value = null;
    confirmClear.value = false;
    cartOpen.value = false;
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
const currentMethod = computed(() => props.payment_methods.find((m) => m.key === method.value));
const paid = computed(() => (method.value === 'cash' ? cashInput.value || 0 : total.value));
const change = computed(() => paid.value - total.value);
const canPay = computed(
    () =>
        total.value > 0 &&
        !!method.value &&
        paid.value >= total.value &&
        !readOnly.value &&
        (method.value !== 'transfer' || !!bankId.value),
);

const DENOMS = [5000, 10000, 20000, 50000, 75000, 100000];
const quick = computed(() => {
    const t = total.value;
    const extra = [Math.ceil(t / 50000) * 50000, Math.ceil(t / 100000) * 100000].filter(
        (v) => v > t && !DENOMS.includes(v),
    );
    const nominals = [...new Set([...DENOMS, ...extra])].sort((a, b) => a - b);
    return [{ label: 'Uang pas', value: t, enabled: t > 0 }, ...nominals.map((v) => ({ label: rupiah(v), value: v, enabled: v >= t }))];
});

watch(method, (m) => {
    if (m === 'transfer') bankId.value = currentMethod.value?.banks?.[0]?.id ?? '';
    error.value = '';
});

const openPay = () => {
    error.value = '';
    cashInput.value = null;
    method.value = props.payment_methods[0]?.key ?? '';
    payOpen.value = true;
};

const copy = async (text: string) => {
    try {
        await navigator.clipboard.writeText(text);
        copied.value = text;
        setTimeout(() => (copied.value = ''), 1500);
    } catch { /* abaikan */ }
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
                discount_type: discountType.value,
                discount_value: discountValue.value || 0,
                items: cart.value.map((l) => ({
                    product_id: l.product.id,
                    unit_name: l.unit_name || null,
                    qty: l.qty,
                })),
                payments: [
                    {
                        method: method.value,
                        amount: paid.value,
                        bank_account_id: method.value === 'transfer' ? bankId.value : null,
                    },
                ],
            }),
        });

        if (res.ok) {
            receipt.value = await res.json();
            cart.value = [];
            discountValue.value = null;
            payOpen.value = false;
            cartOpen.value = false;
            orderId.value = uuid();
            router.reload({ only: ['shift', 'products'] });
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
</script>

<template>
    <Head title="Kasir" />

    <div class="pos">
        <!-- Header -->
        <header class="pos-header">
            <div class="pos-row">
                <Link href="/dashboard" class="pos-btn">← Keluar</Link>
                <div>
                    <div class="pos-bold">{{ business_name }}</div>
                    <div class="pos-muted pos-xs">Kasir</div>
                </div>
            </div>
            <div v-if="shift" class="pos-row">
                <span class="pos-muted pos-xs">{{ shift.outlet_name }}</span>
                <button type="button" class="pos-btn" @click="panelOpen = true">Kas &amp; Tutup Kasir</button>
            </div>
        </header>

        <div v-if="readOnly" class="pos-alert">
            Masa trial/langganan habis. Transaksi baru dinonaktifkan. Upgrade paket untuk melanjutkan.
        </div>

        <div class="pos-body">
            <!-- Katalog -->
            <section class="pos-catalog">
                <input
                    ref="searchEl"
                    v-model="search"
                    @keyup.enter="onEnter"
                    type="search"
                    placeholder="Cari produk, atau scan barcode"
                    class="pos-input"
                />

                <div class="pos-cats">
                    <button class="pos-chip" :class="{ on: !categoryId }" @click="categoryId = null">Semua</button>
                    <button v-for="c in categories" :key="c.id" class="pos-chip" :class="{ on: categoryId === c.id }" @click="categoryId = c.id">
                        {{ c.name }}
                    </button>
                </div>

                <div class="pos-grid">
                    <button v-for="p in filtered" :key="p.id" :disabled="soldOut(p)" class="pos-card" @click="add(p)">
                        <div class="pos-thumb">
                            <img v-if="p.image_url" :src="p.image_url" :alt="p.name" loading="lazy" />
                            <span v-else>{{ p.name.charAt(0).toUpperCase() }}</span>
                        </div>
                        <div class="pos-card-body">
                            <div class="pos-name">{{ p.name }}</div>
                            <div class="pos-bold pos-price">{{ rupiah(p.sell_price) }}</div>
                            <div v-if="p.stock !== null" class="pos-xs" :class="p.stock <= 0 ? 'pos-danger-text' : 'pos-muted'">
                                {{ p.stock <= 0 ? 'Habis' : `Stok: ${p.stock}` }}
                            </div>
                            <div v-if="p.units.length" class="pos-muted pos-xs pos-ellipsis">+ {{ p.units.map((u) => u.name).join(', ') }}</div>
                        </div>
                    </button>
                    <p v-if="!filtered.length" class="pos-empty pos-span">Produk tidak ditemukan.</p>
                </div>
            </section>

            <!-- Keranjang -->
            <aside class="pos-cart" :class="{ open: cartOpen }">
                <div class="pos-cart-head">
                    <div class="pos-bold">Keranjang <span class="pos-muted pos-normal">({{ itemCount }})</span></div>
                    <div class="pos-row">
                        <button class="pos-link pos-danger-text" @click="askClear">Kosongkan</button>
                        <button class="pos-btn pos-mobile-only" @click="cartOpen = false">Tutup</button>
                    </div>
                </div>

                <div class="pos-lines">
                    <div v-for="(l, i) in cart" :key="i" class="pos-line">
                        <div class="pos-row pos-top">
                            <div class="pos-mini">
                                <img v-if="l.product.image_url" :src="l.product.image_url" alt="" />
                                <span v-else>{{ l.product.name.charAt(0).toUpperCase() }}</span>
                            </div>
                            <div class="pos-grow">
                                <div class="pos-between">
                                    <span class="pos-medium">{{ l.product.name }}</span>
                                    <span class="pos-bold">{{ rupiah(lineTotal(l)) }}</span>
                                </div>
                                <div class="pos-muted pos-xs">{{ rupiah(priceOf(l)) }} / {{ l.unit_name || l.product.unit }}</div>
                            </div>
                        </div>
                        <div class="pos-row pos-mt">
                            <button class="pos-sq" @click="dec(i)">−</button>
                            <input :value="l.qty" @change="setQty(i, +($event.target as HTMLInputElement).value)" type="number" min="1" class="pos-qty" />
                            <button class="pos-sq" @click="l.qty++">+</button>
                            <select v-if="l.product.units.length" v-model="l.unit_name" class="pos-input pos-auto pos-ml">
                                <option value="">{{ l.product.unit }}</option>
                                <option v-for="u in l.product.units" :key="u.name" :value="u.name">{{ u.name }}</option>
                            </select>
                        </div>
                        <p v-if="overStock(l)" class="pos-error" style="margin-top: 8px">
                            Stok tersisa {{ l.product.stock }} {{ l.product.unit }}. Kurangi jumlahnya.
                        </p>
                    </div>
                    <p v-if="!cart.length" class="pos-empty">Pilih produk untuk memulai.</p>
                </div>

                <div class="pos-summary">
                    <div class="pos-between"><span class="pos-muted">Subtotal</span><span>{{ rupiah(subtotal) }}</span></div>
                    <div class="pos-between">
                        <span class="pos-muted">Diskon</span>
                        <div class="pos-row">
                            <div class="pos-seg">
                                <button type="button" :class="{ on: discountType === 'rp' }" @click="setDiscountType('rp')">Rp</button>
                                <button type="button" :class="{ on: discountType === 'percent' }" @click="setDiscountType('percent')">%</button>
                            </div>
                            <MoneyInput v-if="discountType === 'rp'" v-model="discountValue" placeholder="0" class="pos-input pos-disc" />
                            <input v-else v-model.number="discountValue" type="number" min="0" max="100" placeholder="0" class="pos-input pos-disc" />
                        </div>
                    </div>
                    <div v-if="discountType === 'percent' && discountApplied" class="pos-between pos-xs pos-muted">
                        <span>Potongan</span><span>-{{ rupiah(discountApplied) }}</span>
                    </div>
                    <div class="pos-between pos-total"><span>Total</span><span>{{ rupiah(total) }}</span></div>
                    <button class="pos-primary" :disabled="!cart.length || readOnly || hasOver" @click="openPay">Bayar</button>
                </div>
            </aside>
        </div>

        <!-- Bar bawah (HP / tablet kecil) -->
        <button class="pos-mbar" @click="cartOpen = true">
            <span>🛒 {{ itemCount }} item</span>
            <span class="pos-bold">{{ rupiah(total) }} · Lihat keranjang</span>
        </button>

        <!-- Modal bayar -->
        <div v-if="payOpen" class="pos-overlay" @click.self="payOpen = false">
            <div class="pos-modal">
                <div class="pos-center">
                    <div class="pos-muted">Total bayar</div>
                    <div class="pos-big">{{ rupiah(total) }}</div>
                </div>

                <div v-if="!payment_methods.length" class="pos-notice">
                    Belum ada metode pembayaran aktif.
                    <Link href="/payment-settings" class="pos-underline">Atur di menu Pembayaran</Link>
                </div>

                <template v-else>
                    <div class="pos-tabs">
                        <button v-for="m in payment_methods" :key="m.key" class="pos-chip" :class="{ on: method === m.key }" @click="method = m.key">
                            {{ m.label }}
                        </button>
                    </div>

                    <!-- Tunai -->
                    <div v-if="method === 'cash'" class="pos-stack">
                        <MoneyInput v-model="cashInput" placeholder="Uang diterima" class="pos-input pos-lg" autofocus />
                        <div class="pos-quick">
                            <button v-for="q in quick" :key="q.label" class="pos-chip" :disabled="!q.enabled" @click="cashInput = q.value">
                                {{ q.label }}
                            </button>
                        </div>
                        <div class="pos-between pos-lg">
                            <span class="pos-muted">Kembalian</span>
                            <span class="pos-bold" :class="change < 0 ? 'pos-danger-text' : 'pos-ok-text'">{{ rupiah(Math.max(change, 0)) }}</span>
                        </div>
                    </div>

                    <!-- QRIS -->
                    <div v-else-if="method === 'qris'" class="pos-center pos-stack">
                        <img :src="currentMethod?.image_url" alt="QRIS" class="pos-qris" />
                        <div class="pos-muted">Minta pelanggan scan, pastikan {{ rupiah(total) }} sudah masuk.</div>
                    </div>

                    <!-- Transfer -->
                    <div v-else-if="method === 'transfer'" class="pos-stack">
                        <label v-for="b in currentMethod?.banks" :key="b.id" class="pos-bank" :class="{ on: bankId === b.id }">
                            <input v-model="bankId" type="radio" :value="b.id" />
                            <div class="pos-grow">
                                <div class="pos-medium">{{ b.bank_name }}</div>
                                <div class="pos-bold pos-mono">{{ b.account_number }}</div>
                                <div class="pos-muted pos-xs">a.n. {{ b.account_name }}</div>
                            </div>
                            <button type="button" class="pos-btn" @click.prevent="copy(b.account_number)">
                                {{ copied === b.account_number ? 'Tersalin' : 'Salin' }}
                            </button>
                        </label>
                        <div class="pos-muted pos-center">Pastikan transfer {{ rupiah(total) }} sudah masuk.</div>
                    </div>

                    <!-- Debit -->
                    <div v-else class="pos-muted pos-center">
                        Proses {{ rupiah(total) }} di mesin EDC, lalu klik Selesaikan.
                    </div>
                </template>

                <p v-if="error" class="pos-error">{{ error }}</p>

                <div class="pos-row">
                    <button class="pos-primary pos-grow" :disabled="!canPay || processing" @click="checkout">
                        {{ processing ? 'Menyimpan...' : 'Selesaikan' }}
                    </button>
                    <button class="pos-btn pos-btn-lg" @click="payOpen = false">Batal</button>
                </div>
            </div>
        </div>

        <!-- Struk -->
        <ReceiptModal v-if="receipt" :receipt="receipt" close-label="Transaksi Baru" @close="closeReceipt" />

        <ShiftGate v-if="!shift" :outlets="outlets" />
        <ShiftPanel v-if="shift && panelOpen" :shift="shift" @close="panelOpen = false" />

        <ConfirmDialog
            :open="confirmClear"
            title="Kosongkan keranjang?"
            message="Semua produk di keranjang akan dihapus."
            confirm-text="Ya, kosongkan"
            danger
            @confirm="doClear"
            @cancel="confirmClear = false"
        />
    </div>
</template>

<style>
.pos {
    --bg: #f4f4f5; --card: #ffffff; --text: #111111; --muted: #71717a; --border: #e4e4e7;
    --accent: #111111; --accent-text: #ffffff; --soft: #f4f4f5; --danger: #dc2626; --ok: #16a34a;
    height: 100svh; display: flex; flex-direction: column; background: var(--bg); color: var(--text); font-size: 14px;
}
.dark .pos {
    --bg: #0a0a0a; --card: #171717; --text: #fafafa; --muted: #a1a1aa; --border: #2e2e2e;
    --accent: #fafafa; --accent-text: #111111; --soft: #262626;
}
.pos *, .pos *::before, .pos *::after { box-sizing: border-box; }
:where(.pos) :is(button, select, input) { font: inherit; color: inherit; }
.pos button, .pos select, .pos label, .pos a { cursor: pointer; }
.pos button:disabled { cursor: not-allowed; opacity: 0.4; }

/* util */
.pos-row { display: flex; align-items: center; gap: 10px; }
.pos-top { align-items: flex-start; }
.pos-between { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.pos-stack { display: flex; flex-direction: column; gap: 12px; }
.pos-grow { flex: 1; min-width: 0; }
.pos-muted { color: var(--muted); }
.pos-xs { font-size: 12px; }
.pos-bold { font-weight: 700; }
.pos-medium { font-weight: 500; }
.pos-normal { font-weight: 400; }
.pos-center { text-align: center; }
.pos-justify { justify-content: center; }
.pos-mt { margin-top: 8px; }
.pos-ml { margin-left: auto; }
.pos-mono { font-family: ui-monospace, monospace; letter-spacing: 0.5px; }
.pos-ellipsis { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pos-danger-text { color: var(--danger); }
.pos-ok-text { color: var(--ok); }
.pos-underline { text-decoration: underline; font-weight: 600; }
.pos-span { grid-column: 1 / -1; }
.pos-empty { padding: 40px 0; text-align: center; color: var(--muted); }
.pos-lg { font-size: 18px; }

/* header */
.pos-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 16px; background: var(--card); border-bottom: 1px solid var(--border); }
.pos-alert { padding: 8px 16px; text-align: center; color: var(--danger); background: rgba(220, 38, 38, 0.1); }

/* controls */
.pos-btn { display: inline-flex; align-items: center; padding: 7px 14px; border: 1px solid var(--border); border-radius: 10px; background: transparent; text-decoration: none; }
.pos-btn:hover { background: var(--soft); }
.pos-btn-lg { padding: 12px 20px; }
.pos-link { background: none; border: 0; padding: 4px; }
.pos-input { width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 10px; background: var(--card); outline: none; }
.pos-input:focus { border-color: var(--text); }
.pos-auto { width: auto; padding: 7px 10px; }
.pos-disc { width: 130px; padding: 7px 10px; text-align: right; }
.pos-chip { flex-shrink: 0; padding: 7px 16px; border: 1px solid var(--border); border-radius: 999px; background: var(--card); }
.pos-chip:hover:not(:disabled) { border-color: var(--muted); }
.pos-chip.on { background: var(--accent); color: var(--accent-text); border-color: var(--accent); }
.pos-primary { padding: 14px; border: 0; border-radius: 12px; background: var(--accent); color: var(--accent-text); font-weight: 600; font-size: 16px; width: 100%; }
.pos-primary.pos-grow { width: auto; }
.pos-white { padding: 12px 24px; border: 0; border-radius: 12px; background: #fff; color: #000; font-weight: 600; }
.pos-ghost { padding: 12px 24px; border: 1px solid rgba(255, 255, 255, 0.4); border-radius: 12px; background: transparent; color: #fff; font-weight: 600; }

/* layout */
.pos-body { flex: 1; min-height: 0; display: grid; grid-template-columns: 1fr 380px; }
.pos-catalog { display: flex; flex-direction: column; gap: 12px; padding: 12px; min-height: 0; min-width: 0; }
.pos-cats { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px; flex-shrink: 0; }
.pos-grid { flex: 1; min-height: 0; overflow-y: auto; display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; align-content: start; }
.pos-card { display: block; padding: 0; overflow: hidden; text-align: left; border: 1px solid var(--border); border-radius: 12px; background: var(--card); transition: transform 0.08s, border-color 0.15s, box-shadow 0.15s; }
.pos-card:hover { border-color: var(--muted); box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08); }
.pos-card:active { transform: scale(0.97); }
.pos-thumb { aspect-ratio: 1 / 1; display: flex; align-items: center; justify-content: center; background: var(--soft); font-size: 36px; font-weight: 700; color: var(--muted); }
.pos-thumb img { width: 100%; height: 100%; object-fit: cover; }
.pos-card-body { padding: 10px; }
.pos-name { font-weight: 500; line-height: 1.25; min-height: 2.5em; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.pos-price { margin-top: 4px; }

/* keranjang */
.pos-cart { display: flex; flex-direction: column; min-height: 0; background: var(--card); border-left: 1px solid var(--border); }
.pos-cart-head { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; }
.pos-lines { flex: 1; min-height: 0; overflow-y: auto; padding: 0 16px 12px; display: flex; flex-direction: column; gap: 8px; }
.pos-line { padding: 12px; border: 1px solid var(--border); border-radius: 12px; }
.pos-mini { width: 44px; height: 44px; flex-shrink: 0; border-radius: 8px; overflow: hidden; background: var(--soft); display: flex; align-items: center; justify-content: center; font-weight: 700; color: var(--muted); }
.pos-mini img { width: 100%; height: 100%; object-fit: cover; }
.pos-sq { width: 36px; height: 36px; border: 1px solid var(--border); border-radius: 10px; background: transparent; font-size: 18px; }
.pos-sq:hover { background: var(--soft); }
.pos-qty { width: 60px; height: 36px; border: 1px solid var(--border); border-radius: 10px; background: transparent; text-align: center; }
.pos-summary { display: flex; flex-direction: column; gap: 10px; padding: 16px; border-top: 1px solid var(--border); }
.pos-total { font-size: 18px; font-weight: 700; }
.pos-mbar { display: none; }
.pos-mobile-only { display: none; }

/* modal */
.pos-overlay { position: fixed; inset: 0; z-index: 60; display: flex; align-items: center; justify-content: center; padding: 16px; background: rgba(0, 0, 0, 0.6); overflow-y: auto; }
.pos-modal { width: 100%; max-width: 460px; max-height: calc(100svh - 32px); overflow-y: auto; display: flex; flex-direction: column; gap: 14px; padding: 20px; border: 1px solid var(--border); border-radius: 16px; background: var(--card); }
.pos-big { font-size: 32px; font-weight: 800; }
.pos-tabs { display: flex; flex-wrap: wrap; gap: 8px; }
.pos-quick { display: flex; flex-wrap: wrap; gap: 8px; }
.pos-notice { padding: 14px; border-radius: 10px; background: rgba(245, 158, 11, 0.12); color: #b45309; text-align: center; }
.pos-qris { width: 100%; max-width: 280px; margin: 0 auto; border-radius: 12px; background: #fff; padding: 8px; }
.pos-bank { display: flex; align-items: center; gap: 12px; padding: 12px; border: 1px solid var(--border); border-radius: 12px; }
.pos-bank.on { border-color: var(--text); background: var(--soft); }
.pos-error { margin: 0; padding: 10px 12px; border-radius: 10px; background: rgba(220, 38, 38, 0.1); color: var(--danger); }

.pos-seg { display: flex; overflow: hidden; border: 1px solid var(--border); border-radius: 10px; }
.pos-seg button { padding: 7px 12px; border: 0; background: transparent; }
.pos-seg button.on { background: var(--accent); color: var(--accent-text); }

/* struk */
.pos-receipt-wrap { width: 100%; max-width: 360px; }
.pos-receipt { width: 302px; margin: 0 auto; padding: 12px; background: #fff; color: #000; font-family: ui-monospace, 'Courier New', monospace; font-size: 12px; line-height: 1.35; }
.rc-center { text-align: center; }
.rc-bold { font-weight: 700; }
.rc-hr { border: 0; border-top: 1px dashed #000; margin: 8px 0; }
.rc-row { display: flex; justify-content: space-between; gap: 8px; }
.rc-item { margin-bottom: 4px; }

/* responsif: HP & tablet kecil */
@media (max-width: 899px) {
    .pos-body { grid-template-columns: 1fr; }
    .pos-grid { padding-bottom: 76px; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); }
    .pos-cart { position: fixed; inset: 0; z-index: 40; border-left: 0; transform: translateY(100%); transition: transform 0.25s ease; }
    .pos-cart.open { transform: none; }
    .pos-mobile-only { display: inline-flex; }
    .pos-mbar {
        display: flex; align-items: center; justify-content: space-between; gap: 10px;
        position: fixed; left: 12px; right: 12px; bottom: calc(12px + env(safe-area-inset-bottom, 0px)); z-index: 30;
        padding: 14px 18px; border: 0; border-radius: 14px; background: var(--accent); color: var(--accent-text);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
    }
}

@media print {
    body * { visibility: hidden !important; }
    #receipt, #receipt * { visibility: visible !important; }
    .pos-overlay { position: static !important; overflow: visible !important; background: none !important; padding: 0 !important; }
    #receipt { position: absolute; left: 0; top: 0; width: 80mm; padding: 2mm; }
    @page { margin: 0; }
}
</style>