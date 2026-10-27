<script setup lang="ts">
defineProps<{
    open: boolean;
    title: string;
    message?: string;
    confirmText?: string;
    cancelText?: string;
    danger?: boolean;
}>();
defineEmits<{ confirm: []; cancel: [] }>();
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="cd-overlay" @click.self="$emit('cancel')">
            <div class="cd-box" role="dialog" aria-modal="true">
                <h3 class="cd-title">{{ title }}</h3>
                <p v-if="message" class="cd-msg">{{ message }}</p>
                <div class="cd-actions">
                    <button type="button" class="cd-btn cd-cancel" @click="$emit('cancel')">{{ cancelText || 'Batal' }}</button>
                    <button type="button" class="cd-btn cd-ok" :class="{ 'cd-danger': danger }" @click="$emit('confirm')">
                        {{ confirmText || 'Ya, lanjutkan' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style>
.cd-overlay { position: fixed; inset: 0; z-index: 100; display: flex; align-items: center; justify-content: center; padding: 16px; background: rgba(0, 0, 0, 0.6); }
.cd-box { width: 100%; max-width: 380px; padding: 24px; border-radius: 16px; background: #ffffff; color: #111111; border: 1px solid #e4e4e7; box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3); }
.dark .cd-box { background: #171717; color: #fafafa; border-color: #2e2e2e; }
.cd-title { margin: 0; font-size: 17px; font-weight: 600; }
.cd-msg { margin: 8px 0 0; font-size: 14px; color: #71717a; }
.cd-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 22px; }
.cd-btn { cursor: pointer; padding: 10px 18px; border-radius: 10px; font-size: 14px; font-weight: 500; border: 1px solid transparent; }
.cd-cancel { background: transparent; color: inherit; border-color: #d4d4d8; }
.dark .cd-cancel { border-color: #3f3f46; }
.cd-cancel:hover { background: rgba(127, 127, 127, 0.15); }
.cd-ok { background: #111111; color: #ffffff; border-color: #111111; }
.dark .cd-ok { background: #fafafa; color: #111111; border-color: #fafafa; }
.cd-ok:hover { opacity: 0.88; }
.cd-ok.cd-danger, .dark .cd-ok.cd-danger { background: #dc2626; color: #ffffff; border-color: #dc2626; }
</style>