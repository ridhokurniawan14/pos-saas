<script setup lang="ts">
import { compressToDataUrl } from '@/lib/image';
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

type Outlet = { id: string; name: string; address: string | null; phone: string | null; receipt_header: string | null; receipt_footer: string | null };

const props = defineProps<{
    business: { name: string; allow_negative_stock: boolean; logo_url: string | null };
    outlets: Outlet[];
    can_custom: boolean;
}>();

const biz = useForm({ name: props.business.name, allow_negative_stock: props.business.allow_negative_stock });
const saveBiz = () => biz.put('/receipt-settings/business', { preserveScroll: true });

const forms = reactive<Record<string, any>>({});
props.outlets.forEach((o) => {
    forms[o.id] = useForm({
        name: o.name,
        address: o.address ?? '',
        phone: o.phone ?? '',
        receipt_header: o.receipt_header ?? '',
        receipt_footer: o.receipt_footer ?? '',
    });
});
const saveOutlet = (id: string) => forms[id].put(`/receipt-settings/outlets/${id}`, { preserveScroll: true });

const logoEl = ref<HTMLInputElement | null>(null);
const logoError = ref('');
const onLogo = async (e: Event) => {
    const input = e.target as HTMLInputElement;
    const f = input.files?.[0];
    if (!f) return;
    logoError.value = '';
    try {
        const url = await compressToDataUrl(f, 400, 'image/png');
        router.post('/receipt-settings/logo', { logo_data: url }, {
            preserveScroll: true,
            onError: (errs) => (logoError.value = Object.values(errs)[0] as string),
        });
    } catch {
        logoError.value = 'File bukan gambar yang valid.';
    }
    input.value = '';
};

const input =
    'w-full rounded-lg border bg-transparent px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-neutral-400 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white dark:[color-scheme:dark]';
</script>

<template>
    <Head title="Pengaturan Toko" />

    <div class="mx-auto w-full max-w-2xl space-y-5 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold">Pengaturan Toko</h1>
            <p class="text-sm text-neutral-500">Nama toko, aturan stok, dan tampilan struk.</p>
        </div>

        <!-- Toko -->
        <form class="space-y-4 rounded-xl border p-4 dark:border-neutral-800" @submit.prevent="saveBiz">
            <h2 class="font-semibold">Toko</h2>
            <div>
                <label class="mb-1.5 block text-sm font-medium">Nama toko (tampil di struk)</label>
                <input v-model="biz.name" :class="input" />
                <p v-if="biz.errors.name" class="mt-1 text-sm text-red-500">{{ biz.errors.name }}</p>
            </div>
            <label class="flex cursor-pointer items-start gap-3 text-sm">
                <input v-model="biz.allow_negative_stock" type="checkbox" class="mt-1" />
                <span>
                    <span class="font-medium">Izinkan penjualan melebihi stok</span>
                    <span class="block text-neutral-500">Nonaktif (disarankan): kasir tidak bisa menjual lebih dari stok yang tercatat.</span>
                </span>
            </label>
            <button type="submit" :disabled="biz.processing" class="rounded-lg bg-black px-5 py-2.5 text-sm font-medium text-white disabled:opacity-50 dark:bg-white dark:text-black">
                {{ biz.recentlySuccessful ? 'Tersimpan ✓' : 'Simpan' }}
            </button>
        </form>

        <!-- Logo -->
        <div class="space-y-3 rounded-xl border p-4 dark:border-neutral-800">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">Logo struk</h2>
                <span v-if="!can_custom" class="rounded-full bg-amber-500/15 px-3 py-1 text-xs font-medium text-amber-600">Paket Tumbuh ke atas</span>
            </div>
            <div v-if="can_custom" class="flex items-center gap-4">
                <button type="button" class="flex h-24 w-24 shrink-0 cursor-pointer items-center justify-center overflow-hidden rounded-xl border-2 border-dashed bg-white dark:border-neutral-700" @click="logoEl?.click()">
                    <img v-if="business.logo_url" :src="business.logo_url" class="h-full w-full object-contain" />
                    <span v-else class="px-2 text-center text-xs text-neutral-500">+ Upload logo</span>
                </button>
                <div class="space-y-2">
                    <div class="flex gap-2">
                        <button type="button" class="rounded-lg border px-4 py-2 text-sm font-medium hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800" @click="logoEl?.click()">
                            {{ business.logo_url ? 'Ganti logo' : 'Pilih logo' }}
                        </button>
                        <button v-if="business.logo_url" type="button" class="rounded-lg px-3 py-2 text-sm text-red-500 hover:bg-red-500/10" @click="router.delete('/receipt-settings/logo', { preserveScroll: true })">Hapus</button>
                    </div>
                    <p class="text-xs text-neutral-500">Pakai logo hitam putih berlatar putih supaya hasil cetak thermal tajam.</p>
                    <p v-if="logoError" class="text-xs text-red-500">{{ logoError }}</p>
                </div>
                <input ref="logoEl" type="file" accept="image/png,image/jpeg,image/webp" style="display: none" @change="onLogo" />
            </div>
            <p v-else class="text-sm text-neutral-500">Upgrade ke paket Tumbuh untuk menampilkan logo di struk.</p>
        </div>

        <!-- Cabang -->
        <form v-for="o in outlets" :key="o.id" class="space-y-4 rounded-xl border p-4 dark:border-neutral-800" @submit.prevent="saveOutlet(o.id)">
            <h2 class="font-semibold">Struk cabang: {{ o.name }}</h2>

            <div>
                <label class="mb-1.5 block text-sm font-medium">Nama cabang</label>
                <input v-model="forms[o.id].name" :class="input" />
                <p v-if="forms[o.id].errors.name" class="mt-1 text-sm text-red-500">{{ forms[o.id].errors.name }}</p>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium">Alamat</label>
                <textarea v-model="forms[o.id].address" rows="2" maxlength="200" :class="input" placeholder="Jl. Contoh No. 1, Banyuwangi" />
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium">Telepon / WhatsApp</label>
                <input v-model="forms[o.id].phone" :class="input" placeholder="0812xxxxxxx" />
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium">
                    Teks tambahan header <span v-if="!can_custom" class="text-xs font-normal text-amber-600">(Tumbuh ke atas)</span>
                </label>
                <textarea v-model="forms[o.id].receipt_header" rows="2" maxlength="300" :disabled="!can_custom" :class="input" placeholder="Mis. IG: @tokoku, NPWP, jam buka" />
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium">
                    Footer struk <span v-if="!can_custom" class="text-xs font-normal text-amber-600">(Tumbuh ke atas)</span>
                </label>
                <textarea v-model="forms[o.id].receipt_footer" rows="4" maxlength="500" :disabled="!can_custom" :class="input"
                    placeholder="Terima kasih atas kunjungan Anda&#10;Barang yang sudah dibeli tidak dapat ditukar/dikembalikan" />
                <p class="mt-1 text-xs text-neutral-500">Tekan Enter untuk baris baru. Kosongkan untuk memakai teks bawaan.</p>
            </div>

            <button type="submit" :disabled="forms[o.id].processing" class="rounded-lg bg-black px-5 py-2.5 text-sm font-medium text-white disabled:opacity-50 dark:bg-white dark:text-black">
                {{ forms[o.id].recentlySuccessful ? 'Tersimpan ✓' : 'Simpan' }}
            </button>
        </form>
    </div>
</template>