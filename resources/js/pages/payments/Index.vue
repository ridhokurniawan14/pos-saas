<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { compressToDataUrl } from '@/lib/image';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

type Bank = { id: string; bank_name: string; account_number: string; account_name: string };

const props = defineProps<{
    settings: {
        cash_enabled: boolean;
        qris_enabled: boolean;
        debit_enabled: boolean;
        transfer_enabled: boolean;
        qris_image_url: string | null;
    };
    banks: Bank[];
}>();

const toggle = (key: string, value: boolean) =>
    router.put('/payment-settings', { [key]: value }, { preserveScroll: true });

const qrisEl = ref<HTMLInputElement | null>(null);
const qrisError = ref('');

const onQris = async (e: Event) => {
    const input = e.target as HTMLInputElement;
    const f = input.files?.[0];
    if (!f) return;
    qrisError.value = '';
    try {
        const url = await compressToDataUrl(f, 1000, 'image/png');
        router.post('/payment-settings/qris', { qris_image_data: url }, {
            preserveScroll: true,
            onError: (errs) => (qrisError.value = Object.values(errs)[0] as string),
        });
    } catch {
        qrisError.value = 'File bukan gambar yang valid.';
    }
    input.value = '';
};

const bankForm = useForm({ bank_name: '', account_number: '', account_name: '' });
const addBank = () => bankForm.post('/payment-settings/banks', { preserveScroll: true, onSuccess: () => bankForm.reset() });

const deleteTarget = ref<Bank | null>(null);
const removeBank = () => {
    if (!deleteTarget.value) return;
    router.delete(`/payment-settings/banks/${deleteTarget.value.id}`, { preserveScroll: true });
    deleteTarget.value = null;
};

const input =
    'w-full rounded-lg border bg-transparent px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-neutral-400 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white dark:[color-scheme:dark]';
</script>

<template>
    <Head title="Metode Pembayaran" />

    <div class="mx-auto w-full max-w-2xl space-y-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold">Metode Pembayaran</h1>
            <p class="text-sm text-neutral-500">Hanya metode yang aktif dan datanya lengkap yang tampil di kasir.</p>
        </div>

        <!-- Tunai -->
        <div class="flex items-center justify-between rounded-xl border p-4 dark:border-neutral-800">
            <div>
                <p class="font-medium">Tunai</p>
                <p class="text-sm text-neutral-500">Terima uang tunai dan hitung kembalian.</p>
            </div>
            <button type="button" role="switch" :aria-checked="settings.cash_enabled" @click="toggle('cash_enabled', !settings.cash_enabled)"
                :style="{ position: 'relative', width: '44px', height: '24px', borderRadius: '999px', border: 0, background: settings.cash_enabled ? '#16a34a' : '#a1a1aa', flexShrink: 0 }">
                <span :style="{ position: 'absolute', top: '2px', left: settings.cash_enabled ? '22px' : '2px', width: '20px', height: '20px', borderRadius: '999px', background: '#fff', transition: 'left .15s' }" />
            </button>
        </div>

        <!-- QRIS -->
        <div class="space-y-4 rounded-xl border p-4 dark:border-neutral-800">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-medium">QRIS</p>
                    <p class="text-sm text-neutral-500">Upload gambar QRIS toko kamu.</p>
                </div>
                <button type="button" role="switch" :aria-checked="settings.qris_enabled" @click="toggle('qris_enabled', !settings.qris_enabled)"
                    :style="{ position: 'relative', width: '44px', height: '24px', borderRadius: '999px', border: 0, background: settings.qris_enabled ? '#16a34a' : '#a1a1aa', flexShrink: 0 }">
                    <span :style="{ position: 'absolute', top: '2px', left: settings.qris_enabled ? '22px' : '2px', width: '20px', height: '20px', borderRadius: '999px', background: '#fff', transition: 'left .15s' }" />
                </button>
            </div>

            <div class="flex items-center gap-4">
                <button type="button" class="flex h-28 w-28 shrink-0 cursor-pointer items-center justify-center overflow-hidden rounded-xl border-2 border-dashed bg-white dark:border-neutral-700" @click="qrisEl?.click()">
                    <img v-if="settings.qris_image_url" :src="settings.qris_image_url" class="h-full w-full object-contain" />
                    <span v-else class="px-2 text-center text-xs text-neutral-500">+ Upload QRIS</span>
                </button>
                <div class="space-y-2">
                    <div class="flex gap-2">
                        <button type="button" class="cursor-pointer rounded-lg border px-4 py-2 text-sm font-medium hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800" @click="qrisEl?.click()">
                            {{ settings.qris_image_url ? 'Ganti gambar' : 'Pilih gambar' }}
                        </button>
                        <button v-if="settings.qris_image_url" type="button" class="cursor-pointer rounded-lg px-3 py-2 text-sm text-red-500 hover:bg-red-500/10" @click="router.delete('/payment-settings/qris', { preserveScroll: true })">
                            Hapus
                        </button>
                    </div>
                    <p v-if="settings.qris_enabled && !settings.qris_image_url" class="text-xs text-amber-600">Belum ada gambar, QRIS belum tampil di kasir.</p>
                    <p v-if="qrisError" class="text-xs text-red-500">{{ qrisError }}</p>
                </div>
                <input ref="qrisEl" type="file" accept="image/png,image/jpeg,image/webp" style="display: none" @change="onQris" />
            </div>
        </div>

        <!-- Debit -->
        <div class="flex items-center justify-between rounded-xl border p-4 dark:border-neutral-800">
            <div>
                <p class="font-medium">Kartu Debit / EDC</p>
                <p class="text-sm text-neutral-500">Aktifkan jika toko punya mesin EDC.</p>
            </div>
            <button type="button" role="switch" :aria-checked="settings.debit_enabled" @click="toggle('debit_enabled', !settings.debit_enabled)"
                :style="{ position: 'relative', width: '44px', height: '24px', borderRadius: '999px', border: 0, background: settings.debit_enabled ? '#16a34a' : '#a1a1aa', flexShrink: 0 }">
                <span :style="{ position: 'absolute', top: '2px', left: settings.debit_enabled ? '22px' : '2px', width: '20px', height: '20px', borderRadius: '999px', background: '#fff', transition: 'left .15s' }" />
            </button>
        </div>

        <!-- Transfer -->
        <div class="space-y-4 rounded-xl border p-4 dark:border-neutral-800">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-medium">Transfer Bank</p>
                    <p class="text-sm text-neutral-500">Bisa lebih dari satu rekening.</p>
                </div>
                <button type="button" role="switch" :aria-checked="settings.transfer_enabled" @click="toggle('transfer_enabled', !settings.transfer_enabled)"
                    :style="{ position: 'relative', width: '44px', height: '24px', borderRadius: '999px', border: 0, background: settings.transfer_enabled ? '#16a34a' : '#a1a1aa', flexShrink: 0 }">
                    <span :style="{ position: 'absolute', top: '2px', left: settings.transfer_enabled ? '22px' : '2px', width: '20px', height: '20px', borderRadius: '999px', background: '#fff', transition: 'left .15s' }" />
                </button>
            </div>

            <p v-if="settings.transfer_enabled && !banks.length" class="text-xs text-amber-600">Belum ada rekening, Transfer belum tampil di kasir.</p>

            <div v-if="banks.length" class="divide-y rounded-lg border dark:divide-neutral-800 dark:border-neutral-800">
                <div v-for="b in banks" :key="b.id" class="flex items-center justify-between gap-3 px-3 py-2.5">
                    <div>
                        <p class="text-sm font-medium">{{ b.bank_name }} · {{ b.account_number }}</p>
                        <p class="text-xs text-neutral-500">a.n. {{ b.account_name }}</p>
                    </div>
                    <button type="button" class="cursor-pointer text-sm text-red-500" @click="deleteTarget = b">Hapus</button>
                </div>
            </div>

            <form class="space-y-3" @submit.prevent="addBank">
                <p class="text-sm font-medium">Tambah rekening</p>
                <div>
                    <input v-model="bankForm.bank_name" list="bank-list" placeholder="Nama bank (mis. BCA)" :class="input" />
                    <datalist id="bank-list">
                        <option v-for="n in ['BCA','BRI','BNI','Mandiri','BSI','CIMB Niaga','Permata','Danamon','Jago','SeaBank','BTN','DANA','OVO','GoPay']" :key="n" :value="n" />
                    </datalist>
                    <p v-if="bankForm.errors.bank_name" class="mt-1 text-sm text-red-500">{{ bankForm.errors.bank_name }}</p>
                </div>
                <div>
                    <input v-model="bankForm.account_number" inputmode="numeric" placeholder="Nomor rekening" :class="input" />
                    <p v-if="bankForm.errors.account_number" class="mt-1 text-sm text-red-500">{{ bankForm.errors.account_number }}</p>
                </div>
                <div>
                    <input v-model="bankForm.account_name" placeholder="Atas nama" :class="input" />
                    <p v-if="bankForm.errors.account_name" class="mt-1 text-sm text-red-500">{{ bankForm.errors.account_name }}</p>
                </div>
                <button type="submit" :disabled="bankForm.processing" class="rounded-lg bg-black px-5 py-2.5 text-sm font-medium text-white disabled:opacity-50 dark:bg-white dark:text-black">
                    Simpan rekening
                </button>
            </form>
        </div>
    </div>

    <ConfirmDialog
        :open="!!deleteTarget"
        title="Hapus rekening ini?"
        :message="deleteTarget ? `${deleteTarget.bank_name} · ${deleteTarget.account_number}` : ''"
        confirm-text="Ya, hapus"
        danger
        @confirm="removeBank"
        @cancel="deleteTarget = null"
    />
</template>