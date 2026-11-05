<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Pelanggan', href: '/customers' }] } });

type Customer = { id: string; name: string; phone: string | null; address: string | null; orders_count: number; total_spent: number; debt: number; points: number; };

const props = defineProps<{
    customers: { data: Customer[]; links: { url: string | null; label: string; active: boolean }[] };
    filters: { search?: string | null };
    manager: boolean;
    can: { receivable: boolean; loyalty: boolean };
}>();

const rupiah = (n: number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n || 0);

const search = ref(props.filters.search ?? '');
let timer: number | undefined;
watch(search, () => {
    clearTimeout(timer);
    timer = window.setTimeout(
        () => router.get('/customers', { search: search.value || undefined }, { preserveState: true, preserveScroll: true, replace: true }),
        300,
    );
});
onBeforeUnmount(() => clearTimeout(timer));

const form = useForm({ name: '', phone: '', address: '' });
const add = () => form.post('/customers', { preserveScroll: true, onSuccess: () => form.reset() });

const editing = ref<string | null>(null);
const edit = useForm({ name: '', phone: '', address: '' });
const startEdit = (c: Customer) => {
    editing.value = c.id;
    edit.clearErrors();
    edit.name = c.name;
    edit.phone = c.phone ?? '';
    edit.address = c.address ?? '';
};
const save = (id: string) => edit.put(`/customers/${id}`, { preserveScroll: true, onSuccess: () => (editing.value = null) });

const target = ref<Customer | null>(null);
const remove = () => {
    if (!target.value) return;
    router.delete(`/customers/${target.value.id}`, { preserveScroll: true });
    target.value = null;
};

const input =
    'w-full rounded-lg border bg-transparent px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-neutral-400 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white dark:[color-scheme:dark]';
</script>

<template>
    <Head title="Pelanggan" />

    <div class="mx-auto w-full max-w-3xl space-y-5 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold">Pelanggan</h1>
            <p class="text-sm text-neutral-500">Data pelanggan tetap dan riwayat belanjanya.</p>
        </div>

        <form class="space-y-3 rounded-xl border p-4 dark:border-neutral-800" @submit.prevent="add">
            <p class="text-sm font-medium">Tambah pelanggan</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <input v-model="form.name" placeholder="Nama pelanggan" :class="input" />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-500">{{ form.errors.name }}</p>
                </div>
                <div>
                    <input v-model="form.phone" inputmode="tel" placeholder="Telepon / WhatsApp (opsional)" :class="input" />
                    <p v-if="form.errors.phone" class="mt-1 text-sm text-red-500">{{ form.errors.phone }}</p>
                </div>
            </div>
            <input v-model="form.address" placeholder="Alamat (opsional)" :class="input" />
            <div class="flex justify-end">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-black px-5 py-2.5 text-sm font-medium text-white disabled:opacity-50 dark:bg-white dark:text-black">Simpan</button>
            </div>
        </form>

        <input v-model="search" type="search" placeholder="Cari nama atau telepon" :class="input" />

        <div class="divide-y rounded-xl border dark:divide-neutral-800 dark:border-neutral-800">
            <div v-for="c in customers.data" :key="c.id" class="px-4 py-3">
                <div v-if="editing === c.id" class="space-y-2">
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div>
                            <input v-model="edit.name" placeholder="Nama" :class="input" />
                            <p v-if="edit.errors.name" class="mt-1 text-sm text-red-500">{{ edit.errors.name }}</p>
                        </div>
                        <div>
                            <input v-model="edit.phone" inputmode="tel" placeholder="Telepon" :class="input" />
                            <p v-if="edit.errors.phone" class="mt-1 text-sm text-red-500">{{ edit.errors.phone }}</p>
                        </div>
                    </div>
                    <input v-model="edit.address" placeholder="Alamat" :class="input" />
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" class="rounded-lg border px-4 py-2 text-sm font-medium hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800" @click="editing = null">Batal</button>
                        <button type="button" :disabled="edit.processing" class="rounded-lg bg-black px-4 py-2 text-sm font-medium text-white disabled:opacity-50 dark:bg-white dark:text-black" @click="save(c.id)">Simpan</button>
                    </div>
                </div>

                <div v-else class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium">{{ c.name }}</p>
                        <p class="text-xs text-neutral-500">{{ [c.phone, c.address].filter(Boolean).join(' · ') || '-' }}</p>
                        <p class="text-xs text-neutral-500">{{ c.orders_count }} transaksi · total belanja {{ rupiah(c.total_spent) }}</p>
                        <p v-if="can.receivable && c.debt > 0" class="text-xs font-medium text-red-500">Piutang {{ rupiah(c.debt) }}</p>
                        <p v-if="can.loyalty" class="text-xs text-neutral-500">{{ c.points }} poin</p>
                    </div>
                    <div class="flex gap-3 text-sm">
                        <Link :href="`/customers/${c.id}`" class="font-medium">Riwayat</Link>
                        <button class="text-neutral-500 hover:text-neutral-900 dark:hover:text-white" @click="startEdit(c)">Edit</button>
                        <button v-if="manager" class="text-red-500" @click="target = c">Hapus</button>
                    </div>
                </div>
            </div>
            <p v-if="!customers.data.length" class="px-4 py-8 text-center text-sm text-neutral-500">
                {{ search ? 'Pelanggan tidak ditemukan.' : 'Belum ada pelanggan.' }}
            </p>
        </div>

        <div v-if="customers.links.length > 3" class="flex flex-wrap gap-1">
            <template v-for="(l, i) in customers.links" :key="i">
                <Link v-if="l.url" :href="l.url" preserve-state class="rounded-md border px-3 py-1.5 text-sm dark:border-neutral-700"
                    :class="l.active ? 'bg-black text-white dark:bg-white dark:text-black' : ''" v-html="l.label" />
                <span v-else class="px-3 py-1.5 text-sm text-neutral-400" v-html="l.label" />
            </template>
        </div>
    </div>

    <ConfirmDialog
        :open="!!target"
        title="Hapus pelanggan ini?"
        message="Riwayat transaksinya tetap tersimpan."
        confirm-text="Ya, hapus"
        danger
        @confirm="remove"
        @cancel="target = null"
    />
</template>