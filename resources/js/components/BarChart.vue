<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    items: { label: string; value: number }[];
    height?: number;
    format?: (n: number) => string;
}>();

const max = computed(() => Math.max(1, ...props.items.map((i) => i.value)));
const step = computed(() => Math.max(1, Math.ceil(props.items.length / 12)));
const fmt = (n: number) => (props.format ? props.format(n) : String(n));
</script>

<template>
    <div>
        <p class="bc-max">{{ fmt(max) }}</p>
        <div class="bc-wrap" :style="{ height: (height ?? 160) + 'px' }">
            <div v-for="(i, k) in items" :key="k" class="bc-col" :title="`${i.label}: ${fmt(i.value)}`">
                <div class="bc-area">
                    <div class="bc-bar" :style="{ height: (i.value / max) * 100 + '%' }" />
                </div>
            </div>
        </div>
        <div class="bc-labels">
            <span v-for="(i, k) in items" :key="k" class="bc-label">{{ k % step === 0 ? i.label : '' }}</span>
        </div>
    </div>
</template>

<style>
.bc-max { margin: 0 0 4px; font-size: 11px; color: #71717a; }
.bc-wrap { display: flex; align-items: stretch; gap: 4px; border-bottom: 1px solid #d4d4d8; }
.dark .bc-wrap { border-color: #3f3f46; }
.bc-col { flex: 1; min-width: 0; display: flex; }
.bc-area { flex: 1; display: flex; align-items: flex-end; }
.bc-bar { width: 100%; min-height: 2px; border-radius: 4px 4px 0 0; background: #3b82f6; transition: opacity 0.15s; }
.bc-col:hover .bc-bar { opacity: 0.75; }
.bc-labels { display: flex; gap: 4px; margin-top: 4px; }
.bc-label { flex: 1; min-width: 0; overflow: visible; white-space: nowrap; text-align: center; font-size: 11px; color: #71717a; }
</style>