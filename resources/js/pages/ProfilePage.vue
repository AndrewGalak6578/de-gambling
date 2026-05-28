<script setup>
import { onMounted, reactive, ref } from 'vue';
import AppLayout from '../components/AppLayout.vue';
import PageHeader from '../components/PageHeader.vue';
import Spinner from '../components/Spinner.vue';
import { api } from '../services/api';
import { showToast } from '../services/toast';
import { session } from '../state/session';
import { navigate } from '../state/router';

const loading = ref(true);
const selfExcluded = ref(false);
const selfExclusion = ref(null);
const profile = reactive({ name: '', email: '', loading: false });
const password = reactive({ current_password: '', password: '', password_confirmation: '', loading: false });
const limits = reactive({ daily_deposit_limit: '', daily_bet_limit: '', daily_loss_limit: '', loading: false });
const exclusion = reactive({ days: 30, reason: '', loading: false });

onMounted(load);

async function load() {
    const [restrictionsData, selfExclData] = await Promise.all([api('/user/restrictions'), api('/user/self-exclusion')]);
    const restrictions = restrictionsData?.restrictions || {};

    profile.name = session.user?.name || '';
    profile.email = session.user?.email || '';
    limits.daily_deposit_limit = restrictions.daily_deposit_limit || '';
    limits.daily_bet_limit = restrictions.daily_bet_limit || '';
    limits.daily_loss_limit = restrictions.daily_loss_limit || '';
    selfExcluded.value = Boolean(selfExclData?.self_excluded);
    selfExclusion.value = selfExclData?.intervention || null;
    loading.value = false;
}

async function updateProfile() {
    profile.loading = true;
    const data = await api('/user/profile', { method: 'PATCH', body: JSON.stringify({ name: profile.name, email: profile.email }) });
    profile.loading = false;
    if (data && !data._status) {
        session.user = data.user;
        showToast('Profile updated!', 'success');
    } else showToast(data?.message || 'Update failed.', 'error');
}

async function updatePassword() {
    if (password.password !== password.password_confirmation) return showToast('Passwords do not match.', 'error');
    password.loading = true;
    const data = await api('/user/password', { method: 'PATCH', body: JSON.stringify(password) });
    password.loading = false;
    if (data && !data._status) {
        showToast('Password changed!', 'success');
        password.current_password = '';
        password.password = '';
        password.password_confirmation = '';
    } else showToast(data?.message || 'Failed.', 'error');
}

async function updateLimits() {
    limits.loading = true;
    const body = {};
    if (limits.daily_deposit_limit) body.daily_deposit_limit = limits.daily_deposit_limit;
    if (limits.daily_bet_limit) body.daily_bet_limit = limits.daily_bet_limit;
    if (limits.daily_loss_limit) body.daily_loss_limit = limits.daily_loss_limit;
    const data = await api('/user/restrictions', { method: 'PATCH', body: JSON.stringify(body) });
    limits.loading = false;
    if (data && !data._status) showToast('Limits saved!', 'success');
    else showToast(data?.message || 'Failed.', 'error');
}

async function selfExclude() {
    exclusion.loading = true;
    const data = await api('/user/self-exclusion', { method: 'POST', body: JSON.stringify({ days: Number(exclusion.days), reason: exclusion.reason }) });
    exclusion.loading = false;
    if (data && !data._status) {
        showToast('Account suspended.', 'info');
        navigate('profile');
        await load();
    } else showToast(data?.message || 'Failed.', 'error');
}
</script>

<template>
    <AppLayout>
        <Spinner v-if="loading" />
        <template v-else>
            <PageHeader title="Settings" subtitle="Account and responsible gaming" />
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <form class="card" @submit.prevent="updateProfile">
                    <h3 class="font-bold mb-4">Profile</h3>
                    <div class="space-y-4">
                        <div><label class="label">Name</label><input v-model="profile.name" type="text" class="input"></div>
                        <div><label class="label">Email</label><input v-model="profile.email" type="email" class="input"></div>
                        <button class="btn btn-gold w-full" :disabled="profile.loading">{{ profile.loading ? 'Saving...' : 'Update Profile' }}</button>
                    </div>
                </form>
                <form class="card" @submit.prevent="updatePassword">
                    <h3 class="font-bold mb-4">Security</h3>
                    <div class="space-y-4">
                        <div><label class="label">Current Password</label><input v-model="password.current_password" type="password" class="input"></div>
                        <div><label class="label">New Password</label><input v-model="password.password" type="password" class="input"></div>
                        <div><label class="label">Confirm New Password</label><input v-model="password.password_confirmation" type="password" class="input"></div>
                        <button class="btn btn-ghost w-full" :disabled="password.loading">{{ password.loading ? 'Updating...' : 'Change Password' }}</button>
                    </div>
                </form>
                <form class="card" @submit.prevent="updateLimits">
                    <h3 class="font-bold mb-4">Responsible Gaming</h3>
                    <div class="space-y-4">
                        <div><label class="label">Daily Deposit Limit ($)</label><input v-model="limits.daily_deposit_limit" type="number" class="input" placeholder="No limit" min="0"></div>
                        <div><label class="label">Daily Bet Limit ($)</label><input v-model="limits.daily_bet_limit" type="number" class="input" placeholder="No limit" min="0"></div>
                        <div><label class="label">Daily Loss Limit ($)</label><input v-model="limits.daily_loss_limit" type="number" class="input" placeholder="No limit" min="0"></div>
                        <button class="btn btn-gold w-full" :disabled="limits.loading">{{ limits.loading ? 'Saving...' : 'Save Limits' }}</button>
                    </div>
                </form>
                <div class="card card-gold">
                    <h3 class="font-bold mb-4">Self-Exclusion</h3>
                    <div v-if="selfExcluded" class="p-3 rounded-lg" style="background:var(--surface-1);border:1px solid var(--border-gold)">
                        <p class="text-sm font-semibold text-yellow-400">Self-Exclusion Active</p>
                        <p class="text-xs mt-1" style="color:var(--text-secondary)">Until: <strong>{{ new Date(selfExclusion.ends_at).toLocaleDateString() }}</strong></p>
                        <p v-if="selfExclusion.payload?.reason" class="text-xs mt-1" style="color:var(--text-muted)">Reason: {{ selfExclusion.payload.reason }}</p>
                    </div>
                    <form v-else class="space-y-4" @submit.prevent="selfExclude">
                        <p class="text-sm mb-4" style="color:var(--text-secondary)">Temporarily suspend your account.</p>
                        <div><label class="label">Period (days, 1-365)</label><input v-model="exclusion.days" type="number" class="input" min="1" max="365"></div>
                        <div><label class="label">Reason</label><input v-model="exclusion.reason" type="text" class="input" placeholder="Optional"></div>
                        <button class="btn btn-danger w-full" :disabled="exclusion.loading">{{ exclusion.loading ? 'Processing...' : 'Suspend Account' }}</button>
                    </form>
                </div>
            </div>
        </template>
    </AppLayout>
</template>

