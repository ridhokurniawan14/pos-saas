<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Supplier', href: '/suppliers' }] } });

type Supplier = { id: string; name: string; phone: string | null; address: string | null; purchases_count: number };
defineProps<{ suppliers: Supplier[] }>();

const form = useForm({ name: '', phone: '', address: '' });
const editing = ref<string | null>(null);
const edit = useForm({ name: '', phone: '', address: '' });
const target = ref<Supplier | null>(null);

const add = () => form.post('/suppliers', { preserveScroll: true, onSuccess: () => form.reset() });
const startEdit = (s: Supplier) => {
    editing.value = s.id;
    edit.name = s.name;
    edit.phone = s.phone ?? '';
    edit.address = s.address ?? '';
};
const save = (id: string) => edit.put(`/suppliers/${id}`, { preserveScroll: true, onSuccess: () => (editing.value = null) });
const remove = () => {
    if (!target.value) return;
    router.delete(`/suppliers/${target.value.id}`, { preserveScroll: true });
    target.value = null;
};

const input =
    'w-full rounded-lg border bg-transparent px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-neutral-400 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white dark:[color-scheme:dark]';
</script>

<template>
    <Head title="Supplier" />

    <div class="mx-auto w-full max-w-2xl space-y-5 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold">Supplier</h1>
            <p class="text-sm text-neutral-500">Tempat kamu membeli barang dagangan.</p>
        </div>

        <form class="space-y-3 rounded-xl border p-4 dark:border-neutral-800" @submit.prevent="add">
            <p class="text-sm font-medium">Tambah supplier</p>
            <input v-model="form.name" placeholder="Nama supplier" :class="input" />
            <p v-if="form.errors.name" class="text-sm text-red-500">{{ form.errors.name }}</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <input v-model="form.phone" placeholder="Telepon (opsional)" :class="input" />
                <input v-model="form.address" placeholder="Alamat (opsional)" :class="input" />
            </div>
            <button type="submit" :disabled="form.processing" class="rounded-lg bg-black px-5 py-2.5 text-sm font-medium text-white disabled:opacity-50 dark:bg-white dark:text-black">Simpan</button>
        </form>

        <div class="divide-y rounded-xl border dark:divide-neutral-800 dark:border-neutral-800">
            <div v-for="s in suppliers" :key="s.id" class="px-4 py-3">
                <div v-if="editing === s.id" class="space-y-2">
                    <input v-model="edit.name" :class="input" />
                    <div class="grid gap-2 sm:grid-cols-2">
                        <input v-model="edit.phone" placeholder="Telepon" :class="input" />
                        <input v-model="edit.address" placeholder="Alamat" :class="input" />
                    </div>
                    <p v-if="edit.errors.name" class="text-sm text-red-500">{{ edit.errors.name }}</p>
                    <div class="flex gap-3 text-sm">
                        <button class="font-medium" @click="save(s.id)">Simpan</button>
                        <button class="text-neutral-500" @click="editing = null">Batal</button>
                    </div>
                </div>
                <div v-else class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium">{{ s.name }}</p>
                        <p class="text-xs text-neutral-500">{{ [s.phone, s.address].filter(Boolean).join(' · ') || '-' }} · {{ s.purchases_count }} pembelian</p>
                    </div>
                    <div class="flex gap-3 text-sm">
                        <button class="text-neutral-500 hover:text-neutral-900 dark:hover:text-white" @click="startEdit(s)">Edit</button>
                        <button class="text-red-500" @click="target = s">Hapus</button>
                    </div>
                </div>
            </div>
            <p v-if="!suppliers.length" class="px-4 py-8 text-center text-sm text-neutral-500">Belum ada supplier.</p>
        </div>
    </div>

    <ConfirmDialog
        :open="!!target"
        title="Hapus supplier ini?"
        message="Riwayat pembelian lama tetap tersimpan."
        confirm-text="Ya, hapus"
        danger
        @confirm="remove"
        @cancel="target = null"
    />
</template>