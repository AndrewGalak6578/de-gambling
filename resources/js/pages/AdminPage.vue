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
const activeInterventions = ref([]);
const selectedUserId = ref(null);
const userInterventions = ref([]);
const games = ref([]);
const interventionForm = ref({ type: 'admin_bet_block', ends_at: '', max_wins: 5, window: 'day', reason: '' });
const selectedUser = computed(() => users.value.find((user) => String(user.id) === String(selectedUserId.value)));

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
    if (tab === 'risk') await loadRisk();
    if (tab === 'interventions') await loadUserInterventions();
    if (tab === 'games') await loadGames();
}

async function loadUsers() {
    const data = await api('/admin/users');
    users.value = data?.users || [];
    if (!selectedUserId.value && users.value.length) selectedUserId.value = users.value[0].id;
}

async function loadWithdrawals() {
    const data = await api('/admin/withdrawals');
    withdrawals.value = data?.withdrawals || [];
}

async function loadRisk() {
    const [events, interventions] = await Promise.all([api('/admin/risk-events'), api('/admin/interventions')]);
    riskEvents.value = events?.risk_events || [];
    activeInterventions.value = interventions?.interventions || [];
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
</script>

<template>
    <AppLayout>
        <Spinner v-if="loading" />
        <div v-else-if="accessDenied" class="empty-state"><p>Access denied.</p></div>
        <template v-else>
            <PageHeader title="Admin" subtitle="Management dashboard" />
            <div class="tabs">
                <button v-for="tab in ['users', 'withdrawals', 'risk', 'interventions', 'games']" :key="tab" :class="['tab', { active: activeTab === tab }]" @click="selectTab(tab)">{{ tab[0].toUpperCase() + tab.slice(1) }}</button>
            </div>

            <div v-if="activeTab === 'users'" class="table-wrapper"><table><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead><tbody>
                <tr v-for="user in users" :key="user.id">
                    <td class="font-mono text-xs">{{ user.id }}</td><td class="font-semibold" style="color:var(--text-primary)">{{ user.name }}</td><td class="font-mono text-xs">{{ user.email }}</td>
                    <td><span :class="['badge', user.status === 'active' ? 'badge-green' : 'badge-red']">{{ user.status }}</span></td><td class="text-xs">{{ new Date(user.created_at).toLocaleDateString() }}</td>
                    <td class="flex gap-2"><button class="btn btn-gold btn-sm" @click="openUserInterventions(user.id)">Limits</button><button v-if="user.status === 'active'" class="btn btn-ghost btn-sm" @click="disableUser(user.id)">Disable</button><button class="btn btn-danger btn-sm" @click="deleteUser(user.id)">Delete</button></td>
                </tr>
            </tbody></table></div>

            <div v-if="activeTab === 'withdrawals'" class="table-wrapper"><table><thead><tr><th>ID</th><th>User</th><th>Amount</th><th>Status</th><th>Address</th><th>Date</th><th>Actions</th></tr></thead><tbody>
                <tr v-if="withdrawals.length === 0"><td colspan="7" class="text-center py-4" style="color:var(--text-muted)">No pending withdrawals</td></tr>
                <tr v-for="withdrawal in withdrawals" :key="withdrawal.id">
                    <td class="font-mono text-xs">{{ withdrawal.id }}</td><td class="text-xs">User #{{ withdrawal.user_id }}</td><td class="font-bold gold-text">${{ money(withdrawal.amount) }}</td><td><span class="badge badge-yellow">{{ withdrawal.status }}</span></td><td class="font-mono text-xs truncate" style="max-width:150px">{{ withdrawal.meta?.destination || '--' }}</td><td class="text-xs">{{ new Date(withdrawal.created_at).toLocaleString() }}</td>
                    <td class="flex gap-2"><button class="btn btn-gold btn-sm" @click="approveWithdrawal(withdrawal.id)">Approve</button><button class="btn btn-ghost btn-sm" @click="rejectWithdrawal(withdrawal.id)">Reject</button></td>
                </tr>
            </tbody></table></div>

            <div v-if="activeTab === 'risk'">
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
