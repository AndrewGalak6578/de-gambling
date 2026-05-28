<script setup>
import { onMounted, ref } from 'vue';
import AppLayout from '../components/AppLayout.vue';
import PageHeader from '../components/PageHeader.vue';
import Spinner from '../components/Spinner.vue';
import { api } from '../services/api';
import { navigate } from '../state/router';
import { normalizeCollection } from '../utils/format';
import { icons } from '../utils/icons';

const loading = ref(true);
const games = ref([]);

onMounted(async () => {
    const data = await api('/games');
    games.value = normalizeCollection(data);
    loading.value = false;
});
</script>

<template>
    <AppLayout>
        <PageHeader title="Games" subtitle="Choose a game to play" />
        <Spinner v-if="loading" />
        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div v-if="games.length === 0" class="empty-state col-span-full">
                <span v-html="icons.empty"></span>
                <p>No games currently available.</p>
            </div>
            <div v-for="game in games" :key="game.id" :class="['card cursor-pointer', game.slug === 'dice' ? 'card-gold' : 'card-silver']">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <h3 :class="['text-lg font-bold', game.slug === 'dice' ? 'gold-text' : 'silver-text']">{{ game.name }}</h3>
                        <p class="text-xs mt-1" style="color:var(--text-muted)">{{ game.config?.description || 'Casino game' }}</p>
                    </div>
                    <span class="badge badge-green">{{ game.rtp_percentage }}% RTP</span>
                </div>
                <div class="flex items-center justify-between pt-3 border-t">
                    <span class="text-xs font-mono" style="color:var(--text-muted)">{{ game.config?.min_bet ? `$${game.config.min_bet} - $${game.config.max_bet}` : '$0.10 - $5000' }}</span>
                    <button :class="['btn', game.slug === 'dice' ? 'btn-gold' : 'btn-silver', 'btn-sm']" @click="navigate('game-play', { gameId: game.slug })">Play</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
