<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage<any>();
const list = computed<{ id: string; name: string; role: string }[]>(() => page.props.businesses ?? []);
const current = computed(() => page.props.business?.id);

const roleLabel: Record<string, string> = { owner: 'Pemilik', admin: 'Admin', kasir: 'Kasir', gudang: 'Gudang' };

const change = (e: Event) => {
    const id = (e.target as HTMLSelectElement).value;
    if (id !== current.value) router.post('/business/switch', { business_id: id });
};
</script>

<template>
    <select v-if="list.length > 1" :value="current" class="bs-select" aria-label="Pilih toko" @change="change">
        <option v-for="b in list" :key="b.id" :value="b.id">{{ b.name }} ({{ roleLabel[b.role] ?? b.role }})</option>
    </select>
</template>

<style>
.bs-select { cursor: pointer; max-width: 220px; height: 36px; padding: 0 10px; border: 1px solid #d4d4d8; border-radius: 10px; background: transparent; color: inherit; font-size: 13px; outline: none; }
.dark .bs-select { border-color: #3f3f46; background: #171717; color-scheme: dark; }
</style>