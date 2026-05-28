<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import AppIcon from '../components/AppIcon.vue';
import AppLayout from '../components/AppLayout.vue';
import Spinner from '../components/Spinner.vue';
import { api } from '../services/api';
import { showToast } from '../services/toast';
import { navigate } from '../state/router';
import { money, normalizeCollection, randomSeed } from '../utils/format';

const props = defineProps({ gameId: { type: String, default: '' } });

const loading = ref(true);
const game = ref(null);
const balance = ref('0.00');
const history = ref([]);
const currentRotation = ref(0);
const dice = reactive({ target: 50, condition: 'under', amount: '10.00', seed: randomSeed(), roll: '50.00', result: 'Ready to roll', resultColor: 'var(--text-muted)', marker: 50, proof: null, rolling: false });
const spin = reactive({ sector: 1, amount: '10.00', seed: randomSeed(), result: '1', status: 'Place your bet', resultColor: '', proof: null, spinning: false });
const isDice = computed(() => game.value?.slug === 'dice');
const sectors = computed(() => game.value?.config?.sectors || 8);
const sectorNumbers = computed(() => Array.from({ length: sectors.value }, (_, index) => index + 1));
const greenStyle = computed(() => dice.condition === 'under' ? { left: '0%', width: `${dice.target}%` } : { left: `${dice.target}%`, width: `${100 - dice.target}%` });
const redStyle = computed(() => dice.condition === 'under' ? { left: `${dice.target}%`, width: `${100 - dice.target}%` } : { left: '0%', width: `${dice.target}%` });

watch(() => props.gameId, load);
onMounted(load);

async function load() {
    loading.value = true;
    const [gamesData, walletData] = await Promise.all([api('/games'), api('/wallet')]);
    const games = normalizeCollection(gamesData);
    game.value = games.find((item) => item.slug === props.gameId) || games[0] || null;
    balance.value = walletData?.balance || '0.00';
    history.value = [];
    currentRotation.value = 0;
    Object.assign(dice, { target: 50, condition: 'under', amount: '10.00', seed: randomSeed(), roll: '50.00', result: 'Ready to roll', resultColor: 'var(--text-muted)', marker: 50, proof: null, rolling: false });
    Object.assign(spin, { sector: 1, amount: '10.00', seed: randomSeed(), result: '1', status: 'Place your bet', resultColor: '', proof: null, spinning: false });
    loading.value = false;
}

async function refreshBalance() {
    const wallet = await api('/wallet');
    if (wallet) balance.value = wallet.balance;
}

function addHistory(text, amount, isWin) {
    history.value.unshift({ id: Date.now(), text, amount, isWin });
    history.value = history.value.slice(0, 12);
}

async function placeDiceBet() {
    dice.rolling = true;
    const rollInterval = setInterval(() => {
        dice.roll = (Math.random() * 100).toFixed(2);
        dice.resultColor = 'var(--text-muted)';
    }, 50);

    const data = await api(`/games/${game.value.id}/bet`, {
        method: 'POST',
        body: JSON.stringify({ bet_amount: dice.amount, client_seed: dice.seed, payload: { target: Number(dice.target), condition: dice.condition } }),
    });

    clearInterval(rollInterval);
    dice.rolling = false;
    if (!data || data._status) return showToast(data?.message || 'Bet failed.', 'error');

    const roll = data.outcome?.state?.roll;
    const isWin = data.outcome?.state?.is_win;
    dice.roll = roll !== undefined ? roll.toFixed(2) : '--';
    dice.marker = roll || 0;
    dice.result = isWin ? `Win! +$${money(data.bet.payout_amount)}` : 'Lost';
    dice.resultColor = isWin ? '#4ade80' : '#f87171';
    dice.proof = data.bet.result?.server_seed ? data.bet : null;
    addHistory(`#${data.bet.id} Roll: ${roll?.toFixed(2)}`, isWin ? `+$${money(data.bet.payout_amount)}` : `-$${money(dice.amount)}`, isWin);
    await refreshBalance();
}

async function placeSpinBet() {
    spin.spinning = true;
    currentRotation.value += 1800;
    const data = await api(`/games/${game.value.id}/bet`, {
        method: 'POST',
        body: JSON.stringify({ bet_amount: spin.amount, client_seed: spin.seed, payload: { sector: Number(spin.sector) } }),
    });

    if (!data || data._status) {
        spin.spinning = false;
        return showToast(data?.message || 'Spin failed.', 'error');
    }

    const state = data.outcome?.state;
    const isWin = state?.is_win;
    const landed = state?.landed_sector || 1;
    const sectorAngle = 360 / sectors.value;
    const targetOffset = 360 - ((landed - 1) * sectorAngle + (sectorAngle / 2));
    currentRotation.value = Math.ceil(currentRotation.value / 360) * 360 + targetOffset + 1440;

    setTimeout(async () => {
        spin.spinning = false;
        spin.result = `Sector ${landed}`;
        spin.status = isWin ? `Win! +$${money(data.bet.payout_amount)}` : `Lost. Picked ${state?.player_sector}, landed ${landed}`;
        spin.resultColor = isWin ? '#4ade80' : '#f87171';
        spin.proof = data.bet.result?.server_seed ? data.bet : null;
        addHistory(`#${data.bet.id} Sector ${landed}`, isWin ? `+$${money(data.bet.payout_amount)}` : `-$${money(spin.amount)}`, isWin);
        await refreshBalance();
    }, 4600);
}
</script>

<template>
    <AppLayout>
        <Spinner v-if="loading" />
        <div v-else-if="!game" class="empty-state"><p>Game not found.</p></div>
        <template v-else>
            <div class="flex items-center gap-3 mb-6">
                <button class="btn btn-ghost btn-icon" @click="navigate('games')"><AppIcon name="back" /></button>
                <h1 :class="['text-xl font-bold', isDice ? 'gold-text' : 'silver-text']">{{ game.name }}</h1>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2">
                    <div :class="['card', isDice ? 'card-gold' : 'card-silver']">
                        <template v-if="isDice">
                            <div class="text-center mb-6">
                                <div class="roll-display gold-text" :style="{ color: dice.resultColor }">{{ dice.roll }}</div>
                                <div class="text-sm font-semibold mt-1" :style="{ minHeight: '1.5rem', color: dice.resultColor }">{{ dice.result }}</div>
                            </div>
                            <div class="dice-slider-container">
                                <div class="dice-slider-track">
                                    <div class="dice-slider-zone dice-slider-zone-green absolute top-0 bottom-0" :style="greenStyle"></div>
                                    <div class="dice-slider-zone dice-slider-zone-red absolute top-0 bottom-0" :style="redStyle"></div>
                                </div>
                                <div class="dice-slider-marker" :style="{ left: `${dice.marker}%` }"></div>
                            </div>
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div><label class="label">Target (1-99)</label><input v-model.number="dice.target" type="number" min="1" max="99" class="input"></div>
                                <div><label class="label">Condition</label><select v-model="dice.condition" class="select"><option value="under">Under</option><option value="over">Over</option></select></div>
                            </div>
                            <div class="grid grid-cols-2 gap-4 mb-6">
                                <div><label class="label">Bet Amount ($)</label><input v-model="dice.amount" type="number" min="0.10" max="5000" step="1.00" class="input"></div>
                                <div><label class="label">Client Seed</label><input v-model="dice.seed" type="text" class="input font-mono text-xs"></div>
                            </div>
                            <button class="btn btn-gold w-full py-3" :disabled="dice.rolling" @click="placeDiceBet">{{ dice.rolling ? 'Rolling...' : 'Roll Dice' }}</button>
                            <div v-if="dice.proof" class="p-3 rounded-lg text-xs font-mono break-all mt-4" style="background:var(--surface-1);border:1px solid var(--border-subtle);color:var(--text-muted);line-height:1.6">Server: <span style="color:var(--text-primary)">{{ dice.proof.result.server_seed }}</span><br>Client: <span style="color:var(--text-primary)">{{ dice.proof.client_seed }}</span><br>Hash: <span style="color:var(--text-primary)">{{ dice.proof.server_seed_hash }}</span></div>
                        </template>
                        <template v-else>
                            <div class="text-center mb-2">
                                <div class="roll-display silver-text" :style="{ color: spin.resultColor }">{{ spin.result }}</div>
                                <div class="text-sm font-semibold mt-1" :style="{ minHeight: '1.5rem', color: spin.resultColor || 'var(--text-muted)' }">{{ spin.status }}</div>
                            </div>
                            <div class="wheel-outer">
                                <div class="wheel-pointer"></div>
                                <div class="spin-wheel" :style="{ transform: `rotate(${currentRotation}deg)` }">
                                    <div v-for="num in sectorNumbers" :key="num" class="wheel-sector-num" :style="{ '--angle': `${(num - 1) * (360 / sectors) + (180 / sectors)}deg` }">{{ num }}</div>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="label">Target Sector (1-{{ sectors }})</label>
                                <div class="flex gap-2 flex-wrap mb-3"><button v-for="num in sectorNumbers" :key="num" class="sector-btn" @click="spin.sector = num">{{ num }}</button></div>
                                <input v-model.number="spin.sector" type="number" min="1" :max="sectors" class="input">
                            </div>
                            <div class="grid grid-cols-2 gap-4 mb-6">
                                <div><label class="label">Bet Amount ($)</label><input v-model="spin.amount" type="number" min="0.10" max="5000" step="1.00" class="input"></div>
                                <div><label class="label">Client Seed</label><input v-model="spin.seed" type="text" class="input font-mono text-xs"></div>
                            </div>
                            <button class="btn btn-silver w-full py-3" :disabled="spin.spinning" @click="placeSpinBet">{{ spin.spinning ? 'Spinning...' : 'Spin Wheel' }}</button>
                            <div v-if="spin.proof" class="p-3 rounded-lg text-xs font-mono break-all mt-4" style="background:var(--surface-1);border:1px solid var(--border-subtle);color:var(--text-muted);line-height:1.6">Server: <span style="color:var(--text-primary)">{{ spin.proof.result.server_seed }}</span><br>Client: <span style="color:var(--text-primary)">{{ spin.proof.client_seed }}</span></div>
                        </template>
                    </div>
                </div>
                <div>
                    <div class="card mb-4"><div class="stat-label">Balance</div><div class="text-2xl font-black gold-text mt-1">${{ money(balance) }}</div></div>
                    <div class="card"><div class="stat-label mb-3">Recent Bets</div><div class="space-y-3 text-xs font-mono max-h-60 overflow-y-auto">
                        <div v-for="item in history" :key="item.id" :class="['p-3 rounded flex items-center justify-between border-l-4', item.isWin ? 'border-green-500 text-green-400' : 'border-red-500 text-red-400']" style="background:var(--surface-1)"><span>{{ item.text }}</span><span>{{ item.amount }}</span></div>
                    </div></div>
                </div>
            </div>
        </template>
    </AppLayout>
</template>
