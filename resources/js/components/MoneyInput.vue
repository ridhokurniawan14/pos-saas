<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ modelValue: number | null | undefined }>();
const emit = defineEmits<{ 'update:modelValue': [value: number | null] }>();

const fmt = (n: number) => new Intl.NumberFormat('id-ID').format(n);
const display = computed(() => (props.modelValue === null || props.modelValue === undefined ? '' : fmt(props.modelValue)));

const onInput = (e: Event) => {
    const el = e.target as HTMLInputElement;
    const caret = el.selectionStart ?? el.value.length;
    const digitsBefore = el.value.slice(0, caret).replace(/\D/g, '').length;

    const digits = el.value.replace(/\D/g, '').slice(0, 11);
    const num = digits === '' ? null : Number(digits);
    const text = num === null ? '' : fmt(num);

    el.value = text;
    emit('update:modelValue', num);

    // kembalikan posisi kursor
    let pos = 0;
    let seen = 0;
    while (pos < text.length && seen < digitsBefore) {
        if (/\d/.test(text[pos])) seen++;
        pos++;
    }
    el.setSelectionRange(pos, pos);
};
</script>

<template>
    <input type="text" inputmode="numeric" autocomplete="off" :value="display" @input="onInput" />
</template>