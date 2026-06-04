<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import AppIcon from '../components/AppIcon.vue';
import AppLayout from '../components/AppLayout.vue';
import Spinner from '../components/Spinner.vue';
import { api } from '../services/api';
import { showToast } from '../services/toast';
import { navigate } from '../state/router';
import { money, normalizeCollection, randomSeed } from '../utils/format';

const props = defineProps({ gameId: { type: String, default: '' } });

const SLOT_SYMBOLS = {
    cherry: { icon: '🍒', label: 'Cherry', payout: 10 },
    lemon: { icon: '🍋', label: 'Lemon', payout: 16 },
    bell: { icon: '🔔', label: 'Bell', payout: 27 },
    star: { icon: '⭐', label: 'Star', payout: 55 },
    diamond: { icon: '💎', label: 'Diamond', payout: 110 },
    seven: { icon: '7️⃣', label: 'Seven', payout: 270 },
    crown: { icon: '👑', label: 'Crown', payout: 1300 },
};
const SLOT_SYMBOL_KEYS = ['cherry', 'lemon', 'bell', 'star', 'diamond', 'seven', 'crown'];
const SLOT_REEL_LENGTH = 25;
const SLOT_SYMBOL_HEIGHT = 120;
const SLOT_DURATIONS = [1.4, 1.9, 2.5];

const loading = ref(true);
const game = ref(null);
const balance = ref('0.00');
const history = ref([]);
const currentRotation = ref(0);
const burstId = ref(0);
const dice = reactive({ target: 50, condition: 'under', amount: '10.00', seed: randomSeed(), roll: '50.00', result: 'Ready to roll', resultColor: 'var(--text-muted)', marker: 50, proof: null, rolling: false, state: '' });
const spin = reactive({ sector: 1, amount: '10.00', seed: randomSeed(), result: '1', status: 'Place your bet', resultColor: '', proof: null, spinning: false });
const slots = reactive({ amount: '10.00', seed: randomSeed(), message: 'Pull the lever', messageColor: 'var(--text-muted)', multiplier: '', proof: null, busy: false, landed: false, reels: [['🎰'], ['🎰'], ['🎰']], winning: false });

let diceInterval = null;
let spinTimer = null;
let slotTimer = null;
let burstTimer = null;

const isDice = computed(() => game.value?.slug === 'dice');
const isSlots = computed(() => game.value?.slug === 'slots');
const sectors = computed(() => game.value?.config?.sectors || 8);
const sectorNumbers = computed(() => Array.from({ length: sectors.value }, (_, index) => index + 1));
const diceTarget = computed(() => clamp(Number(dice.target), 1, 99));
const diceMarker = computed(() => clamp(Number(dice.marker), 0, 100));
const spinSector = computed(() => clamp(Number(spin.sector), 1, sectors.value));
const accent = computed(() => game.value?.slug === 'spin-to-win' ? 'silver' : 'gold');
const gameCardClass = computed(() => game.value?.slug === 'spin-to-win' ? 'card-silver' : 'card-gold');
const gameEmoji = computed(() => game.value?.slug === 'dice' ? '🎲' : game.value?.slug === 'slots' ? '🎰' : game.value?.slug === 'spin-to-win' ? '🎡' : '🎮');
const greenStyle = computed(() => dice.condition === 'under' ? { left: '0%', width: `${diceTarget.value}%` } : { left: `${diceTarget.value}%`, width: `${100 - diceTarget.value}%` });
const redStyle = computed(() => dice.condition === 'under' ? { left: `${diceTarget.value}%`, width: `${100 - diceTarget.value}%` } : { left: '0%', width: `${diceTarget.value}%` });
const diceStats = computed(() => {
    const rtp = Number(game.value?.rtp_percentage || 95);
    const amount = Number(dice.amount) || 0;
    const winBase = dice.condition === 'under' ? diceTarget.value : 100 - diceTarget.value;
    const winChance = winBase * (rtp / 100);
    const multiplier = (100 / winBase) * (rtp / 100);
    return { winChance: `${winChance.toFixed(2)}%`, multiplier: `${multiplier.toFixed(4)}×`, onWin: `+$${money(amount * multiplier)}` };
});
const sparks = computed(() => Array.from({ length: 24 }, (_, index) => {
    const angle = (index / 24) * 2 * Math.PI + Math.random() * 0.4;
    const distance = 80 + Math.random() * 120;
    return { id: `${burstId.value}-${index}`, dx: `${Math.cos(angle) * distance}px`, dy: `${Math.sin(angle) * distance}px`, delay: `${Math.random() * 0.15}s` };
}));
const showBurst = computed(() => burstId.value > 0);

watch(() => props.gameId, load);
onMounted(load);
onBeforeUnmount(clearTimers);

function clearTimers() {
    if (diceInterval) clearInterval(diceInterval);
    if (spinTimer) clearTimeout(spinTimer);
    if (slotTimer) clearTimeout(slotTimer);
    if (burstTimer) clearTimeout(burstTimer);
}

function clamp(value, min, max) {
    if (!Number.isFinite(value)) return min;
    return Math.min(max, Math.max(min, value));
}

function validAmount(value) {
    const amount = Number(value);
    return Number.isFinite(amount) && amount >= 0.1 && amount <= 5000;
}

async function load() {
    clearTimers();
    loading.value = true;
    const [gamesData, walletData] = await Promise.all([api('/games'), api('/wallet')]);
    const games = normalizeCollection(gamesData);
    game.value = games.find((item) => item.slug === props.gameId) || games[0] || null;
    balance.value = walletData?.balance || '0.00';
    currentRotation.value = 0;
    history.value = [];
    resetGameState();
    loading.value = false;
}

function resetGameState() {
    Object.assign(dice, { target: 50, condition: 'under', amount: '10.00', seed: randomSeed(), roll: '50.00', result: 'Ready to roll', resultColor: 'var(--text-muted)', marker: 50, proof: null, rolling: false, state: '' });
    Object.assign(spin, { sector: 1, amount: '10.00', seed: randomSeed(), result: '1', status: 'Place your bet', resultColor: '', proof: null, spinning: false });
    Object.assign(slots, { amount: '10.00', seed: randomSeed(), message: 'Pull the lever', messageColor: 'var(--text-muted)', multiplier: '', proof: null, busy: false, landed: false, reels: [['🎰'], ['🎰'], ['🎰']], winning: false });
    burstId.value = 0;
}

async function refreshBalance() {
    const wallet = await api('/wallet');
    if (wallet) balance.value = wallet.balance;
}

function setBetAmount(target, amount) {
    target.amount = amount.toFixed(2);
}

function scaleBetAmount(target, factor) {
    target.amount = Math.max(0.1, (Number(target.amount) || 1) * factor).toFixed(2);
}

function addBetToHistory(bet, isWin, amount, text) {
    history.value.unshift({ id: `${bet.id}-${Date.now()}`, text: `#${bet.id} ${text}`, amount: isWin ? `+$${money(bet.payout_amount)}` : `-$${money(amount)}`, isWin });
    history.value = history.value.slice(0, 12);
}

function spawnWinBurst() {
    burstId.value += 1;
    if (burstTimer) clearTimeout(burstTimer);
    burstTimer = setTimeout(() => { burstId.value = 0; }, 1300);
}

async function placeDiceBet() {
    if (dice.rolling || !game.value) return;
    if (!validAmount(dice.amount)) return showToast('Bet amount must be between $0.10 and $5000.', 'error');
    if (!dice.seed?.trim()) return showToast('Client seed required.', 'error');

    dice.target = diceTarget.value;
    dice.rolling = true;
    dice.state = '';
    dice.result = 'Rolling...';
    dice.resultColor = 'var(--text-muted)';
    dice.proof = null;
    burstId.value = 0;
    diceInterval = setInterval(() => { dice.roll = (Math.random() * 100).toFixed(2); }, 60);

    const data = await api(`/games/${game.value.id}/bet`, {
        method: 'POST',
        body: JSON.stringify({ bet_amount: dice.amount, client_seed: dice.seed.trim(), payload: { target: diceTarget.value, condition: dice.condition } }),
    });

    clearInterval(diceInterval);
    diceInterval = null;
    dice.rolling = false;

    if (!data || data._status) {
        dice.result = 'Bet failed';
        dice.resultColor = '#f87171';
        dice.roll = '--';
        return showToast(data?.message || 'Bet failed.', 'error');
    }

    const roll = data.outcome?.state?.roll;
    const isWin = data.outcome?.state?.is_win;
    dice.roll = roll !== undefined ? roll.toFixed(2) : '--';
    dice.marker = clamp(Number(roll), 0, 100);
    dice.state = isWin ? 'win' : 'loss';
    dice.result = isWin ? 'Win!' : 'Lost';
    dice.resultColor = isWin ? '#4ade80' : '#f87171';
    dice.proof = data.bet.result?.server_seed ? data.bet : null;
    if (isWin) spawnWinBurst();
    setTimeout(() => { dice.state = ''; }, 2200);
    addBetToHistory(data.bet, isWin, dice.amount, `Roll: ${roll?.toFixed(2)}`);
    await refreshBalance();
}

async function placeSpinBet() {
    if (spin.spinning || !game.value) return;
    if (!validAmount(spin.amount)) return showToast('Bet amount must be between $0.10 and $5000.', 'error');
    if (!spin.seed?.trim()) return showToast('Client seed required.', 'error');

    spin.sector = spinSector.value;
    spin.spinning = true;
    currentRotation.value += 1800;

    const data = await api(`/games/${game.value.id}/bet`, {
        method: 'POST',
        body: JSON.stringify({ bet_amount: spin.amount, client_seed: spin.seed.trim(), payload: { sector: spinSector.value } }),
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

    spinTimer = setTimeout(async () => {
        spin.spinning = false;
        spin.result = `Sector ${landed}`;
        spin.resultColor = isWin ? '#4ade80' : '#f87171';
        spin.status = isWin ? `Win! +$${money(data.bet.payout_amount)}` : `Lost. Picked ${state?.player_sector}, landed ${landed}`;
        spin.proof = data.bet.result?.server_seed ? data.bet : null;
        addBetToHistory(data.bet, isWin, spin.amount, `Sector ${landed}`);
        await refreshBalance();
    }, 4500);
}

function randomReelSymbols() {
    return Array.from({ length: SLOT_REEL_LENGTH }, () => SLOT_SYMBOLS[SLOT_SYMBOL_KEYS[Math.floor(Math.random() * SLOT_SYMBOL_KEYS.length)]].icon);
}

function buildReelStrip(targetSymbolKey) {
    const symbols = [];
    for (let index = 0; index < SLOT_REEL_LENGTH - 1; index += 1) {
        symbols.push(SLOT_SYMBOL_KEYS[Math.floor(Math.random() * SLOT_SYMBOL_KEYS.length)]);
    }
    symbols.push(targetSymbolKey);
    return symbols.map((key) => SLOT_SYMBOLS[key]?.icon || SLOT_SYMBOLS.cherry.icon);
}

async function placeSlotBet() {
    if (slots.busy || !game.value) return;
    if (!validAmount(slots.amount)) return showToast('Bet amount must be between $0.10 and $5000.', 'error');
    if (!slots.seed?.trim()) return showToast('Client seed required.', 'error');

    slots.busy = true;
    slots.winning = false;
    slots.landed = false;
    slots.message = 'Spinning...';
    slots.messageColor = 'var(--text-muted)';
    slots.multiplier = '';
    slots.proof = null;
    burstId.value = 0;
    slots.reels = [randomReelSymbols(), randomReelSymbols(), randomReelSymbols()];
    await nextTick();

    const data = await api(`/games/${game.value.id}/bet`, {
        method: 'POST',
        body: JSON.stringify({ bet_amount: slots.amount, client_seed: slots.seed.trim(), payload: {} }),
    });

    if (!data || data._status) {
        slots.busy = false;
        slots.message = 'Spin failed';
        slots.messageColor = '#f87171';
        return showToast(data?.message || 'Spin failed.', 'error');
    }

    const reels = data.outcome?.state?.reels || ['cherry', 'lemon', 'bell'];
    const matchType = data.outcome?.state?.match_type;
    const isWin = data.outcome?.state?.is_win;
    slots.reels = reels.map(buildReelStrip);
    await nextTick();
    requestAnimationFrame(() => { slots.landed = true; });

    slotTimer = setTimeout(async () => {
        slots.busy = false;
        if (matchType === 'three_of_a_kind') {
            slots.winning = true;
            slots.message = `Three of a kind — ${SLOT_SYMBOLS[reels[0]]?.label || reels[0]}!`;
            slots.messageColor = '#4ade80';
            slots.multiplier = `+$${money(data.bet.payout_amount)}`;
            spawnWinBurst();
        } else if (matchType === 'cherry_consolation') {
            slots.message = 'Cherry consolation';
            slots.messageColor = '#facc15';
            slots.multiplier = `+$${money(data.bet.payout_amount)}`;
        } else {
            slots.message = 'No match';
            slots.messageColor = '#f87171';
            slots.multiplier = `-$${money(slots.amount)}`;
        }
        slots.proof = data.bet.result?.server_seed ? data.bet : null;
        addBetToHistory(data.bet, isWin, slots.amount, reels.map((key) => SLOT_SYMBOLS[key]?.icon || key).join(' '));
        await refreshBalance();
    }, SLOT_DURATIONS[2] * 1000 + 120);
}
</script>

<template>
    <AppLayout>
        <Spinner v-if="loading" />
        <div v-else-if="!game" class="empty-state"><p>Game not found.</p></div>
        <template v-else>
            <div class="flex items-center gap-3 mb-6">
                <button class="btn btn-ghost btn-icon" @click="navigate('games')"><AppIcon name="back" /></button>
                <h1 :class="['text-xl font-bold', accent === 'gold' ? 'gold-text' : 'silver-text']">{{ gameEmoji }} {{ game.name }}</h1>
                <span class="badge badge-green" style="margin-left:auto">{{ game.rtp_percentage }}% RTP</span>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2">
                    <div :class="['card', gameCardClass]">
                        <template v-if="isDice">
                            <div class="dice-stage">
                                <div :class="['dice-cube', { rolling: dice.rolling, win: dice.state === 'win', loss: dice.state === 'loss' }]">
                                    <div class="dice-face"><span>{{ dice.roll }}</span></div>
                                </div>
                                <div class="text-sm font-semibold" :style="{ minHeight: '1.5rem', color: dice.resultColor }">{{ dice.result }}</div>
                                <div style="min-height:3rem">
                                    <div v-if="dice.state === 'win'" class="multiplier-pop">+${{ money(dice.proof?.payout_amount) }}</div>
                                    <div v-else-if="dice.state === 'loss'" class="text-lg text-red-400 font-bold" style="margin-top:.5rem">-${{ money(dice.amount) }}</div>
                                </div>
                                <div v-if="showBurst" class="win-burst">
                                    <div v-for="spark in sparks" :key="spark.id" class="spark" :style="{ '--dx': spark.dx, '--dy': spark.dy, animationDelay: spark.delay }"></div>
                                </div>
                            </div>
                            <div class="dice-slider-container">
                                <div class="dice-slider-track">
                                    <div class="dice-slider-zone dice-slider-zone-green absolute top-0 bottom-0" :style="greenStyle"></div>
                                    <div class="dice-slider-zone dice-slider-zone-red absolute top-0 bottom-0" :style="redStyle"></div>
                                </div>
                                <div class="dice-slider-marker" :style="{ left: `${diceMarker}%` }"></div>
                            </div>
                            <div class="dice-tick-labels"><span>0</span><span>25</span><span>50</span><span>75</span><span>100</span></div>
                            <div class="dice-stats-row">
                                <div class="dice-stat"><div class="dice-stat-label">Win Chance</div><div class="dice-stat-value">{{ diceStats.winChance }}</div></div>
                                <div class="dice-stat"><div class="dice-stat-label">Multiplier</div><div class="dice-stat-value gold-text">{{ diceStats.multiplier }}</div></div>
                                <div class="dice-stat"><div class="dice-stat-label">On Win</div><div class="dice-stat-value">{{ diceStats.onWin }}</div></div>
                            </div>
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div><label class="label">Target (1-99)</label><input v-model.number="dice.target" type="number" min="1" max="99" class="input"></div>
                                <div><label class="label">Condition</label><select v-model="dice.condition" class="select"><option value="under">Under</option><option value="over">Over</option></select></div>
                            </div>
                            <div class="mb-4">
                                <label class="label">Bet Amount ($)</label>
                                <input v-model="dice.amount" type="number" min="0.10" max="5000" step="1.00" class="input">
                                <div class="flex gap-2 mt-2 flex-wrap">
                                    <button class="bet-chip" @click="setBetAmount(dice, 5)">$5</button><button class="bet-chip" @click="setBetAmount(dice, 25)">$25</button><button class="bet-chip" @click="setBetAmount(dice, 100)">$100</button><button class="bet-chip" @click="setBetAmount(dice, 500)">$500</button><button class="bet-chip" @click="scaleBetAmount(dice, 0.5)">½</button><button class="bet-chip" @click="scaleBetAmount(dice, 2)">2×</button>
                                </div>
                            </div>
                            <div class="mb-4"><label class="label">Client Seed</label><input v-model="dice.seed" type="text" class="input font-mono text-xs"></div>
                            <button class="btn btn-gold w-full py-3" :disabled="dice.rolling" @click="placeDiceBet">{{ dice.rolling ? 'Rolling...' : 'Roll Dice' }}</button>
                            <div v-if="dice.proof" class="p-3 rounded-lg text-xs font-mono break-all mt-4" style="background:var(--surface-1);border:1px solid var(--border-subtle);color:var(--text-muted);line-height:1.6">Server: <span style="color:var(--text-primary)">{{ dice.proof.result.server_seed }}</span><br>Client: <span style="color:var(--text-primary)">{{ dice.proof.client_seed }}</span><br>Hash: <span style="color:var(--text-primary)">{{ dice.proof.server_seed_hash }}</span></div>
                        </template>

                        <template v-else-if="isSlots">
                            <div class="slot-machine">
                                <div class="slot-title">Royal Slots</div>
                                <div class="slot-window">
                                    <div v-for="(reel, index) in slots.reels" :key="index" :class="['slot-reel', { winning: slots.winning }]">
                                        <div class="reel-strip" :style="{ transform: slots.landed ? `translateY(-${(SLOT_REEL_LENGTH - 1) * SLOT_SYMBOL_HEIGHT}px)` : 'translateY(0px)', transition: slots.landed ? `transform ${SLOT_DURATIONS[index]}s cubic-bezier(0.18,0.8,0.18,1)` : 'none' }">
                                            <div v-for="(symbol, symbolIndex) in reel" :key="`${index}-${symbolIndex}-${symbol}`" class="reel-symbol">{{ symbol }}</div>
                                        </div>
                                    </div>
                                    <div class="payline"></div>
                                    <div v-if="showBurst" class="win-burst"><div v-for="spark in sparks" :key="spark.id" class="spark" :style="{ '--dx': spark.dx, '--dy': spark.dy, animationDelay: spark.delay }"></div></div>
                                </div>
                                <div class="text-center mt-4">
                                    <div class="text-sm font-semibold" :style="{ minHeight: '1.5rem', color: slots.messageColor }">{{ slots.message }}</div>
                                    <div style="min-height:3rem">
                                        <div v-if="slots.messageColor === '#4ade80'" class="multiplier-pop">{{ slots.multiplier }}</div>
                                        <div v-else-if="slots.multiplier" class="text-lg gold-text font-bold" style="margin-top:.5rem">{{ slots.multiplier }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4 mb-4 mt-3">
                                <div><label class="label">Bet Amount ($)</label><input v-model="slots.amount" type="number" min="0.10" max="5000" step="1.00" class="input"><div class="flex gap-2 mt-2 flex-wrap"><button class="bet-chip" @click="setBetAmount(slots, 5)">$5</button><button class="bet-chip" @click="setBetAmount(slots, 25)">$25</button><button class="bet-chip" @click="setBetAmount(slots, 100)">$100</button><button class="bet-chip" @click="scaleBetAmount(slots, 0.5)">½</button><button class="bet-chip" @click="scaleBetAmount(slots, 2)">2×</button></div></div>
                                <div><label class="label">Client Seed</label><input v-model="slots.seed" type="text" class="input font-mono text-xs"></div>
                            </div>
                            <button class="btn btn-gold w-full py-3" :disabled="slots.busy" @click="placeSlotBet">{{ slots.busy ? 'Spinning...' : 'Spin Reels' }}</button>
                            <div class="mt-4"><div class="text-xs font-semibold mb-2" style="color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">Three of a kind pays</div><div class="paytable"><div v-for="symbol in SLOT_SYMBOLS" :key="symbol.label" class="paytable-cell" :title="symbol.label"><div class="paytable-symbol">{{ symbol.icon }}</div><div class="paytable-payout">{{ symbol.payout }}×</div></div></div></div>
                            <div v-if="slots.proof" class="p-3 rounded-lg text-xs font-mono break-all mt-4" style="background:var(--surface-1);border:1px solid var(--border-subtle);color:var(--text-muted);line-height:1.6">Server: <span style="color:var(--text-primary)">{{ slots.proof.result.server_seed }}</span><br>Client: <span style="color:var(--text-primary)">{{ slots.proof.client_seed }}</span><br>Hash: <span style="color:var(--text-primary)">{{ slots.proof.server_seed_hash }}</span></div>
                        </template>

                        <template v-else>
                            <div class="text-center mb-2"><div class="roll-display silver-text" :style="{ color: spin.resultColor }">{{ spin.result }}</div><div class="text-sm font-semibold mt-1" :style="{ minHeight: '1.5rem', color: spin.resultColor || 'var(--text-muted)' }">{{ spin.status }}</div></div>
                            <div class="wheel-outer"><div class="wheel-pointer"></div><div class="spin-wheel" :style="{ transform: `rotate(${currentRotation}deg)` }"><div v-for="num in sectorNumbers" :key="num" class="wheel-sector-num" :style="{ '--angle': `${(num - 1) * (360 / sectors) + (180 / sectors)}deg` }">{{ num }}</div></div></div>
                            <div class="mb-4"><label class="label">Target Sector (1-{{ sectors }})</label><div class="flex gap-2 flex-wrap mb-3"><button v-for="num in sectorNumbers" :key="num" class="sector-btn" @click="spin.sector = num">{{ num }}</button></div><input v-model.number="spin.sector" type="number" min="1" :max="sectors" class="input"></div>
                            <div class="grid grid-cols-2 gap-4 mb-6"><div><label class="label">Bet Amount ($)</label><input v-model="spin.amount" type="number" min="0.10" max="5000" step="1.00" class="input"></div><div><label class="label">Client Seed</label><input v-model="spin.seed" type="text" class="input font-mono text-xs"></div></div>
                            <button class="btn btn-silver w-full py-3" :disabled="spin.spinning" @click="placeSpinBet">{{ spin.spinning ? 'Spinning...' : 'Spin Wheel' }}</button>
                            <div v-if="spin.proof" class="p-3 rounded-lg text-xs font-mono break-all mt-4" style="background:var(--surface-1);border:1px solid var(--border-subtle);color:var(--text-muted);line-height:1.6">Server: <span style="color:var(--text-primary)">{{ spin.proof.result.server_seed }}</span><br>Client: <span style="color:var(--text-primary)">{{ spin.proof.client_seed }}</span></div>
                        </template>
                    </div>
                </div>
                <div><div class="card mb-4"><div class="stat-label">Balance</div><div class="text-2xl font-black gold-text mt-1">${{ money(balance) }}</div></div><div class="card"><div class="stat-label mb-3">Recent Bets</div><div class="space-y-3 text-xs font-mono max-h-60 overflow-y-auto"><div v-for="item in history" :key="item.id" :class="['p-3 rounded flex items-center justify-between border-l-4', item.isWin ? 'border-green-500 text-green-400' : 'border-red-500 text-red-400']" style="background:var(--surface-1)"><span>{{ item.text }}</span><span>{{ item.amount }}</span></div></div></div></div>
            </div>
        </template>
    </AppLayout>
</template>
