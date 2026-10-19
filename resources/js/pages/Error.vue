<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ status: number; message?: string }>();

const content = computed(() => {
    if (props.status === 403) {
        return {
            title: 'Akses dibatasi',
            text: props.message || 'Kamu tidak punya akses ke halaman ini.',
        };
    }
    return {
        title: 'Halaman tidak ditemukan',
        text: 'Halaman yang kamu cari tidak ada atau sudah dipindahkan.',
    };
});
</script>

<template>
    <Head :title="content.title" />

    <div class="flex min-h-svh items-center justify-center bg-neutral-50 p-4 dark:bg-neutral-950">
        <div class="w-full max-w-md rounded-2xl border bg-white p-8 text-center shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
            <p class="text-5xl font-bold text-neutral-300 dark:text-neutral-700">{{ status }}</p>
            <h1 class="mt-3 text-xl font-semibold">{{ content.title }}</h1>
            <p class="mt-2 text-sm text-neutral-500">{{ content.text }}</p>
            <Link
                href="/dashboard"
                class="mt-6 inline-block rounded-lg bg-black px-5 py-2.5 text-sm font-medium text-white hover:opacity-90 dark:bg-white dark:text-black"
            >
                Kembali ke Dashboard
            </Link>
        </div>
    </div>
</template>