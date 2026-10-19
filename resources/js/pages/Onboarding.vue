<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    business_name: '',
    outlet_name: 'Cabang Utama',
});

const submit = () => form.post('/onboarding');
</script>

<template>
    <Head title="Atur Bisnis" />

    <div class="flex min-h-svh items-center justify-center bg-neutral-50 p-4 dark:bg-neutral-950">
        <div class="w-full max-w-md rounded-2xl border bg-white p-8 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
            <div class="mb-6 text-center">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-black text-lg font-bold text-white dark:bg-white dark:text-black">
                    P
                </div>
                <h1 class="text-xl font-semibold">Atur bisnis kamu</h1>
                <p class="mt-1 text-sm text-neutral-500">
                    Trial 14 hari sudah aktif. Isi data awal ini dulu.
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Nama bisnis</label>
                    <input
                        v-model="form.business_name"
                        type="text"
                        placeholder="Contoh: Kopi Senja"
                        class="w-full rounded-lg border bg-transparent px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-neutral-400 dark:border-neutral-700"
                    />
                    <p v-if="form.errors.business_name" class="mt-1 text-sm text-red-500">
                        {{ form.errors.business_name }}
                    </p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Nama cabang utama</label>
                    <input
                        v-model="form.outlet_name"
                        type="text"
                        class="w-full rounded-lg border bg-transparent px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-neutral-400 dark:border-neutral-700"
                    />
                    <p v-if="form.errors.outlet_name" class="mt-1 text-sm text-red-500">
                        {{ form.errors.outlet_name }}
                    </p>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-lg bg-black px-4 py-2.5 text-sm font-medium text-white transition hover:opacity-90 disabled:opacity-50 dark:bg-white dark:text-black"
                >
                    {{ form.processing ? 'Menyimpan...' : 'Mulai Gunakan' }}
                </button>
            </form>
        </div>
    </div>
</template>