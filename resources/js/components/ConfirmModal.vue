<script setup>
import { nextTick, ref, watch } from 'vue';
import { closeModal, modal } from '../services/modal';

const input = ref(null);

watch(() => modal.open, async (open) => {
    if (open && modal.showInput) {
        await nextTick();
        input.value?.focus();
    }
});

function confirm() {
    const value = modal.showInput ? modal.inputValue : '';
    const handler = modal.onConfirm;
    closeModal();
    if (handler) handler(value);
}
</script>

<template>
    <div v-if="modal.open" class="modal-overlay" @click.self="closeModal">
        <div class="modal">
            <div class="modal-title">{{ modal.title }}</div>
            <div class="modal-desc">{{ modal.description }}</div>
            <div v-if="modal.showInput">
                <label class="label">{{ modal.inputLabel }}</label>
                <input ref="input" v-model="modal.inputValue" type="text" class="input" :placeholder="modal.inputPlaceholder">
            </div>
            <div class="modal-actions">
                <button class="btn btn-ghost btn-sm" @click="closeModal">Cancel</button>
                <button :class="['btn', modal.confirmClass, 'btn-sm']" @click="confirm">{{ modal.confirmText }}</button>
            </div>
        </div>
    </div>
</template>

