<script setup>
import { icons } from '../utils/icons';
import { toasts } from '../services/toast';
</script>

<template>
    <div class="toast-container">
        <div
            v-for="toast in toasts"
            :key="toast.id"
            :class="['toast', `toast-${toast.type}`]"
            :style="toast.leaving ? { opacity: 0, transform: 'translateX(20px)', transition: 'all .25s ease' } : null"
        >
            <span style="width:16px;height:16px;flex-shrink:0" v-html="toast.type === 'success' ? icons.check : toast.type === 'error' ? icons.x : icons.info"></span>
            <span>{{ toast.message }}</span>
        </div>
    </div>
</template>

