<script setup lang="ts">
import MoneyInput from '@/components/MoneyInput.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{ outlets: { id: string; name: string }[] }>();

const outletId = ref(props.outlets[0]?.id ?? '');
const cash = ref<number | null>(null);
const error = ref('');
const loading = ref(false);

const open = () => {
    error.value = '';
    loading.value = true;
    router.post(
        '/shifts',
        { outlet_id: outletId.value, opening_cash: cash.value ?? 0 },
        {
            preserveScroll: true,
            onError: (e) => (error.value = Object.values(e)[0] as string),
            onFinish: () => (loading.value = false),
        },
    );
};
</script>

<template>
    <Teleport to="body">
        <div class="sg-overlay">
            <div class="sg-box">
                <h2 class="sg-title">Buka Kasir</h2>
                <p class="sg-muted">Hitung uang di laci, lalu isi sebagai modal awal.</p>

                <template v-if="outlets.length">
                    <label v-if="outlets.length > 1" class="sg-label">
                        Cabang
                        <select v-model="outletId" class="sg-input">
                            <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
                        </select>
                    </label>
                    <p v-else class="sg-muted">Cabang: <strong>{{ outlets[0].name }}</strong></p>

                    <label class="sg-label">
                        Modal awal (Rp)
                        <MoneyInput v-model="cash" placeholder="0" class="sg-input" autofocus />
                    </label>

                    <p v-if="error" class="sg-error">{{ error }}</p>

                    <button type="button" class="sg-primary" :disabled="loading" @click="open">
                        {{ loading ? 'Membuka...' : 'Buka Kasir' }}
                    </button>
                </template>
                <p v-else class="sg-error">Belum ada cabang aktif.</p>

                <Link href="/dashboard" class="sg-back">← Kembali ke dashboard</Link>
            </div>
        </div>
    </Teleport>
</template>

<style>
.sg-overlay { position: fixed; inset: 0; z-index: 80; display: flex; align-items: center; justify-content: center; padding: 16px; background: #f4f4f5; }
.dark .sg-overlay { background: #0a0a0a; }
.sg-box { width: 100%; max-width: 400px; display: flex; flex-direction: column; gap: 14px; padding: 24px; border: 1px solid #e4e4e7; border-radius: 16px; background: #fff; color: #111; }
.dark .sg-box { background: #171717; color: #fafafa; border-color: #2e2e2e; }
.sg-title { margin: 0; font-size: 20px; font-weight: 700; }
.sg-muted { margin: 0; font-size: 14px; color: #71717a; }
.sg-label { display: flex; flex-direction: column; gap: 6px; font-size: 14px; font-weight: 500; }
.sg-input { width: 100%; padding: 11px 12px; border: 1px solid #d4d4d8; border-radius: 10px; background: transparent; color: inherit; font-size: 15px; outline: none; }
.dark .sg-input { border-color: #3f3f46; background: #171717; color-scheme: dark; }
.sg-input:focus { border-color: #111; }
.dark .sg-input:focus { border-color: #fafafa; }
.sg-primary { cursor: pointer; padding: 14px; border: 0; border-radius: 12px; background: #111; color: #fff; font-size: 16px; font-weight: 600; }
.dark .sg-primary { background: #fafafa; color: #111; }
.sg-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.sg-error { margin: 0; padding: 10px 12px; border-radius: 10px; background: rgba(220, 38, 38, 0.1); color: #dc2626; font-size: 14px; }
.sg-back { text-align: center; font-size: 14px; color: #71717a; text-decoration: none; cursor: pointer; }
</style>