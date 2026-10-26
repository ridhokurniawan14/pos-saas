<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{ categories: { id: string; name: string; products_count: number }[] }>();

const form = useForm({ name: '' });
const editingId = ref<string | null>(null);
const editName = ref('');

const add = () => form.post('/categories', { preserveScroll: true, onSuccess: () => form.reset() });

const startEdit = (c: { id: string; name: string }) => {
    editingId.value = c.id;
    editName.value = c.name;
};

const saveEdit = (id: string) =>
    router.put(`/categories/${id}`, { name: editName.value }, {
        preserveScroll: true,
        onSuccess: () => (editingId.value = null),
    });

const remove = (id: string) => {
    if (confirm('Hapus kategori ini? Produk di dalamnya tidak ikut terhapus.')) {
        router.delete(`/categories/${id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Kategori" />

    <div class="mx-auto w-full max-w-2xl space-y-6 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold">Kategori</h1>
            <p class="text-sm text-neutral-500">Kelompokkan produk agar kasir lebih cepat mencari.</p>
        </div>

        <form @submit.prevent="add" class="flex gap-2">
            <input
                v-model="form.name"
                type="text"
                placeholder="Nama kategori baru"
                class="flex-1 rounded-lg border bg-transparent px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-neutral-400 dark:border-neutral-700"
            />
            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-lg bg-black px-4 py-2.5 text-sm font-medium text-white disabled:opacity-50 dark:bg-white dark:text-black"
            >
                Tambah
            </button>
        </form>
        <p v-if="form.errors.name" class="text-sm text-red-500">{{ form.errors.name }}</p>

        <div class="divide-y rounded-xl border dark:divide-neutral-800 dark:border-neutral-800">
            <div v-for="c in categories" :key="c.id" class="flex items-center justify-between gap-3 px-4 py-3">
                <template v-if="editingId === c.id">
                    <input
                        v-model="editName"
                        @keyup.enter="saveEdit(c.id)"
                        class="flex-1 rounded-md border bg-transparent px-2 py-1.5 text-sm dark:border-neutral-700"
                    />
                    <button class="text-sm font-medium" @click="saveEdit(c.id)">Simpan</button>
                    <button class="text-sm text-neutral-500" @click="editingId = null">Batal</button>
                </template>
                <template v-else>
                    <div>
                        <p class="text-sm font-medium">{{ c.name }}</p>
                        <p class="text-xs text-neutral-500">{{ c.products_count }} produk</p>
                    </div>
                    <div class="flex gap-3 text-sm">
                        <button class="text-neutral-500 hover:text-neutral-900 dark:hover:text-white" @click="startEdit(c)">Edit</button>
                        <button class="text-red-500" @click="remove(c.id)">Hapus</button>
                    </div>
                </template>
            </div>
            <p v-if="!categories.length" class="px-4 py-8 text-center text-sm text-neutral-500">Belum ada kategori.</p>
        </div>
    </div>
</template>