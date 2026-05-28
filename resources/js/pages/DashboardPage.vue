<script setup>
import { onMounted, ref } from 'vue';
import AppIcon from '../components/AppIcon.vue';
import AppLayout from '../components/AppLayout.vue';
import PageHeader from '../components/PageHeader.vue';
import Spinner from '../components/Spinner.vue';
import { api } from '../services/api';
import { session } from '../state/session';
import { navigate } from '../state/router';
import { money, normalizeCollection } from '../utils/format';

const loading = ref(true);
const dashboard = ref({});
const wallet = ref({});
const selfExclusion = ref({});
const gameCount = ref('--');

onMounted(load);

async function load() {
    const [dashboardData, walletData, selfExclData] = await Promise.all([
        api('/user/dashboard'),
        api('/wallet'),
        api('/user/self-exclusion'),
    ]);

    if (dashboardData?.user) session.user = dashboardData.user;
    dashboard.value = dashboardData?.dashboard || {};
    wallet.value = walletData || {};
    selfExclusion.value = selfExclData || {};

    const adminCheck = await api('/admin/users');
    session.isAdmin = Boolean(adminCheck && !adminCheck._status);

    loading.value = false;
    const gamesData = await api('/games');
    const games = normalizeCollection(gamesData);
    gameCount.value = games.length;
}

function riskLevel(score) {
    if (score < 30) return 'Low';
    if (score < 60) return 'Medium';
    if (score < 75) return 'High';
    return 'Critical';
}

function riskBadge(score) {
    if (score < 30) return 'badge-green';
    if (score < 60) return 'badge-yellow';
    return 'badge-red';
}
</script>

<template>
    <AppLayout>
        <Spinner v-if="loading" />
        <template v-else>
            <PageHeader title="Dashboard" :subtitle="`Welcome back, ${session.user?.name || 'Player'}`" />
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-label">Balance</div><div class="stat-value gold-text">${{ money(wallet.balance) }}</div><div class="stat-sub">Available funds</div></div>
                <div class="stat-card"><div class="stat-label">Risk Score</div><div class="stat-value"><span :class="['badge', riskBadge(dashboard.risk_score ?? 0)]">{{ dashboard.risk_score ?? 0 }} - {{ riskLevel(dashboard.risk_score ?? 0) }}</span></div><div class="stat-sub">{{ (dashboard.risk_score ?? 0) < 75 ? 'Within normal limits' : 'Cooling active' }}</div></div>
                <div class="stat-card"><div class="stat-label">Status</div><div class="stat-value"><span :class="['badge', session.user?.status === 'active' ? 'badge-green' : 'badge-red']">{{ session.user?.status || 'active' }}</span></div><div class="stat-sub">{{ dashboard.self_excluded ? 'Self-excluded' : dashboard.cool_off_active ? 'Cool-off active' : 'Active member' }}</div></div>
                <div class="stat-card"><div class="stat-label">Games</div><div class="stat-value silver-text">{{ gameCount }}</div><div class="stat-sub"><a href="#" @click.prevent="navigate('games')" style="color:var(--gold-300);text-decoration:none;font-weight:600">Browse games &rarr;</a></div></div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div v-if="dashboard.self_excluded" class="card card-gold">
                    <h3 class="font-bold mb-3">Self-Exclusion Active</h3>
                    <div class="space-y-3 text-sm" style="color:var(--text-secondary)">
                        <p>Betting blocked until <strong>{{ new Date(selfExclusion.intervention?.ends_at).toLocaleDateString() }}</strong>.</p>
                        <p v-if="selfExclusion.intervention?.payload?.reason" class="p-3 rounded" style="background:var(--surface-1);border:1px solid var(--border-subtle)">Reason: {{ selfExclusion.intervention.payload.reason }}</p>
                    </div>
                </div>
                <div v-else-if="dashboard.cool_off_active" class="card card-gold">
                    <h3 class="font-bold mb-3">Cool-off Active</h3>
                    <div class="space-y-3 text-sm" style="color:var(--text-secondary)">
                        <p>A 24-hour cooling break was triggered.</p>
                        <p v-if="dashboard.active_intervention?.payload?.message" class="p-3 rounded text-xs" style="background:var(--surface-1);border:1px solid var(--border-subtle);color:var(--text-muted)">{{ dashboard.active_intervention.payload.message }}</p>
                    </div>
                </div>
                <div v-else class="card card-gold">
                    <h3 class="font-bold mb-2">Quick Play</h3>
                    <p class="text-sm mb-4" style="color:var(--text-secondary)">Jump straight into your favorite game.</p>
                    <div class="flex gap-3">
                        <button class="btn btn-gold btn-sm" @click="navigate('game-play', { gameId: 'dice' })"><AppIcon name="dice" /> Dice</button>
                        <button class="btn btn-silver btn-sm" @click="navigate('game-play', { gameId: 'spin-to-win' })"><AppIcon name="wheel" /> Spin</button>
                    </div>
                </div>
                <div class="card">
                    <h3 class="font-bold mb-2">Wallet</h3>
                    <p class="text-sm mb-4" style="color:var(--text-secondary)">Manage deposits and withdrawals.</p>
                    <div class="flex gap-3"><button class="btn btn-gold btn-sm" @click="navigate('wallet')">Deposit</button><button class="btn btn-ghost btn-sm" @click="navigate('wallet')">Withdraw</button></div>
                </div>
            </div>
        </template>
    </AppLayout>
</template>
