<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Lock } from '@lucide/vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Fitur Terkunci', href: '#' }] } });
defineProps<{ feature: string; plan: string }>();
</script>

<template>
    <Head :title="feature" />

    <div class="mx-auto flex w-full max-w-md flex-col items-center gap-4 p-6 pt-16 text-center">
        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-500/15 text-amber-600">
            <Lock :size="28" />
        </div>
        <h1 class="text-xl font-semibold">{{ feature }} belum tersedia di paket kamu</h1>
        <p class="text-sm text-neutral-500">
            Upgrade ke paket <strong>{{ plan }}</strong> atau di atasnya untuk memakai fitur ini.
        </p>
        <div class="flex gap-3">
            <a href="#" class="rounded-lg bg-black px-5 py-2.5 text-sm font-medium text-white dark:bg-white dark:text-black">Lihat Paket</a>
            <Link href="/dashboard" class="rounded-lg border px-5 py-2.5 text-sm font-medium dark:border-neutral-700">Kembali</Link>
        </div>
    </div>
</template>