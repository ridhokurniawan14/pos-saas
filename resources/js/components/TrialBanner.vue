<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage<any>();
const business = computed(() => page.props.business);
const isTrial = computed(() => business.value?.plan?.code === 'trial');
const endsAt = computed(() =>
    business.value?.ends_at
        ? new Date(business.value.ends_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
        : '-',
);
</script>

<template>
    <div
        v-if="business?.read_only"
        class="flex items-center justify-between gap-3 border-b border-red-500/30 bg-red-500/10 px-4 py-2.5 text-sm text-red-600 dark:text-red-400"
    >
        <span>
            <strong>Masa {{ isTrial ? 'trial' : 'langganan' }} habis.</strong>
            Data kamu aman, tapi hanya bisa dilihat. Upgrade untuk melanjutkan.
        </span>
        <a href="#" class="shrink-0 rounded-md bg-red-600 px-3 py-1 text-xs font-medium text-white">Pilih Paket</a>
    </div>

    <div
        v-else-if="isTrial"
        class="flex items-center justify-between gap-3 border-b border-amber-500/30 bg-amber-500/10 px-4 py-2.5 text-sm text-amber-700 dark:text-amber-400"
    >
        <span>
            Paket <strong>Free Trial</strong>: sisa <strong>{{ business?.days_left }} hari</strong>. Beberapa fitur dibatasi.
        </span>
        <a href="#" class="shrink-0 rounded-md bg-amber-600 px-3 py-1 text-xs font-medium text-white">Upgrade</a>
    </div>

    <div
        v-else-if="business"
        class="flex items-center justify-between gap-3 border-b bg-neutral-50 px-4 py-2 text-xs text-neutral-600 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-400"
    >
        <span>
            Paket <strong class="text-neutral-900 dark:text-white">{{ business?.plan?.name }}</strong>
            · aktif sampai {{ endsAt }}
        </span>
        <a v-if="business?.plan?.code !== 'sultan'" href="#" class="font-medium text-neutral-900 underline dark:text-white">Upgrade</a>
    </div>
</template>