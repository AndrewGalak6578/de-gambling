<script setup>
import { icons } from '../utils/icons';

defineProps({
    name: { type: String, required: true },
    extraClass: { type: String, default: '' },
});
</script>

<template>
    <span :class="['nav-icon', extraClass]" v-html="icons[name] || ''"></span>
</template>

