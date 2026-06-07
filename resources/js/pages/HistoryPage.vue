<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import AppLayout from '../components/AppLayout.vue';
import PageHeader from '../components/PageHeader.vue';
import Spinner from '../components/Spinner.vue';
import { api } from '../services/api';
import { showToast } from '../services/toast';
import { money, normalizeCollection } from '../utils/format';

const loading = ref(true);
const bets = ref([]);
const games = ref([]);
const pagination = reactive({ current_page: 1, last_page: 1, total: 0, per_page: 20 });
const filters = reactive({ game_id: '', status: '', sort: 'created_at', direction: 'desc' });
const expandedId = ref(null);

onMounted(async () => {
    const gamesData = await api('/games');
    games.value = normalizeCollection(gamesData);
    await load();
});

watch(filters, () => { pagination.current_page = 1; load(); }, { deep: true });

async function load() {
    loading.value = true;
    const params = new URLSearchParams({
        page: pagination.current_page,
        per_page: pagination.per_page,
        sort: filters.sort,
        direction: filters.direction,
    });
    if (filters.game_id) params.set('game_id', filters.game_id);
    if (filters.status) params.set('status', filters.status);
    const data = await api(`/user/bets?${params.toString()}`);
    loading.value = false;
    if (!data || data._status) {
        showToast(data?.message || 'Failed to load history.', 'error');
        return;
    }
    bets.value = data.data || [];
    pagination.current_page = data.current_page;
    pagination.last_page = data.last_page;
    pagination.total = data.total;
    pagination.per_page = data.per_page;
}

function goToPage(page) {
    if (page < 1 || page > pagination.last_page || page === pagination.current_page) return;
    pagination.current_page = page;
    load();
}

function toggleExpanded(id) {
    expandedId.value = expandedId.value === id ? null : id;
}

function fmtDate(value) {
    if (!value) return '--';
    const d = new Date(value);
    return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function gameLabel(bet) {
    return bet.game?.name || `#${bet.game_id}`;
}

function gameEmoji(slug) {
    return { dice: '🎲', slots: '🎰', 'spin-to-win': '🎡', blackjack: '🃏' }[slug] || '🎮';
}

function net(bet) {
    return parseFloat(bet.payout_amount || 0) - parseFloat(bet.bet_amount || 0);
}

function netClass(bet) {
    const n = net(bet);
    if (n > 0) return 'text-green-400';
    if (n < 0) return 'text-red-400';
    return '';
}

function netLabel(bet) {
    const n = net(bet);
    const sign = n > 0 ? '+' : n < 0 ? '-' : '';
    return `${sign}$${Math.abs(n).toFixed(2)}`;
}

const summary = computed(() => {
    const wagered = bets.value.reduce((acc, b) => acc + parseFloat(b.bet_amount || 0), 0);
    const won = bets.value.reduce((acc, b) => acc + parseFloat(b.payout_amount || 0), 0);
    const wins = bets.value.filter((b) => parseFloat(b.payout_amount || 0) > parseFloat(b.bet_amount || 0)).length;
    return {
        wagered: wagered.toFixed(2),
        won: won.toFixed(2),
        net: (won - wagered).toFixed(2),
        wins,
        winRate: bets.value.length ? ((wins / bets.value.length) * 100).toFixed(1) : '0.0',
    };
});

function toggleSort(column) {
    if (filters.sort === column) {
        filters.direction = filters.direction === 'desc' ? 'asc' : 'desc';
    } else {
        filters.sort = column;
        filters.direction = 'desc';
    }
}

function sortIndicator(column) {
    if (filters.sort !== column) return '';
    return filters.direction === 'desc' ? '↓' : '↑';
}

const visiblePages = computed(() => {
    const total = pagination.last_page;
    const current = pagination.current_page;
    if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
    if (current <= 4) return [1, 2, 3, 4, 5, '…', total];
    if (current >= total - 3) return [1, '…', total - 4, total - 3, total - 2, total - 1, total];
    return [1, '…', current - 1, current, current + 1, '…', total];
});
</script>

<template>
    <AppLayout>
        <PageHeader title="Bet History" subtitle="Every wager you've ever placed." />

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-label">Bets (page)</div><div class="stat-value gold-text">{{ bets.length }}</div><div class="stat-sub">of {{ pagination.total }} total</div></div>
            <div class="stat-card"><div class="stat-label">Wagered (page)</div><div class="stat-value">${{ summary.wagered }}</div><div class="stat-sub">Sum of stakes shown</div></div>
            <div class="stat-card"><div class="stat-label">Returned (page)</div><div class="stat-value gold-text">${{ summary.won }}</div><div class="stat-sub">Sum of payouts shown</div></div>
            <div class="stat-card">
                <div class="stat-label">Net (page)</div>
                <div :class="['stat-value', parseFloat(summary.net) > 0 ? 'text-green-400' : parseFloat(summary.net) < 0 ? 'text-red-400' : '']">
                    {{ parseFloat(summary.net) >= 0 ? '+' : '-' }}${{ Math.abs(parseFloat(summary.net)).toFixed(2) }}
                </div>
                <div class="stat-sub">{{ summary.wins }} wins · {{ summary.winRate }}% rate</div>
            </div>
        </div>

        <div class="card mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="label">Game</label>
                    <select v-model="filters.game_id" class="select">
                        <option value="">All games</option>
                        <option v-for="g in games" :key="g.id" :value="g.id">{{ gameEmoji(g.slug) }} {{ g.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Status</label>
                    <select v-model="filters.status" class="select">
                        <option value="">All statuses</option>
                        <option value="settled">Settled</option>
                        <option value="pending">Pending</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div>
                    <label class="label">Per page</label>
                    <select v-model.number="pagination.per_page" class="select" @change="load">
                        <option :value="10">10</option>
                        <option :value="20">20</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button class="btn btn-ghost w-full" @click="load">Refresh</button>
                </div>
            </div>
        </div>

        <Spinner v-if="loading && !bets.length" />

        <div v-else-if="!bets.length" class="empty-state">
            <p>No bets match your filters yet.</p>
            <p class="text-xs mt-2" style="color:var(--text-muted)">Place a bet on the Games page and it will appear here.</p>
        </div>

        <div v-else class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width:60px">ID</th>
                        <th>Game</th>
                        <th class="sortable" @click="toggleSort('bet_amount')">Bet {{ sortIndicator('bet_amount') }}</th>
                        <th>Result</th>
                        <th class="sortable" @click="toggleSort('payout_amount')">Payout {{ sortIndicator('payout_amount') }}</th>
                        <th>Net</th>
                        <th>Status</th>
                        <th class="sortable" @click="toggleSort('created_at')">Date {{ sortIndicator('created_at') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="bet in bets" :key="bet.id">
                        <tr class="cursor-pointer history-row" @click="toggleExpanded(bet.id)">
                            <td class="font-mono text-xs">#{{ bet.id }}</td>
                            <td><span class="mr-2">{{ gameEmoji(bet.game?.slug) }}</span>{{ gameLabel(bet) }}</td>
                            <td class="font-mono">${{ money(bet.bet_amount) }}</td>
                            <td>
                                <template v-if="bet.result">
                                    <span v-if="bet.game?.slug === 'dice'" class="font-mono text-xs">Roll {{ bet.result.roll?.toFixed?.(2) ?? bet.result.roll ?? '--' }}</span>
                                    <span v-else-if="bet.game?.slug === 'slots'" class="font-mono text-xs">{{ (bet.result.reels || []).map(r => ({ cherry:'🍒',lemon:'🍋',bell:'🔔',star:'⭐',diamond:'💎',seven:'7️⃣',crown:'👑' }[r] || '?')).join(' ') }}</span>
                                    <span v-else-if="bet.game?.slug === 'spin-to-win'" class="font-mono text-xs">Sector {{ bet.result.landed_sector ?? '--' }}</span>
                                    <span v-else class="font-mono text-xs">--</span>
                                </template>
                            </td>
                            <td class="font-mono gold-text">${{ money(bet.payout_amount) }}</td>
                            <td :class="['font-mono font-bold', netClass(bet)]">{{ netLabel(bet) }}</td>
                            <td><span :class="['badge', bet.status === 'settled' ? 'badge-green' : bet.status === 'pending' ? 'badge-yellow' : 'badge-gray']">{{ bet.status }}</span></td>
                            <td class="text-xs">{{ fmtDate(bet.created_at) }}</td>
                        </tr>
                        <tr v-if="expandedId === bet.id" class="history-detail-row">
                            <td colspan="8">
                                <div class="history-detail">
                                    <div class="history-detail-grid">
                                        <div>
                                            <div class="history-detail-label">Server seed</div>
                                            <div class="history-detail-value font-mono break-all">{{ bet.result?.server_seed || '— not revealed —' }}</div>
                                        </div>
                                        <div>
                                            <div class="history-detail-label">Server seed hash</div>
                                            <div class="history-detail-value font-mono break-all">{{ bet.server_seed_hash || '--' }}</div>
                                        </div>
                                        <div>
                                            <div class="history-detail-label">Client seed</div>
                                            <div class="history-detail-value font-mono break-all">{{ bet.client_seed || '--' }}</div>
                                        </div>
                                        <div>
                                            <div class="history-detail-label">Currency</div>
                                            <div class="history-detail-value font-mono">{{ bet.currency }}</div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div v-if="pagination.last_page > 1" class="pagination">
            <button class="pagination-btn" :disabled="pagination.current_page === 1" @click="goToPage(pagination.current_page - 1)">‹ Prev</button>
            <template v-for="(page, idx) in visiblePages" :key="idx">
                <button
                    v-if="typeof page === 'number'"
                    :class="['pagination-btn', { 'pagination-btn-active': page === pagination.current_page }]"
                    @click="goToPage(page)"
                >{{ page }}</button>
                <span v-else class="pagination-ellipsis">{{ page }}</span>
            </template>
            <button class="pagination-btn" :disabled="pagination.current_page === pagination.last_page" @click="goToPage(pagination.current_page + 1)">Next ›</button>
        </div>
    </AppLayout>
</template>
