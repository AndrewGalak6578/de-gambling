<script setup>
import { onMounted, reactive, ref } from 'vue';
import AppLayout from '../components/AppLayout.vue';
import PageHeader from '../components/PageHeader.vue';
import { api } from '../services/api';
import { showToast } from '../services/toast';
import { money } from '../utils/format';

const balance = ref('0.00');
const deposit = reactive({ amount_usd: '50', coin: 'btc', loading: false, result: null });
const withdraw = reactive({ amount: '10', destination: '', provider_method: 'crypto', loading: false, result: null });

onMounted(load);

async function load() {
    const wallet = await api('/wallet');
    balance.value = wallet?.balance || '0.00';
}

async function handleDeposit() {
    deposit.loading = true;
    const data = await api('/wallet/deposit', { method: 'POST', body: JSON.stringify({ amount_usd: deposit.amount_usd, coin: deposit.coin }) });
    deposit.loading = false;
    deposit.result = data;
    if (!data || data._status) return showToast(data?.message || 'Deposit failed.', 'error');
    showToast('Invoice created!', 'success');
}

async function handleWithdraw() {
    if (!withdraw.destination) return showToast('Address required.', 'error');
    withdraw.loading = true;
    const data = await api('/wallet/withdraw', { method: 'POST', body: JSON.stringify({ amount: withdraw.amount, destination: withdraw.destination, provider_method: withdraw.provider_method }) });
    withdraw.loading = false;
    withdraw.result = data;
    if (!data || data._status) return showToast(data?.message || 'Withdrawal failed.', 'error');
    await load();
    showToast('Payout requested!', 'info');
}
</script>

<template>
    <AppLayout>
        <PageHeader title="Wallet" subtitle="Manage your funds" />
        <div class="stats-grid"><div class="stat-card"><div class="stat-label">Available Balance</div><div class="stat-value gold-text">${{ money(balance) }}</div><div class="stat-sub">USD</div></div></div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="card">
                <h3 class="font-bold mb-4">Deposit Funds</h3>
                <div class="space-y-4">
                    <div><label class="label">Amount (USD)</label><input v-model="deposit.amount_usd" type="number" class="input" min="0.01" step="0.01"></div>
                    <div><label class="label">Cryptocurrency</label><select v-model="deposit.coin" class="select"><option value="btc">Bitcoin (BTC)</option><option value="eth">Ethereum (ETH)</option><option value="usdt">Tether (USDT)</option></select></div>
                    <button class="btn btn-gold w-full" :disabled="deposit.loading" @click="handleDeposit">{{ deposit.loading ? 'Processing...' : 'Generate Invoice' }}</button>
                    <div v-if="deposit.result" class="mt-3 p-3 rounded-lg" style="background:var(--surface-1);border:1px solid var(--border-subtle)">
                        <p v-if="deposit.result._status" class="text-sm text-red-400 mt-2 font-semibold">{{ deposit.result.message || 'Deposit failed.' }}</p>
                        <template v-else>
                            <p class="text-sm font-semibold text-green-400 mb-2">Invoice Created</p>
                            <p class="text-xs" style="color:var(--text-muted)">Send: <strong style="color:var(--text-primary)">{{ deposit.result.deposit_invoice.amount_coin }} {{ deposit.result.deposit_invoice.coin.toUpperCase() }}</strong></p>
                            <p class="text-xs font-mono p-2 mt-2 rounded select-all break-all" style="background:var(--surface-0);border:1px solid var(--border-subtle);color:var(--text-primary)">{{ deposit.result.deposit_invoice.pay_address }}</p>
                            <p class="text-xs mt-2" style="color:var(--text-muted)">Status: <span class="badge badge-yellow">{{ deposit.result.deposit_invoice.status }}</span></p>
                            <p class="text-xs mt-1" style="color:var(--text-muted)">Expires: {{ new Date(deposit.result.deposit_invoice.expires_at).toLocaleString() }}</p>
                        </template>
                    </div>
                </div>
            </div>
            <div class="card">
                <h3 class="font-bold mb-4">Request Withdrawal</h3>
                <div class="space-y-4">
                    <div><label class="label">Amount (USD)</label><input v-model="withdraw.amount" type="number" class="input" min="0.01" step="0.01"></div>
                    <div><label class="label">Recipient Address</label><input v-model="withdraw.destination" type="text" class="input" placeholder="0x... or 1..."></div>
                    <div><label class="label">Network</label><select v-model="withdraw.provider_method" class="select"><option value="crypto">Crypto</option><option value="bank">Bank Wire</option></select></div>
                    <button class="btn btn-ghost w-full" :disabled="withdraw.loading" @click="handleWithdraw">{{ withdraw.loading ? 'Processing...' : 'Request Payout' }}</button>
                    <div v-if="withdraw.result" class="mt-3 p-3 rounded-lg" style="background:var(--surface-1);border:1px solid var(--border-subtle)">
                        <p v-if="withdraw.result._status" class="text-sm text-red-400 mt-2 font-semibold">{{ withdraw.result.message || 'Withdrawal failed.' }}</p>
                        <template v-else>
                            <p class="text-sm font-semibold text-yellow-400">Payout Pending</p>
                            <p class="text-xs mt-1" style="color:var(--text-secondary)">Amount: <strong class="gold-text">${{ money(withdraw.result.withdrawal.amount) }}</strong></p>
                            <p class="text-xs" style="color:var(--text-muted)">Status: <span class="badge badge-yellow">{{ withdraw.result.withdrawal.status }}</span></p>
                        </template>
                    </div>
                </div>
                <p class="text-xs mt-4" style="color:var(--text-muted)">Withdrawals require admin approval.</p>
            </div>
        </div>
    </AppLayout>
</template>

