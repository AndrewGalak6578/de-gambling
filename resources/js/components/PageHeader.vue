<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <div class="page-header">
        <h1 class="page-title gold-text">{{ title }}</h1>
        <p class="page-subtitle">{{ subtitle }}</p>
    </div>
</template>

