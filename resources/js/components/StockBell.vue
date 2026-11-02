<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const page = usePage<any>();
const canStock = computed(() => (page.props.business?.features ?? []).includes('stock_management'));
const count = computed(() => page.props.low_stock?.count ?? 0);
const items = computed(() => page.props.low_stock?.items ?? []);
const open = ref(false);
const root = ref<HTMLElement | null>(null);

const refresh = () => {
    if (canStock.value) router.reload({ only: ['low_stock'], preserveScroll: true, preserveState: true });
};

const onDoc = (e: MouseEvent) => {
    if (root.value && !root.value.contains(e.target as Node)) open.value = false;
};

let timer: number | undefined;
onMounted(() => {
    document.addEventListener('click', onDoc);
    timer = window.setInterval(refresh, 60000); // segarkan tiap 1 menit
});
onBeforeUnmount(() => {
    document.removeEventListener('click', onDoc);
    clearInterval(timer);
});
</script>

<template>
    <div v-if="canStock" ref="root" class="sb-root">
        <button type="button" class="sb-btn" aria-label="Notifikasi stok" @click="open = !open">
            <Bell :size="18" />
            <span v-if="count" class="sb-badge">{{ count >= 50 ? '50+' : count }}</span>
        </button>

        <div v-if="open" class="sb-menu">
            <div class="sb-head">Stok menipis</div>
            <p v-if="!items.length" class="sb-empty">Semua stok aman 👍</p>
            <Link v-for="i in items" :key="i.id + i.outlet" href="/stock" class="sb-item" @click="open = false">
                <span class="sb-name">{{ i.name }}<small>{{ i.outlet }}</small></span>
                <span :class="i.qty <= 0 ? 'sb-bad' : 'sb-warn'">{{ i.qty <= 0 ? 'Habis' : `${i.qty} ${i.unit}` }}</span>
            </Link>
            <div v-if="items.length" class="sb-foot">
                <Link href="/stock" @click="open = false">Lihat stok</Link>
                <Link href="/purchases/create" @click="open = false">Buat pembelian</Link>
            </div>
        </div>
    </div>
</template>

<style>
.sb-root { position: relative; }
.sb-btn { position: relative; cursor: pointer; display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border: 1px solid #e4e4e7; border-radius: 10px; background: transparent; color: inherit; }
.dark .sb-btn { border-color: #3f3f46; }
.sb-btn:hover { background: rgba(127, 127, 127, 0.15); }
.sb-badge { position: absolute; top: -6px; right: -6px; min-width: 18px; padding: 1px 5px; border-radius: 999px; background: #dc2626; color: #fff; font-size: 11px; font-weight: 700; line-height: 16px; text-align: center; }
.sb-menu { position: absolute; right: 0; top: 44px; z-index: 50; width: 320px; max-width: calc(100vw - 24px); overflow: hidden; border: 1px solid #e4e4e7; border-radius: 12px; background: #fff; color: #111; box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2); font-size: 14px; }
.dark .sb-menu { background: #171717; color: #fafafa; border-color: #2e2e2e; }
.sb-head { padding: 12px 14px; font-weight: 600; border-bottom: 1px solid #e4e4e7; }
.dark .sb-head { border-color: #2e2e2e; }
.sb-empty { margin: 0; padding: 18px 14px; text-align: center; color: #71717a; }
.sb-item { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 14px; text-decoration: none; color: inherit; cursor: pointer; }
.sb-item:hover { background: rgba(127, 127, 127, 0.12); }
.sb-name { display: flex; flex-direction: column; min-width: 0; }
.sb-name small { color: #71717a; font-size: 12px; }
.sb-warn { color: #d97706; font-weight: 600; white-space: nowrap; }
.sb-bad { color: #dc2626; font-weight: 600; white-space: nowrap; }
.sb-foot { display: flex; justify-content: space-between; padding: 10px 14px; border-top: 1px solid #e4e4e7; font-weight: 500; }
.dark .sb-foot { border-color: #2e2e2e; }
.sb-foot a { cursor: pointer; text-decoration: underline; color: inherit; }
</style>