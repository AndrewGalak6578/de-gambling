<script setup>
import { computed, onMounted, ref } from 'vue';
import AppLayout from '../components/AppLayout.vue';
import PageHeader from '../components/PageHeader.vue';
import Spinner from '../components/Spinner.vue';
import { api } from '../services/api';
import { showModal } from '../services/modal';
import { showToast } from '../services/toast';
import { session } from '../state/session';
import { fmtDate, interventionLabel, money } from '../utils/format';

const loading = ref(true);
const accessDenied = ref(false);
const activeTab = ref('users');
const users = ref([]);
const withdrawals = ref([]);
const riskEvents = ref([]);
const riskUsers = ref([]);
const activeInterventions = ref([]);
const financeOverview = ref(null);
const activityOpen = ref({
    users: true,
    bets: true,
    deposit_invoices: false,
    withdrawals: false,
    transactions: false,
});
const activityLimits = ref({
    users: 10,
    bets: 10,
    deposit_invoices: 10,
    withdrawals: 10,
    transactions: 10,
});
const activityLimitOptions = [5, 10, 25, 50, 100];
const selectedUserId = ref(null);
const selectedRiskUserId = ref(null);
const userInterventions = ref([]);
const games = ref([]);
const interventionForm = ref({ type: 'admin_bet_block', ends_at: '', max_wins: 5, window: 'day', reason: '' });
const riskOverrideForm = ref({ score_adjustment: 0, disabled: false, reason: '' });
const creditForm = ref({ user_id: '', amount: '50.00', reason: '' });
const selectedUser = computed(() => users.value.find((user) => String(user.id) === String(selectedUserId.value)));
const creditUser = computed(() => users.value.find((user) => String(user.id) === String(creditForm.value.user_id)));
const selectedRiskUser = computed(() => riskUsers.value.find((user) => String(user.id) === String(selectedRiskUserId.value)));

onMounted(async () => {
    const data = await api('/admin/users');
    if (!data || data._status) {
        session.isAdmin = false;
        accessDenied.value = true;
        loading.value = false;
        return;
    }

    session.isAdmin = true;
    users.value = data.users || [];
    selectedUserId.value = users.value[0]?.id || null;
    loading.value = false;
    await loadTab('users');
});

async function selectTab(tab) {
    activeTab.value = tab;
    await loadTab(tab);
}

async function loadTab(tab) {
    if (tab === 'users') await loadUsers();
    if (tab === 'withdrawals') await loadWithdrawals();
    if (tab === 'activity') await loadFinanceOverview();
    if (tab === 'risk') await loadRisk();
    if (tab === 'interventions') await loadUserInterventions();
    if (tab === 'games') await loadGames();
}

async function loadUsers() {
    const data = await api('/admin/users');
    users.value = data?.users || [];
    if (!selectedUserId.value && users.value.length) selectedUserId.value = users.value[0].id;
    if (!creditForm.value.user_id && users.value.length) creditForm.value.user_id = users.value[0].id;
}

async function loadWithdrawals() {
    const data = await api('/admin/withdrawals');
    withdrawals.value = data?.withdrawals || [];
}

async function loadFinanceOverview() {
    const data = await api('/admin/finance-overview');
    financeOverview.value = data && !data._status ? data : null;
}

async function loadRisk() {
    const [events, interventions, summaries] = await Promise.all([api('/admin/risk-events'), api('/admin/interventions'), api('/admin/risk-summaries')]);
    riskEvents.value = events?.risk_events || [];
    activeInterventions.value = interventions?.interventions || [];
    riskUsers.value = summaries?.users || [];
    if (riskUsers.value.length && !riskUsers.value.some((user) => String(user.id) === String(selectedRiskUserId.value))) {
        selectedRiskUserId.value = riskUsers.value[0].id;
    }
    syncRiskOverrideForm();
}

async function loadUserInterventions() {
    await loadUsers();
    if (users.value.length && !users.value.some((user) => String(user.id) === String(selectedUserId.value))) {
        selectedUserId.value = users.value[0].id;
    }
    const data = selectedUserId.value ? await api(`/admin/users/${selectedUserId.value}/interventions`) : { interventions: [] };
    userInterventions.value = data?.interventions || [];
}

async function loadGames() {
    const data = await api('/admin/games');
    games.value = data?.games || [];
}

function openUserInterventions(id) {
    selectedUserId.value = id;
    selectTab('interventions');
}

function openCreditUser(id) {
    creditForm.value.user_id = id;
}

async function creditBalance() {
    if (!creditForm.value.user_id) return showToast('Select a user first.', 'error');
    if (!creditForm.value.reason.trim()) return showToast('Reason is required.', 'error');

    const data = await api(`/admin/users/${creditForm.value.user_id}/wallet/credit`, {
        method: 'POST',
        body: JSON.stringify({
            amount: creditForm.value.amount,
            currency: 'USD',
            reason: creditForm.value.reason,
        }),
    });

    if (data && !data._status) {
        showToast(`Credited $${money(data.amount)} to ${creditUser.value?.name || 'user'}.`, 'success');
        creditForm.value.reason = '';
    } else showToast(data?.message || 'Failed to credit balance.', 'error');
}

function syncRiskOverrideForm() {
    const override = selectedRiskUser.value?.override || {};
    riskOverrideForm.value = {
        score_adjustment: override.score_adjustment ?? 0,
        disabled: Boolean(override.disabled),
        reason: override.reason || '',
    };
}

async function saveRiskOverride() {
    if (!selectedRiskUserId.value) return showToast('Select a user first.', 'error');

    const data = await api(`/admin/users/${selectedRiskUserId.value}/risk-override`, {
        method: 'PATCH',
        body: JSON.stringify({
            score_adjustment: Number(riskOverrideForm.value.score_adjustment || 0),
            disabled: Boolean(riskOverrideForm.value.disabled),
            reason: riskOverrideForm.value.reason || null,
        }),
    });

    if (data && !data._status) {
        showToast('Risk override saved.', 'success');
        await loadRisk();
    } else showToast(data?.message || 'Failed to save risk override.', 'error');
}

async function resetRiskOverride() {
    riskOverrideForm.value = { score_adjustment: 0, disabled: false, reason: '' };
    await saveRiskOverride();
}

function disableUser(id) {
    showModal({
        title: 'Disable User',
        description: 'Provide an optional reason for disabling this account.',
        showInput: true,
        inputLabel: 'Reason',
        inputPlaceholder: 'Optional reason',
        confirmText: 'Disable',
        confirmClass: 'btn-danger',
        onConfirm: async (reason) => {
            const data = await api(`/admin/users/${id}/disable`, { method: 'PATCH', body: JSON.stringify({ reason: reason || undefined }) });
            if (data && !data._status) {
                showToast('User disabled.', 'info');
                await loadUsers();
            } else showToast(data?.message || 'Failed.', 'error');
        },
    });
}

function deleteUser(id) {
    showModal({
        title: 'Delete User',
        description: 'This action is permanent and cannot be undone.',
        confirmText: 'Delete',
        confirmClass: 'btn-danger',
        onConfirm: async () => {
            const data = await api(`/admin/users/${id}`, { method: 'DELETE' });
            if (data && !data._status) {
                showToast('User deleted.', 'info');
                await loadUsers();
            } else showToast(data?.message || 'Failed.', 'error');
        },
    });
}

function approveWithdrawal(id) {
    showModal({
        title: 'Approve Withdrawal',
        description: 'Optionally enter a transaction hash.',
        showInput: true,
        inputLabel: 'TX Hash',
        inputPlaceholder: 'Optional',
        confirmText: 'Approve',
        confirmClass: 'btn-gold',
        onConfirm: async (txHash) => {
            const data = await api(`/admin/withdrawals/${id}/approve`, { method: 'PATCH', body: JSON.stringify({ tx_hash: txHash || undefined }) });
            if (data && !data._status) {
                showToast('Withdrawal approved!', 'success');
                await loadWithdrawals();
            } else showToast(data?.message || 'Failed.', 'error');
        },
    });
}

function rejectWithdrawal(id) {
    showModal({
        title: 'Reject Withdrawal',
        description: 'A reason is required for audit purposes.',
        showInput: true,
        inputLabel: 'Reason',
        inputPlaceholder: 'Rejection reason',
        confirmText: 'Reject',
        confirmClass: 'btn-danger',
        onConfirm: async (reason) => {
            if (!reason) return showToast('Reason required.', 'error');
            const data = await api(`/admin/withdrawals/${id}/reject`, { method: 'PATCH', body: JSON.stringify({ reason }) });
            if (data && !data._status) {
                showToast('Withdrawal rejected.', 'info');
                await loadWithdrawals();
            } else showToast(data?.message || 'Failed.', 'error');
        },
    });
}

async function createIntervention() {
    if (!selectedUserId.value) return showToast('Select a user first.', 'error');
    if (!interventionForm.value.reason.trim()) return showToast('Reason is required.', 'error');

    const body = { type: interventionForm.value.type, reason: interventionForm.value.reason };
    if (interventionForm.value.ends_at) body.ends_at = interventionForm.value.ends_at;
    if (interventionForm.value.type === 'admin_win_limit') {
        if (!interventionForm.value.max_wins || interventionForm.value.max_wins < 1) return showToast('Max wins must be at least 1.', 'error');
        body.payload = { max_wins: Number(interventionForm.value.max_wins), window: interventionForm.value.window || 'day' };
    }

    const data = await api(`/admin/users/${selectedUserId.value}/interventions`, { method: 'POST', body: JSON.stringify(body) });
    if (data && !data._status) {
        showToast('Intervention applied.', 'success');
        interventionForm.value.reason = '';
        await loadUserInterventions();
    } else showToast(data?.message || 'Failed to apply intervention.', 'error');
}

function revokeIntervention(id) {
    showModal({
        title: 'Revoke Intervention',
        description: 'The historical record will remain archived.',
        showInput: true,
        inputLabel: 'Reason',
        inputPlaceholder: 'Required revoke reason',
        confirmText: 'Revoke',
        confirmClass: 'btn-danger',
        onConfirm: async (reason) => {
            if (!reason) return showToast('Reason is required.', 'error');
            const data = await api(`/admin/interventions/${id}/revoke`, { method: 'PATCH', body: JSON.stringify({ reason }) });
            if (data && !data._status) {
                showToast('Intervention revoked.', 'info');
                await loadUserInterventions();
            } else showToast(data?.message || 'Failed to revoke intervention.', 'error');
        },
    });
}

function editRtp(gameId) {
    showModal({
        title: 'Set RTP',
        description: 'Enter the target RTP percentage (1-99.99).',
        showInput: true,
        inputLabel: 'RTP %',
        inputPlaceholder: 'e.g. 95.00',
        confirmText: 'Update',
        confirmClass: 'btn-gold',
        onConfirm: async (value) => {
            if (!value) return;
            const data = await api(`/admin/games/${gameId}/rtp`, { method: 'PATCH', body: JSON.stringify({ rtp_percentage: parseFloat(value) }) });
            if (data && !data._status) {
                showToast(`RTP updated to ${value}%`, 'success');
                await loadGames();
            } else showToast(data?.message || 'Failed.', 'error');
        },
    });
}

async function toggleGameStatus(game) {
    const status = game.status === 'active' ? 'inactive' : 'active';
    const data = await api(`/admin/games/${game.id}/status`, { method: 'PATCH', body: JSON.stringify({ status }) });
    if (data && !data._status) {
        showToast(`Game ${status}.`, 'info');
        await loadGames();
    } else showToast(data?.message || 'Failed.', 'error');
}

function details(intervention) {
    const payload = intervention.payload || {};
    return [
        payload.max_wins ? `max wins: ${payload.max_wins}` : null,
        payload.window ? `window: ${payload.window}` : null,
        payload.reason ? `reason: ${payload.reason}` : null,
        payload.revoke_reason ? `revoked: ${payload.revoke_reason}` : null,
    ].filter(Boolean);
}

function statusBadge(status) {
    if (['confirmed', 'paid', 'forwarded', 'settled', 'active'].includes(status)) return 'badge-green';
    if (['pending', 'fixated'].includes(status)) return 'badge-yellow';
    if (['failed', 'rejected', 'cancelled', 'disabled'].includes(status)) return 'badge-red';
    return 'badge-gray';
}

function toggleActivitySection(key) {
    activityOpen.value[key] = !activityOpen.value[key];
}

function activityRows(key) {
    return (financeOverview.value?.[key] || []).slice(0, Number(activityLimits.value[key] || 10));
}

function activityCount(key) {
    return financeOverview.value?.[key]?.length || 0;
}
</script>

<template>
    <AppLayout>
        <Spinner v-if="loading" />
        <div v-else-if="accessDenied" class="empty-state"><p>Access denied.</p></div>
        <template v-else>
            <PageHeader title="Admin" subtitle="Management dashboard" />
            <div class="tabs">
                <button v-for="tab in ['users', 'activity', 'withdrawals', 'risk', 'interventions', 'games']" :key="tab" :class="['tab', { active: activeTab === tab }]" @click="selectTab(tab)">{{ tab[0].toUpperCase() + tab.slice(1) }}</button>
            </div>

            <div v-if="activeTab === 'users'">
                <form class="card card-gold mb-6" @submit.prevent="creditBalance">
                    <h3 class="font-bold mb-3">Credit User Balance</h3>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="form-group">
                            <label class="label">User</label>
                            <select v-model="creditForm.user_id" class="input">
                                <option v-for="user in users" :key="user.id" :value="user.id">#{{ user.id }} {{ user.name }} ({{ user.email }})</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="label">Amount (USD)</label>
                            <input v-model="creditForm.amount" type="number" class="input" min="0.01" step="0.01">
                        </div>
                        <div class="form-group">
                            <label class="label">Reason</label>
                            <input v-model="creditForm.reason" type="text" class="input" placeholder="Required audit reason">
                        </div>
                        <div class="form-group" style="display:flex;align-items:end">
                            <button class="btn btn-gold w-full" :disabled="!creditUser">Add Funds</button>
                        </div>
                    </div>
                </form>
                <div class="table-wrapper"><table><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead><tbody>
                    <tr v-for="user in users" :key="user.id">
                        <td class="font-mono text-xs">{{ user.id }}</td><td class="font-semibold" style="color:var(--text-primary)">{{ user.name }}</td><td class="font-mono text-xs">{{ user.email }}</td>
                        <td><span :class="['badge', user.status === 'active' ? 'badge-green' : 'badge-red']">{{ user.status }}</span></td><td class="text-xs">{{ new Date(user.created_at).toLocaleDateString() }}</td>
                        <td class="flex gap-2"><button class="btn btn-gold btn-sm" @click="openCreditUser(user.id)">Credit</button><button class="btn btn-ghost btn-sm" @click="openUserInterventions(user.id)">Limits</button><button v-if="user.status === 'active'" class="btn btn-ghost btn-sm" @click="disableUser(user.id)">Disable</button><button class="btn btn-danger btn-sm" @click="deleteUser(user.id)">Delete</button></td>
                    </tr>
                </tbody></table></div>
            </div>

            <div v-if="activeTab === 'withdrawals'" class="table-wrapper"><table><thead><tr><th>ID</th><th>User</th><th>Amount</th><th>Status</th><th>Address</th><th>Date</th><th>Actions</th></tr></thead><tbody>
                <tr v-if="withdrawals.length === 0"><td colspan="7" class="text-center py-4" style="color:var(--text-muted)">No pending withdrawals</td></tr>
                <tr v-for="withdrawal in withdrawals" :key="withdrawal.id">
                    <td class="font-mono text-xs">{{ withdrawal.id }}</td><td class="text-xs">User #{{ withdrawal.user_id }}</td><td class="font-bold gold-text">${{ money(withdrawal.amount) }}</td><td><span class="badge badge-yellow">{{ withdrawal.status }}</span></td><td class="font-mono text-xs truncate" style="max-width:150px">{{ withdrawal.meta?.destination || '--' }}</td><td class="text-xs">{{ new Date(withdrawal.created_at).toLocaleString() }}</td>
                    <td class="flex gap-2"><button class="btn btn-gold btn-sm" @click="approveWithdrawal(withdrawal.id)">Approve</button><button class="btn btn-ghost btn-sm" @click="rejectWithdrawal(withdrawal.id)">Reject</button></td>
                </tr>
            </tbody></table></div>

            <div v-if="activeTab === 'activity'">
                <div v-if="!financeOverview" class="empty-state"><p>Finance activity could not be loaded.</p></div>
                <template v-else>
                    <div class="stats-grid mb-6">
                        <div class="stat-card"><div class="stat-label">Users</div><div class="stat-value">{{ financeOverview.stats.users_count }}</div><div class="stat-sub">registered</div></div>
                        <div class="stat-card"><div class="stat-label">Wallet Balance</div><div class="stat-value gold-text">${{ money(financeOverview.stats.wallet_balance_usd) }}</div><div class="stat-sub">total USD</div></div>
                        <div class="stat-card"><div class="stat-label">Deposits</div><div class="stat-value">${{ money(financeOverview.stats.deposits_usd) }}</div><div class="stat-sub">{{ financeOverview.stats.pending_deposit_invoices_count }} pending invoices</div></div>
                        <div class="stat-card"><div class="stat-label">Withdrawals</div><div class="stat-value">${{ money(financeOverview.stats.withdrawals_usd) }}</div><div class="stat-sub">{{ financeOverview.stats.pending_withdrawals_count }} pending</div></div>
                        <div class="stat-card"><div class="stat-label">Bet Volume</div><div class="stat-value">${{ money(financeOverview.stats.bet_volume_usd) }}</div><div class="stat-sub">settled stakes</div></div>
                        <div class="stat-card"><div class="stat-label">Payouts</div><div class="stat-value">${{ money(financeOverview.stats.payouts_usd) }}</div><div class="stat-sub">settled wins</div></div>
                    </div>

                    <div class="card mb-4">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <div><h3 class="font-bold">Users Overview</h3><p class="text-xs" style="color:var(--text-muted)">Showing {{ activityRows('users').length }} of {{ activityCount('users') }}</p></div>
                            <div class="flex items-center gap-2"><select v-model.number="activityLimits.users" class="input" style="width:96px"><option v-for="limit in activityLimitOptions" :key="limit" :value="limit">{{ limit }}</option></select><button type="button" class="btn btn-ghost btn-sm" @click="toggleActivitySection('users')">{{ activityOpen.users ? 'Hide' : 'Show' }}</button></div>
                        </div>
                        <div v-if="activityOpen.users" class="table-wrapper"><table><thead><tr><th>ID</th><th>User</th><th>Status</th><th>Wallet</th><th>Bets</th><th>Transactions</th></tr></thead><tbody>
                            <tr v-for="user in activityRows('users')" :key="user.id">
                                <td class="font-mono text-xs">{{ user.id }}</td><td><div class="font-semibold" style="color:var(--text-primary)">{{ user.name }}</div><div class="font-mono text-xs" style="color:var(--text-muted)">{{ user.email }}</div></td><td><span :class="['badge', statusBadge(user.status)]">{{ user.status }}</span></td><td class="font-bold gold-text">${{ money(user.wallet_balance) }}</td><td class="font-mono text-xs">{{ user.bets_count }}</td><td class="font-mono text-xs">{{ user.transactions_count }}</td>
                            </tr>
                        </tbody></table></div>
                    </div>

                    <div class="card mb-4">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <div><h3 class="font-bold">User Bets</h3><p class="text-xs" style="color:var(--text-muted)">Showing {{ activityRows('bets').length }} of {{ activityCount('bets') }}</p></div>
                            <div class="flex items-center gap-2"><select v-model.number="activityLimits.bets" class="input" style="width:96px"><option v-for="limit in activityLimitOptions" :key="limit" :value="limit">{{ limit }}</option></select><button type="button" class="btn btn-ghost btn-sm" @click="toggleActivitySection('bets')">{{ activityOpen.bets ? 'Hide' : 'Show' }}</button></div>
                        </div>
                        <div v-if="activityOpen.bets" class="table-wrapper"><table><thead><tr><th>ID</th><th>User</th><th>Game</th><th>Bet</th><th>Payout</th><th>Status</th><th>Date</th></tr></thead><tbody>
                            <tr v-if="activityCount('bets') === 0"><td colspan="7" class="text-center py-4" style="color:var(--text-muted)">No bets yet</td></tr>
                            <tr v-for="bet in activityRows('bets')" :key="bet.id">
                                <td class="font-mono text-xs">{{ bet.id }}</td><td class="text-xs">{{ bet.user?.name || `#${bet.user_id}` }}</td><td class="text-xs">{{ bet.game?.name || `#${bet.game_id}` }}</td><td class="font-bold">${{ money(bet.bet_amount) }}</td><td class="font-bold gold-text">${{ money(bet.payout_amount) }}</td><td><span :class="['badge', statusBadge(bet.status)]">{{ bet.status }}</span></td><td class="text-xs">{{ new Date(bet.created_at).toLocaleString() }}</td>
                            </tr>
                        </tbody></table></div>
                    </div>

                    <div class="card mb-4">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <div><h3 class="font-bold">Deposit History</h3><p class="text-xs" style="color:var(--text-muted)">Showing {{ activityRows('deposit_invoices').length }} of {{ activityCount('deposit_invoices') }}</p></div>
                            <div class="flex items-center gap-2"><select v-model.number="activityLimits.deposit_invoices" class="input" style="width:96px"><option v-for="limit in activityLimitOptions" :key="limit" :value="limit">{{ limit }}</option></select><button type="button" class="btn btn-ghost btn-sm" @click="toggleActivitySection('deposit_invoices')">{{ activityOpen.deposit_invoices ? 'Hide' : 'Show' }}</button></div>
                        </div>
                        <div v-if="activityOpen.deposit_invoices" class="table-wrapper"><table><thead><tr><th>ID</th><th>User</th><th>Expected USD</th><th>Coin</th><th>Address</th><th>Status</th><th>Created</th></tr></thead><tbody>
                            <tr v-if="activityCount('deposit_invoices') === 0"><td colspan="7" class="text-center py-4" style="color:var(--text-muted)">No deposits yet</td></tr>
                            <tr v-for="invoice in activityRows('deposit_invoices')" :key="invoice.id">
                                <td class="font-mono text-xs">{{ invoice.id }}</td><td class="text-xs">{{ invoice.user?.name || `#${invoice.user_id}` }}</td><td class="font-bold gold-text">${{ money(invoice.expected_usd) }}</td><td class="font-mono text-xs">{{ invoice.coin?.toUpperCase() }}</td><td class="font-mono text-xs truncate" style="max-width:180px">{{ invoice.pay_address }}</td><td><span :class="['badge', statusBadge(invoice.status)]">{{ invoice.status }}</span></td><td class="text-xs">{{ new Date(invoice.created_at).toLocaleString() }}</td>
                            </tr>
                        </tbody></table></div>
                    </div>

                    <div class="card mb-4">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <div><h3 class="font-bold">Withdrawal History</h3><p class="text-xs" style="color:var(--text-muted)">Showing {{ activityRows('withdrawals').length }} of {{ activityCount('withdrawals') }}</p></div>
                            <div class="flex items-center gap-2"><select v-model.number="activityLimits.withdrawals" class="input" style="width:96px"><option v-for="limit in activityLimitOptions" :key="limit" :value="limit">{{ limit }}</option></select><button type="button" class="btn btn-ghost btn-sm" @click="toggleActivitySection('withdrawals')">{{ activityOpen.withdrawals ? 'Hide' : 'Show' }}</button></div>
                        </div>
                        <div v-if="activityOpen.withdrawals" class="table-wrapper"><table><thead><tr><th>ID</th><th>User</th><th>Amount</th><th>Status</th><th>Destination</th><th>Date</th></tr></thead><tbody>
                            <tr v-if="activityCount('withdrawals') === 0"><td colspan="6" class="text-center py-4" style="color:var(--text-muted)">No withdrawals yet</td></tr>
                            <tr v-for="withdrawal in activityRows('withdrawals')" :key="withdrawal.id">
                                <td class="font-mono text-xs">{{ withdrawal.id }}</td><td class="text-xs">{{ withdrawal.user?.name || `#${withdrawal.user_id}` }}</td><td class="font-bold gold-text">${{ money(withdrawal.amount) }}</td><td><span :class="['badge', statusBadge(withdrawal.status)]">{{ withdrawal.status }}</span></td><td class="font-mono text-xs truncate" style="max-width:180px">{{ withdrawal.meta?.destination || '--' }}</td><td class="text-xs">{{ new Date(withdrawal.created_at).toLocaleString() }}</td>
                            </tr>
                        </tbody></table></div>
                    </div>

                    <div class="card">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <div><h3 class="font-bold">Ledger Transactions</h3><p class="text-xs" style="color:var(--text-muted)">Showing {{ activityRows('transactions').length }} of {{ activityCount('transactions') }}</p></div>
                            <div class="flex items-center gap-2"><select v-model.number="activityLimits.transactions" class="input" style="width:96px"><option v-for="limit in activityLimitOptions" :key="limit" :value="limit">{{ limit }}</option></select><button type="button" class="btn btn-ghost btn-sm" @click="toggleActivitySection('transactions')">{{ activityOpen.transactions ? 'Hide' : 'Show' }}</button></div>
                        </div>
                        <div v-if="activityOpen.transactions" class="table-wrapper"><table><thead><tr><th>ID</th><th>User</th><th>Type</th><th>Amount</th><th>Status</th><th>Reason</th><th>Date</th></tr></thead><tbody>
                            <tr v-if="activityCount('transactions') === 0"><td colspan="7" class="text-center py-4" style="color:var(--text-muted)">No transactions yet</td></tr>
                            <tr v-for="transaction in activityRows('transactions')" :key="transaction.id">
                                <td class="font-mono text-xs">{{ transaction.id }}</td><td class="text-xs">{{ transaction.user?.name || `#${transaction.user_id}` }}</td><td><span class="badge badge-blue">{{ transaction.type }}</span></td><td class="font-bold gold-text">${{ money(transaction.amount) }}</td><td><span :class="['badge', statusBadge(transaction.status)]">{{ transaction.status }}</span></td><td class="text-xs">{{ transaction.reason || '--' }}</td><td class="text-xs">{{ new Date(transaction.created_at).toLocaleString() }}</td>
                            </tr>
                        </tbody></table></div>
                    </div>
                </template>
            </div>

            <div v-if="activeTab === 'risk'">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
                    <div class="card card-gold">
                        <h3 class="font-bold mb-3">Risk Override</h3>
                        <div class="form-group">
                            <label class="label">Player</label>
                            <select v-model="selectedRiskUserId" class="input" @change="syncRiskOverrideForm">
                                <option v-for="user in riskUsers" :key="user.id" :value="user.id">#{{ user.id }} {{ user.name }} ({{ user.email }})</option>
                            </select>
                        </div>
                        <div v-if="selectedRiskUser" class="grid grid-cols-2 gap-3">
                            <div class="dice-stat"><div class="dice-stat-label">Raw Score</div><div class="dice-stat-value">{{ selectedRiskUser.raw_score }}</div></div>
                            <div class="dice-stat"><div class="dice-stat-label">Effective</div><div class="dice-stat-value gold-text">{{ selectedRiskUser.effective_score }}</div></div>
                        </div>
                    </div>
                    <form class="card" style="grid-column:span 2" @submit.prevent="saveRiskOverride">
                        <h3 class="font-bold mb-3">Adjust Automation</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="form-group">
                                <label class="label">Score Adjustment (-100..100)</label>
                                <input v-model.number="riskOverrideForm.score_adjustment" type="number" class="input" min="-100" max="100">
                            </div>
                            <label class="form-group flex items-center gap-3" style="margin-top:1.65rem">
                                <input v-model="riskOverrideForm.disabled" type="checkbox">
                                <span class="text-sm" style="color:var(--text-secondary)">Disable risk score automation</span>
                            </label>
                            <div class="form-group">
                                <label class="label">Reason</label>
                                <input v-model="riskOverrideForm.reason" type="text" class="input" placeholder="Optional audit note">
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button class="btn btn-gold btn-sm" :disabled="!selectedRiskUser">Save Override</button>
                            <button type="button" class="btn btn-ghost btn-sm" :disabled="!selectedRiskUser" @click="resetRiskOverride">Reset</button>
                        </div>
                        <p v-if="selectedRiskUser?.override?.updated_at" class="text-xs mt-3" style="color:var(--text-muted)">Last updated: {{ new Date(selectedRiskUser.override.updated_at).toLocaleString() }}</p>
                    </form>
                </div>
                <div class="mb-6"><h3 class="font-bold mb-3">Active Interventions ({{ activeInterventions.length }})</h3><div v-if="activeInterventions.length === 0" class="p-4 rounded-lg text-sm text-center" style="background:var(--surface-1);border:1px solid var(--border-subtle);color:var(--text-muted)">All accounts within normal limits.</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4"><div v-for="item in activeInterventions" :key="item.id" class="card card-gold"><div class="flex items-center justify-between mb-2"><span class="badge badge-red">{{ item.type }}</span><span class="text-xs" style="color:var(--text-secondary)">#{{ item.user_id }} {{ item.user?.name || '' }}</span></div><p class="text-xs" style="color:var(--text-secondary)">{{ item.payload?.message || 'Active hold' }}</p><p class="text-xs mt-2" style="color:var(--text-muted)">Ends: {{ new Date(item.ends_at).toLocaleString() }}</p></div></div>
                </div>
                <h3 class="font-bold mb-3">Risk Events</h3><div class="table-wrapper"><table><thead><tr><th>ID</th><th>User</th><th>Type</th><th>Score</th><th>Value</th><th>Date</th></tr></thead><tbody><tr v-if="riskEvents.length === 0"><td colspan="6" class="text-center py-4" style="color:var(--text-muted)">No risk events</td></tr><tr v-for="event in riskEvents" :key="event.id"><td class="font-mono text-xs">{{ event.id }}</td><td class="text-xs" style="color:var(--text-primary)">{{ event.user?.name || `#${event.user_id}` }}</td><td><span :class="['badge', event.type === 'chasing_losses' ? 'badge-red' : event.type === 'deposit_spike' ? 'badge-yellow' : 'badge-blue']">{{ event.type }}</span></td><td class="font-semibold" style="color:var(--text-primary)">+{{ event.score_delta }}</td><td class="font-mono text-xs">{{ event.payload?.bet_amount ? `$${money(event.payload.bet_amount)}` : '--' }}</td><td class="text-xs">{{ new Date(event.created_at).toLocaleString() }}</td></tr></tbody></table></div>
            </div>

            <div v-if="activeTab === 'interventions'">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="card card-gold"><h3 class="font-bold mb-3">Target User</h3><div class="form-group"><label class="label">User</label><select v-model="selectedUserId" class="input" @change="loadUserInterventions"><option v-for="user in users" :key="user.id" :value="user.id">#{{ user.id }} {{ user.name }} ({{ user.email }})</option></select></div><p v-if="selectedUser" class="text-xs" style="color:var(--text-secondary)">Selected account: <strong style="color:var(--text-primary)">{{ selectedUser.name }}</strong></p><p v-else class="text-sm" style="color:var(--text-muted)">No users available.</p></div>
                    <form class="card card-gold" style="grid-column:span 2" @submit.prevent="createIntervention"><h3 class="font-bold mb-3">Apply Intervention</h3><div class="grid grid-cols-1 md:grid-cols-2 gap-4"><div class="form-group"><label class="label">Type</label><select v-model="interventionForm.type" class="input"><option value="admin_bet_block">Betting block</option><option value="admin_deposit_block">Deposit block</option><option value="admin_win_limit">Win count limit</option><option value="admin_cool_off">Cool-off</option></select></div><div class="form-group"><label class="label">Ends At</label><input v-model="interventionForm.ends_at" type="datetime-local" class="input"></div><div v-if="interventionForm.type === 'admin_win_limit'" class="form-group"><label class="label">Max Wins</label><input v-model.number="interventionForm.max_wins" type="number" class="input" min="1"></div><div v-if="interventionForm.type === 'admin_win_limit'" class="form-group"><label class="label">Window</label><select v-model="interventionForm.window" class="input"><option value="day">Calendar day</option><option value="24h">Rolling 24h</option></select></div><div class="form-group" style="grid-column:1/-1"><label class="label">Reason</label><input v-model="interventionForm.reason" type="text" class="input" placeholder="Required audit reason"></div></div><button class="btn btn-gold w-full" :disabled="!selectedUser">Apply Intervention</button></form>
                </div>
                <h3 class="font-bold mb-3">Intervention History {{ selectedUser ? `for #${selectedUser.id}` : '' }}</h3><div class="table-wrapper"><table><thead><tr><th>ID</th><th>Type</th><th>Status</th><th>Details</th><th>Ends</th><th>Actions</th></tr></thead><tbody><tr v-if="userInterventions.length === 0"><td colspan="6" class="text-center py-4" style="color:var(--text-muted)">No interventions for this user</td></tr><tr v-for="item in userInterventions" :key="item.id"><td class="font-mono text-xs">{{ item.id }}</td><td><span class="badge badge-gold">{{ interventionLabel(item.type) }}</span></td><td><span :class="['badge', item.status === 'active' ? 'badge-red' : item.status === 'revoked' ? 'badge-gray' : 'badge-yellow']">{{ item.status }}</span></td><td class="text-xs" style="color:var(--text-secondary)"><template v-if="details(item).length"><span v-for="detail in details(item)" :key="detail">{{ detail }}<br></span></template><template v-else>--</template></td><td class="text-xs">{{ fmtDate(item.ends_at) }}</td><td><button v-if="item.status === 'active'" class="btn btn-ghost btn-sm" @click="revokeIntervention(item.id)">Revoke</button><span v-else class="text-xs" style="color:var(--text-muted)">Archived</span></td></tr></tbody></table></div>
            </div>

            <div v-if="activeTab === 'games'" class="table-wrapper"><table><thead><tr><th>ID</th><th>Name</th><th>Slug</th><th>Status</th><th>RTP</th><th>Actions</th></tr></thead><tbody><tr v-for="game in games" :key="game.id"><td class="font-mono text-xs">{{ game.id }}</td><td class="font-semibold" style="color:var(--text-primary)">{{ game.name }}</td><td class="font-mono text-xs">{{ game.slug }}</td><td><span class="badge badge-green">{{ game.status }}</span></td><td class="font-mono font-bold" style="color:var(--text-primary)">{{ game.rtp_percentage }}%</td><td class="flex gap-2"><button class="btn btn-gold btn-sm" @click="editRtp(game.id)">Set RTP</button><button class="btn btn-ghost btn-sm" @click="toggleGameStatus(game)">{{ game.status === 'active' ? 'Deactivate' : 'Activate' }}</button></td></tr></tbody></table></div>
        </template>
    </AppLayout>
</template>
