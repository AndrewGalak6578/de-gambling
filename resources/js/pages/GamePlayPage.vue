<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import AppIcon from '../components/AppIcon.vue';
import AppLayout from '../components/AppLayout.vue';
import Spinner from '../components/Spinner.vue';
import { api } from '../services/api';
import { showToast } from '../services/toast';
import { navigate } from '../state/router';
import { money, normalizeCollection, randomSeed } from '../utils/format';
import {
    animateNumber,
    coinRain,
    coinShower,
    flashWin,
    mountAmbientParticles,
    screenShake,
    showBigWinBanner,
    showJackpotBanner,
    spawnWinBurst,
} from '../utils/effects';

const props = defineProps({ gameId: { type: String, default: '' } });

/* ── Slot symbol metadata (matches App\Modules\Game\Engines\SlotsEngine) ── */
const SLOT_SYMBOLS = {
    cherry: { icon: '🍒', label: 'Cherry', payout: 10 },
    lemon: { icon: '🍋', label: 'Lemon', payout: 16 },
    bell: { icon: '🔔', label: 'Bell', payout: 27 },
    star: { icon: '⭐', label: 'Star', payout: 55 },
    diamond: { icon: '💎', label: 'Diamond', payout: 110 },
    seven: { icon: '7️⃣', label: 'Seven', payout: 270 },
    crown: { icon: '👑', label: 'Crown', payout: 1300 },
};
const SLOT_KEYS = ['cherry', 'lemon', 'bell', 'star', 'diamond', 'seven', 'crown'];
const SLOT_REEL_LENGTH = 25;
const SLOT_SYMBOL_HEIGHT = 120;

/* ── Blackjack metadata ── */
const SUIT_GLYPH = { S: '♠', H: '♥', D: '♦', C: '♣' };
const SUIT_COLOR = { S: 'card-black', H: 'card-red', D: 'card-red', C: 'card-black' };
const BJ_STATUS_LABEL = {
    playing: 'Hit or stand?',
    bust: 'Bust! You lose',
    blackjack: 'Blackjack! 3:2 payout',
    win: 'You win!',
    lose: 'Dealer wins',
    push: 'Push — stake returned',
    dealer_bust: 'Dealer busts — you win!',
};
const BJ_STATUS_COLOR = {
    playing: 'var(--text-muted)',
    bust: '#f87171',
    blackjack: '#fcd34d',
    win: '#4ade80',
    lose: '#f87171',
    push: '#fcd34d',
    dealer_bust: '#4ade80',
};

const loading = ref(true);
const game = ref(null);
const balance = ref('0.00');
const history = ref([]);
const currentRotation = ref(0);
const balanceEl = ref(null);
const diceStageEl = ref(null);
const slotMachineEl = ref(null);
const wheelOuterEl = ref(null);
const diceCubeEl = ref(null);
const slotWindowEl = ref(null);

const dice = reactive({
    target: 50,
    condition: 'under',
    amount: '10.00',
    seed: randomSeed(),
    roll: '50.00',
    result: 'Ready to roll',
    resultColor: 'var(--text-muted)',
    marker: 50,
    proof: null,
    rolling: false,
    outcome: 'idle',
    payout: null,
    historyDots: [],
});

const spin = reactive({
    sector: 1,
    amount: '10.00',
    seed: randomSeed(),
    result: '1',
    status: 'Place your bet',
    resultColor: '',
    proof: null,
    spinning: false,
    pointerWobble: false,
});

const slots = reactive({
    amount: '10.00',
    seed: randomSeed(),
    spinning: false,
    message: 'Pull the lever',
    messageColor: 'var(--text-muted)',
    payoutText: '',
    payoutClass: '',
    matchType: 'none',
    reelStrips: [[], [], []],
    reelTransforms: ['translateY(0px)', 'translateY(0px)', 'translateY(0px)'],
    reelTransitions: ['none', 'none', 'none'],
    reelClasses: ['', '', ''],
    proof: null,
});

const blackjack = reactive({
    amount: '25.00',
    seed: randomSeed(),
    playerCards: [],
    dealerCards: [],
    dealerHiddenCount: 0,
    playerScore: 0,
    dealerVisibleScore: 0,
    dealerScore: null,
    status: 'idle', // 'idle' | 'dealing' | 'playing' | terminal status
    statusMessage: 'Place your bet',
    statusColor: 'var(--text-muted)',
    payout: null,
    busy: false,
    betId: null,
    proof: null,
});

const isDice = computed(() => game.value?.slug === 'dice');
const isSlots = computed(() => game.value?.slug === 'slots');
const isWheel = computed(() => game.value?.slug === 'spin-to-win');
const isBlackjack = computed(() => game.value?.slug === 'blackjack');
const bjStageEl = ref(null);
const bjTableEl = ref(null);
const sectors = computed(() => game.value?.config?.sectors || 8);
const sectorNumbers = computed(() => Array.from({ length: sectors.value }, (_, i) => i + 1));
const greenStyle = computed(() => dice.condition === 'under'
    ? { left: '0%', width: `${dice.target}%` }
    : { left: `${dice.target}%`, width: `${100 - dice.target}%` });
const redStyle = computed(() => dice.condition === 'under'
    ? { left: `${dice.target}%`, width: `${100 - dice.target}%` }
    : { left: '0%', width: `${dice.target}%` });

const rtp = computed(() => parseFloat(game.value?.rtp_percentage) || 95);
const diceWinChance = computed(() => {
    const t = Math.max(1, Math.min(99, Number(dice.target) || 50));
    const factor = rtp.value / 100;
    return dice.condition === 'under' ? t * factor : (100 - t) * factor;
});
const diceMultiplier = computed(() => {
    const t = Math.max(1, Math.min(99, Number(dice.target) || 50));
    const factor = rtp.value / 100;
    return dice.condition === 'under' ? (100 / t) * factor : (100 / (100 - t)) * factor;
});
const diceOnWin = computed(() => (parseFloat(dice.amount) || 0) * diceMultiplier.value);

const accentTitleClass = computed(() => {
    if (isDice.value || isSlots.value || isBlackjack.value) return 'gold-text';
    return 'silver-text';
});
const accentCardClass = computed(() => (isWheel.value ? 'card-silver' : 'card-gold'));
const bjFinished = computed(() => ['bust', 'blackjack', 'win', 'lose', 'push', 'dealer_bust'].includes(blackjack.status));
const bjIsPlaying = computed(() => blackjack.status === 'playing');

let ambientCleanup = null;

watch(() => props.gameId, load);
onMounted(load);
onBeforeUnmount(() => { if (ambientCleanup) ambientCleanup(); });

async function load() {
    loading.value = true;
    const [gamesData, walletData] = await Promise.all([api('/games'), api('/wallet')]);
    const games = normalizeCollection(gamesData);
    game.value = games.find((item) => item.slug === props.gameId) || games[0] || null;
    balance.value = walletData?.balance || '0.00';
    history.value = [];
    currentRotation.value = 0;

    Object.assign(dice, {
        target: 50, condition: 'under', amount: '10.00', seed: randomSeed(),
        roll: '50.00', result: 'Ready to roll', resultColor: 'var(--text-muted)',
        marker: 50, proof: null, rolling: false, outcome: 'idle', payout: null,
        historyDots: [],
    });
    Object.assign(spin, {
        sector: 1, amount: '10.00', seed: randomSeed(),
        result: '1', status: 'Place your bet', resultColor: '',
        proof: null, spinning: false, pointerWobble: false,
    });

    const initialStrip = [{ key: '__init', icon: '🎰' }];
    Object.assign(slots, {
        amount: '10.00', seed: randomSeed(), spinning: false,
        message: 'Pull the lever', messageColor: 'var(--text-muted)',
        payoutText: '', payoutClass: '', matchType: 'none',
        reelStrips: [initialStrip.slice(), initialStrip.slice(), initialStrip.slice()],
        reelTransforms: ['translateY(0px)', 'translateY(0px)', 'translateY(0px)'],
        reelTransitions: ['none', 'none', 'none'],
        reelClasses: ['', '', ''],
        proof: null,
    });

    Object.assign(blackjack, {
        amount: '25.00', seed: randomSeed(),
        playerCards: [], dealerCards: [], dealerHiddenCount: 0,
        playerScore: 0, dealerVisibleScore: 0, dealerScore: null,
        status: 'idle', statusMessage: 'Place your bet', statusColor: 'var(--text-muted)',
        payout: null, busy: false, betId: null, proof: null,
    });

    if (game.value) await loadRecentBets();
    if (game.value?.slug === 'blackjack') await restorePendingBlackjack();

    loading.value = false;
    await nextTick(); mountAmbient();
}

async function loadRecentBets() {
    if (!game.value) return;
    const data = await api(`/user/bets?game_id=${game.value.id}&per_page=12&sort=created_at&direction=desc`);
    const bets = data?.data || [];
    history.value = bets.map(formatStoredBet);

    if (game.value.slug === 'dice') {
        dice.historyDots = bets
            .filter((bet) => bet.result?.roll !== undefined)
            .slice()
            .reverse()
            .map((bet) => ({
                id: bet.id,
                roll: Math.floor(Number(bet.result.roll) || 0),
                isWin: Boolean(bet.result.is_win),
            }))
            .slice(-20);
    }
}

function formatStoredBet(bet) {
    const result = bet.result || {};
    const betAmount = parseFloat(bet.bet_amount || 0);
    const payoutAmount = parseFloat(bet.payout_amount || 0);
    const isWin = payoutAmount > betAmount;
    const isPush = payoutAmount > 0 && Math.abs(payoutAmount - betAmount) < 0.000001;

    return {
        id: bet.id,
        text: storedBetText(bet, result),
        amount: bet.status === 'pending' ? 'Pending' : isWin ? `+$${money(bet.payout_amount)}` : isPush ? 'Push' : `-$${money(bet.bet_amount)}`,
        isWin,
    };
}

function storedBetText(bet, result) {
    if (game.value?.slug === 'dice' && result.roll !== undefined) {
        return `#${bet.id} Roll: ${Number(result.roll).toFixed(2)}`;
    }

    if (game.value?.slug === 'slots' && Array.isArray(result.reels)) {
        return `#${bet.id} ${result.reels.map((k) => SLOT_SYMBOLS[k]?.icon || k).join(' ')}`;
    }

    if (game.value?.slug === 'spin-to-win' && result.landed_sector !== undefined) {
        return `#${bet.id} Sector ${result.landed_sector}`;
    }

    if (game.value?.slug === 'blackjack') {
        return `#${bet.id} ${(result.status || bet.status).toUpperCase()} · ${describeHand(result.player_cards || [])}`;
    }

    return `#${bet.id} ${bet.status}`;
}

async function restorePendingBlackjack() {
    if (!game.value) return;
    const data = await api(`/user/bets?game_id=${game.value.id}&status=pending&per_page=1`);
    const pending = (data?.data || [])[0];
    if (!pending) return;
    applyBlackjackBet(pending, /* fromRestore */ true);
}

function applyBlackjackBet(bet, fromRestore = false) {
    const r = bet.result || {};
    blackjack.betId = bet.id;
    blackjack.playerCards = r.player_cards || [];
    blackjack.dealerCards = r.dealer_visible_cards || [];
    blackjack.dealerHiddenCount = r.dealer_hidden_count ?? 0;
    blackjack.playerScore = r.player_score ?? 0;
    blackjack.dealerVisibleScore = r.dealer_visible_score ?? 0;
    blackjack.dealerScore = r.dealer_score ?? null;
    blackjack.status = r.status || 'playing';
    blackjack.statusMessage = fromRestore && blackjack.status === 'playing'
        ? 'Pending hand restored — hit or stand?'
        : (BJ_STATUS_LABEL[blackjack.status] || 'Hit or stand?');
    blackjack.statusColor = BJ_STATUS_COLOR[blackjack.status] || 'var(--text-muted)';
    if (['blackjack', 'win', 'dealer_bust'].includes(blackjack.status)) {
        blackjack.payout = { kind: 'win', text: `+$${money(bet.payout_amount)}` };
    } else if (blackjack.status === 'push') {
        blackjack.payout = { kind: 'push', text: 'Push' };
    } else if (['bust', 'lose'].includes(blackjack.status)) {
        blackjack.payout = { kind: 'loss', text: `-$${money(bet.bet_amount)}` };
    } else {
        blackjack.payout = null;
    }
    blackjack.proof = bet.result?.server_seed ? bet : null;
}

function mountAmbient() {
    if (ambientCleanup) { ambientCleanup(); ambientCleanup = null; }
    const stage = diceStageEl.value || slotMachineEl.value || wheelOuterEl.value || bjStageEl.value;
    if (stage) ambientCleanup = mountAmbientParticles(stage, 8);
}

function setBetAmount(target, value) {
    target.amount = value.toFixed(2);
}
function scaleBetAmount(target, factor) {
    const next = Math.max(0.1, (parseFloat(target.amount) || 1) * factor);
    target.amount = next.toFixed(2);
}

async function refreshBalanceAnimated() {
    const wallet = await api('/wallet');
    if (!wallet) return;
    const prev = parseFloat(balance.value) || 0;
    const next = parseFloat(wallet.balance) || 0;
    balance.value = wallet.balance;
    if (balanceEl.value && Math.abs(next - prev) > 0.001) {
        animateNumber(balanceEl.value, prev, next, { duration: 800 });
    }
}

function pushHistory(text, amount, isWin) {
    history.value.unshift({ id: Date.now() + Math.random(), text, amount, isWin });
    history.value = history.value.slice(0, 12);
}

/* ──────────────────── DICE ──────────────────── */
let diceRollInterval = null;

async function placeDiceBet() {
    if (dice.rolling) return;
    dice.rolling = true;
    dice.outcome = 'rolling';
    dice.result = 'Rolling…';
    dice.resultColor = 'var(--text-muted)';
    dice.payout = null;
    diceRollInterval = setInterval(() => {
        dice.roll = (Math.random() * 100).toFixed(2);
    }, 55);

    const data = await api(`/games/${game.value.id}/bet`, {
        method: 'POST',
        body: JSON.stringify({
            bet_amount: dice.amount,
            client_seed: dice.seed,
            payload: { target: Number(dice.target), condition: dice.condition },
        }),
    });

    clearInterval(diceRollInterval);
    dice.rolling = false;

    if (!data || data._status) {
        dice.outcome = 'idle';
        dice.result = 'Bet failed';
        dice.resultColor = '#f87171';
        return showToast(data?.message || 'Bet failed.', 'error');
    }

    const roll = data.outcome?.state?.roll;
    const isWin = data.outcome?.state?.is_win;
    const multiplier = data.outcome?.payout_multiplier ?? 0;
    dice.roll = roll !== undefined ? roll.toFixed(2) : '--';
    dice.marker = roll || 0;
    dice.outcome = isWin ? 'win' : 'loss';
    dice.result = isWin ? 'Win!' : 'Lost';
    dice.resultColor = isWin ? '#4ade80' : '#f87171';
    dice.payout = isWin
        ? { kind: 'win', text: `+$${money(data.bet.payout_amount)}` }
        : { kind: 'loss', text: `-$${money(dice.amount)}` };
    dice.proof = data.bet.result?.server_seed ? data.bet : null;

    dice.historyDots = [
        ...dice.historyDots,
        { id: data.bet.id, roll: Math.floor(roll || 0), isWin },
    ].slice(-20);

    if (isWin) {
        await nextTick();
        spawnWinBurst(diceCubeEl.value?.parentElement);
        flashWin(diceStageEl.value);
        if (multiplier >= 50) {
            screenShake('hard');
            coinRain({ count: 32 });
            showBigWinBanner(diceStageEl.value, 'MEGA WIN', `${multiplier.toFixed(2)}×`);
        } else if (multiplier >= 10) {
            screenShake('soft');
            coinShower(diceStageEl.value);
            showBigWinBanner(diceStageEl.value, 'BIG WIN', `${multiplier.toFixed(2)}×`);
        }
    }

    pushHistory(
        `#${data.bet.id} Roll: ${roll?.toFixed(2)}`,
        isWin ? `+$${money(data.bet.payout_amount)}` : `-$${money(dice.amount)}`,
        isWin,
    );
    await refreshBalanceAnimated();
    setTimeout(() => { if (dice.outcome === 'win' || dice.outcome === 'loss') dice.outcome = 'settled'; }, 2200);
}

/* ──────────────────── BLACKJACK ──────────────────── */
async function blackjackDeal() {
    if (blackjack.busy || bjIsPlaying.value) return;
    blackjack.busy = true;
    blackjack.status = 'dealing';
    blackjack.statusMessage = 'Dealing…';
    blackjack.statusColor = 'var(--text-muted)';
    blackjack.payout = null;
    blackjack.playerCards = [];
    blackjack.dealerCards = [];
    blackjack.dealerHiddenCount = 0;

    const data = await api(`/games/${game.value.id}/bet`, {
        method: 'POST',
        body: JSON.stringify({
            bet_amount: blackjack.amount,
            client_seed: blackjack.seed,
            payload: {},
        }),
    });

    blackjack.busy = false;
    if (!data || data._status) {
        blackjack.status = 'idle';
        blackjack.statusMessage = 'Deal failed';
        blackjack.statusColor = '#f87171';
        return showToast(data?.message || 'Deal failed.', 'error');
    }

    applyBlackjackBet(data.bet);
    await afterBlackjackUpdate(data, /* isDealOrFinish */ true);
}

async function blackjackAction(action) {
    if (blackjack.busy || !bjIsPlaying.value) return;
    blackjack.busy = true;
    blackjack.statusMessage = action === 'hit' ? 'Drawing card…' : 'Dealer plays out…';
    blackjack.statusColor = 'var(--text-muted)';

    const data = await api(`/games/${game.value.id}/bet`, {
        method: 'POST',
        body: JSON.stringify({ payload: { action } }),
    });

    blackjack.busy = false;
    if (!data || data._status) {
        return showToast(data?.message || 'Action failed.', 'error');
    }

    applyBlackjackBet(data.bet);
    await afterBlackjackUpdate(data, blackjack.status !== 'playing');
}

async function afterBlackjackUpdate(data, settled) {
    if (!settled) return;
    const status = blackjack.status;
    const payoutAmount = parseFloat(data.bet?.payout_amount || 0);
    const stake = parseFloat(blackjack.amount) || 0;
    const net = payoutAmount - stake;

    await nextTick();
    if (['blackjack', 'win', 'dealer_bust'].includes(status)) {
        spawnWinBurst(bjTableEl.value);
        flashWin(bjStageEl.value);
        if (status === 'blackjack') {
            screenShake('hard');
            coinRain({ count: 28 });
            showBigWinBanner(bjStageEl.value, 'BLACKJACK', '3:2 payout');
        } else if (net >= stake * 5) {
            screenShake('hard');
            coinRain({ count: 20 });
            showBigWinBanner(bjStageEl.value, 'BIG WIN', `+$${net.toFixed(2)}`);
        } else {
            screenShake('soft');
            coinShower(bjStageEl.value);
        }
    }

    pushHistory(
        `#${data.bet.id} ${status.toUpperCase()} · ${describeHand(blackjack.playerCards)} vs ${describeHand(blackjack.dealerCards)}`,
        net > 0 ? `+$${net.toFixed(2)}` : net < 0 ? `-$${Math.abs(net).toFixed(2)}` : 'Push',
        net > 0,
    );
    await refreshBalanceAnimated();
}

function describeHand(cards) {
    if (!cards || !cards.length) return '--';
    return cards.map(c => `${c.rank}${SUIT_GLYPH[c.suit] || ''}`).join(' ');
}

function blackjackNewHand() {
    Object.assign(blackjack, {
        playerCards: [], dealerCards: [], dealerHiddenCount: 0,
        playerScore: 0, dealerVisibleScore: 0, dealerScore: null,
        status: 'idle', statusMessage: 'Place your bet', statusColor: 'var(--text-muted)',
        payout: null, busy: false, betId: null, proof: null,
        seed: randomSeed(),
    });
}

/* ──────────────────── SPIN WHEEL ──────────────────── */
async function placeSpinBet() {
    if (spin.spinning) return;
    spin.spinning = true;
    spin.status = 'Spinning…';
    spin.resultColor = '';
    currentRotation.value += 1800;

    const data = await api(`/games/${game.value.id}/bet`, {
        method: 'POST',
        body: JSON.stringify({
            bet_amount: spin.amount,
            client_seed: spin.seed,
            payload: { sector: Number(spin.sector) },
        }),
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

    setTimeout(() => { spin.pointerWobble = true; }, 3200);

    setTimeout(async () => {
        spin.pointerWobble = false;
        spin.spinning = false;
        spin.result = `Sector ${landed}`;
        spin.status = isWin
            ? `Win! +$${money(data.bet.payout_amount)}`
            : `Lost. Picked ${state?.player_sector}, landed ${landed}`;
        spin.resultColor = isWin ? '#4ade80' : '#f87171';
        spin.proof = data.bet.result?.server_seed ? data.bet : null;

        if (isWin) {
            spawnWinBurst(wheelOuterEl.value);
            flashWin(wheelOuterEl.value);
            screenShake('soft');
            coinShower(wheelOuterEl.value);
        }

        pushHistory(
            `#${data.bet.id} Sector ${landed}`,
            isWin ? `+$${money(data.bet.payout_amount)}` : `-$${money(spin.amount)}`,
            isWin,
        );
        await refreshBalanceAnimated();
    }, 4600);
}

/* ──────────────────── SLOTS ──────────────────── */
function buildReelStripFor(targetKey) {
    const arr = [];
    for (let i = 0; i < SLOT_REEL_LENGTH - 1; i++) {
        const k = SLOT_KEYS[Math.floor(Math.random() * SLOT_KEYS.length)];
        arr.push({ key: `${k}-${i}-${Math.random()}`, icon: SLOT_SYMBOLS[k].icon });
    }
    arr.push({ key: `target-${Math.random()}`, icon: SLOT_SYMBOLS[targetKey].icon });
    return arr;
}

function fillRandomStrip() {
    const arr = [];
    for (let i = 0; i < SLOT_REEL_LENGTH; i++) {
        const k = SLOT_KEYS[Math.floor(Math.random() * SLOT_KEYS.length)];
        arr.push({ key: `${k}-${i}-${Math.random()}`, icon: SLOT_SYMBOLS[k].icon });
    }
    return arr;
}

async function placeSlotBet() {
    if (slots.spinning) return;
    slots.spinning = true;
    slots.message = 'Spinning…';
    slots.messageColor = 'var(--text-muted)';
    slots.payoutText = '';
    slots.payoutClass = '';
    slots.matchType = 'none';
    slots.reelClasses = ['', '', ''];

    // Pre-roll: fill with random symbols, reset transforms
    for (let i = 0; i < 3; i++) {
        slots.reelStrips[i] = fillRandomStrip();
        slots.reelTransitions[i] = 'none';
        slots.reelTransforms[i] = 'translateY(0px)';
    }
    await nextTick();

    const data = await api(`/games/${game.value.id}/bet`, {
        method: 'POST',
        body: JSON.stringify({ bet_amount: slots.amount, client_seed: slots.seed, payload: {} }),
    });

    if (!data || data._status) {
        slots.spinning = false;
        slots.message = 'Spin failed';
        slots.messageColor = '#f87171';
        return showToast(data?.message || 'Spin failed.', 'error');
    }

    const reels = data.outcome?.state?.reels || ['cherry', 'lemon', 'bell'];
    const matchType = data.outcome?.state?.match_type;
    const isWin = data.outcome?.state?.is_win;
    const isJackpot = matchType === 'three_of_a_kind' && reels[0] === 'crown';
    const isBigWin = matchType === 'three_of_a_kind' && ['diamond', 'seven', 'crown'].includes(reels[0]);
    const anticipation = reels[0] === reels[1] && reels[0] !== reels[2];

    // Build result strips with the target symbol at the last index
    for (let i = 0; i < 3; i++) {
        slots.reelStrips[i] = buildReelStripFor(reels[i]);
        slots.reelTransitions[i] = 'none';
        slots.reelTransforms[i] = 'translateY(0px)';
    }
    await nextTick();

    // Stagger stops; reel 3 is dramatically slower when first two match.
    const stopDurations = anticipation ? [1.4, 1.9, 3.4] : [1.4, 1.9, 2.5];
    const finalY = -(SLOT_REEL_LENGTH - 1) * SLOT_SYMBOL_HEIGHT;
    requestAnimationFrame(() => {
        for (let i = 0; i < 3; i++) {
            slots.reelTransitions[i] = `transform ${stopDurations[i]}s cubic-bezier(0.18, 0.8, 0.18, 1)`;
            slots.reelTransforms[i] = `translateY(${finalY}px)`;
        }
    });

    // Apply anticipation glow to the first two reels once they've landed
    if (anticipation) {
        setTimeout(() => {
            slots.reelClasses = ['anticipating', 'anticipating', ''];
            slots.message = 'Two of a kind — hold your breath…';
            slots.messageColor = '#fcd34d';
        }, stopDurations[1] * 1000);
    }

    setTimeout(async () => {
        slots.spinning = false;
        slots.matchType = matchType;

        if (matchType === 'three_of_a_kind') {
            slots.reelClasses = isJackpot ? ['jackpot', 'jackpot', 'jackpot'] : ['winning', 'winning', 'winning'];
            slots.message = `Three of a kind — ${SLOT_SYMBOLS[reels[0]].label}!`;
            slots.messageColor = '#4ade80';
            slots.payoutText = `+$${money(data.bet.payout_amount)}`;
            slots.payoutClass = 'multiplier-pop';

            await nextTick();
            spawnWinBurst(slotWindowEl.value, 30);
            flashWin(slotWindowEl.value);
            coinShower(slotMachineEl.value, { count: 18 });

            if (isJackpot) {
                screenShake('hard');
                coinRain({ count: 50 });
                showJackpotBanner(`+$${money(data.bet.payout_amount)} · CROWN ROYALE`);
            } else if (isBigWin) {
                screenShake('hard');
                coinRain({ count: 28 });
                showBigWinBanner(slotMachineEl.value, 'BIG WIN', SLOT_SYMBOLS[reels[0]].label);
            } else {
                screenShake('soft');
            }
        } else if (matchType === 'cherry_consolation') {
            slots.reelClasses = ['', '', ''];
            slots.message = 'Cherry consolation';
            slots.messageColor = '#facc15';
            slots.payoutText = `+$${money(data.bet.payout_amount)}`;
            slots.payoutClass = 'text-lg gold-text font-bold';
        } else {
            slots.reelClasses = ['', '', ''];
            slots.message = 'No match';
            slots.messageColor = '#f87171';
            slots.payoutText = `-$${money(slots.amount)}`;
            slots.payoutClass = 'text-lg text-red-400 font-bold';
        }

        slots.proof = data.bet.result?.server_seed ? data.bet : null;

        pushHistory(
            `#${data.bet.id} ${reels.map((k) => SLOT_SYMBOLS[k].icon).join(' ')}`,
            isWin ? `+$${money(data.bet.payout_amount)}` : `-$${money(slots.amount)}`,
            isWin,
        );
        await refreshBalanceAnimated();
    }, stopDurations[2] * 1000 + 120);
}
</script>

<template>
    <AppLayout>
        <Spinner v-if="loading" />
        <div v-else-if="!game" class="empty-state"><p>Game not found.</p></div>
        <template v-else>
            <div class="flex items-center gap-3 mb-6">
                <button class="btn btn-ghost btn-icon" @click="navigate('games')"><AppIcon name="back" /></button>
                <h1 :class="['text-xl font-bold', accentTitleClass]">{{ game.name }}</h1>
                <span class="badge badge-green" style="margin-left:auto">{{ game.rtp_percentage }}% RTP</span>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2">
                    <div :class="['card', accentCardClass]">
                        <!-- ─── DICE ─── -->
                        <template v-if="isDice">
                            <div class="dice-stage" ref="diceStageEl">
                                <div
                                    ref="diceCubeEl"
                                    class="dice-cube"
                                    :class="{ rolling: dice.rolling, win: dice.outcome === 'win', loss: dice.outcome === 'loss', 'big-win': dice.outcome === 'win' && diceMultiplier >= 10 }"
                                >
                                    <div class="dice-face">{{ dice.roll }}</div>
                                </div>
                                <div class="text-sm font-semibold" :style="{ minHeight: '1.5rem', color: dice.resultColor }">{{ dice.result }}</div>
                                <div style="min-height:3rem;margin-top:.25rem">
                                    <div v-if="dice.payout" :class="dice.payout.kind === 'win' ? 'multiplier-pop' : 'text-lg text-red-400 font-bold'" style="margin-top:.5rem">{{ dice.payout.text }}</div>
                                </div>
                            </div>
                            <div class="dice-slider-container">
                                <div class="dice-slider-track">
                                    <div class="dice-slider-zone dice-slider-zone-green absolute top-0 bottom-0" :style="greenStyle"></div>
                                    <div class="dice-slider-zone dice-slider-zone-red absolute top-0 bottom-0" :style="redStyle"></div>
                                </div>
                                <div class="dice-slider-marker" :style="{ left: `${dice.marker}%` }"></div>
                            </div>
                            <div class="dice-tick-labels"><span>0</span><span>25</span><span>50</span><span>75</span><span>100</span></div>

                            <div class="dice-history-label">Recent rolls</div>
                            <div class="dice-history-strip">
                                <div v-for="d in dice.historyDots" :key="d.id" :class="['dice-history-dot', d.isWin ? 'win' : 'loss']">{{ d.roll }}</div>
                                <span v-if="!dice.historyDots.length" class="dice-history-placeholder">Your last 20 rolls appear here.</span>
                            </div>

                            <div class="dice-stats-row">
                                <div class="dice-stat"><div class="dice-stat-label">Win Chance</div><div class="dice-stat-value">{{ diceWinChance.toFixed(2) }}%</div></div>
                                <div class="dice-stat"><div class="dice-stat-label">Multiplier</div><div class="dice-stat-value gold-text">{{ diceMultiplier.toFixed(4) }}×</div></div>
                                <div class="dice-stat"><div class="dice-stat-label">On Win</div><div class="dice-stat-value">+${{ diceOnWin.toFixed(2) }}</div></div>
                            </div>

                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div><label class="label">Target (1-99)</label><input v-model.number="dice.target" type="number" min="1" max="99" class="input"></div>
                                <div><label class="label">Condition</label><select v-model="dice.condition" class="select"><option value="under">Under</option><option value="over">Over</option></select></div>
                            </div>
                            <div class="mb-4">
                                <label class="label">Bet Amount ($)</label>
                                <input v-model="dice.amount" type="number" min="0.10" max="5000" step="1.00" class="input">
                                <div class="flex gap-2 mt-2 flex-wrap">
                                    <button class="bet-chip" @click="setBetAmount(dice, 5)">$5</button>
                                    <button class="bet-chip" @click="setBetAmount(dice, 25)">$25</button>
                                    <button class="bet-chip" @click="setBetAmount(dice, 100)">$100</button>
                                    <button class="bet-chip" @click="setBetAmount(dice, 500)">$500</button>
                                    <button class="bet-chip" @click="scaleBetAmount(dice, 0.5)">½</button>
                                    <button class="bet-chip" @click="scaleBetAmount(dice, 2)">2×</button>
                                </div>
                            </div>
                            <div class="mb-4"><label class="label">Client Seed</label><input v-model="dice.seed" type="text" class="input font-mono text-xs"></div>
                            <button class="btn btn-gold w-full py-3" :disabled="dice.rolling" @click="placeDiceBet">{{ dice.rolling ? 'Rolling…' : 'Roll Dice' }}</button>
                            <div v-if="dice.proof" class="p-3 rounded-lg text-xs font-mono break-all mt-4" style="background:var(--surface-1);border:1px solid var(--border-subtle);color:var(--text-muted);line-height:1.6">Server: <span style="color:var(--text-primary)">{{ dice.proof.result.server_seed }}</span><br>Client: <span style="color:var(--text-primary)">{{ dice.proof.client_seed }}</span><br>Hash: <span style="color:var(--text-primary)">{{ dice.proof.server_seed_hash }}</span></div>
                        </template>

                        <!-- ─── SLOTS ─── -->
                        <template v-else-if="isSlots">
                            <div class="slot-machine" ref="slotMachineEl">
                                <div class="slot-title">Royal Slots</div>
                                <div class="slot-window" ref="slotWindowEl">
                                    <div v-for="(strip, i) in slots.reelStrips" :key="i" class="slot-reel" :class="slots.reelClasses[i]">
                                        <div class="reel-strip" :style="{ transform: slots.reelTransforms[i], transition: slots.reelTransitions[i] }">
                                            <div v-for="sym in strip" :key="sym.key" class="reel-symbol">{{ sym.icon }}</div>
                                        </div>
                                    </div>
                                    <div class="payline"></div>
                                </div>
                                <div class="text-center mt-4">
                                    <div class="text-sm font-semibold" :style="{ minHeight: '1.5rem', color: slots.messageColor }">{{ slots.message }}</div>
                                    <div style="min-height:3rem">
                                        <div v-if="slots.payoutText" :class="slots.payoutClass" style="margin-top:.5rem">{{ slots.payoutText }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4 mb-4 mt-3">
                                <div>
                                    <label class="label">Bet Amount ($)</label>
                                    <input v-model="slots.amount" type="number" min="0.10" max="5000" step="1.00" class="input">
                                    <div class="flex gap-2 mt-2 flex-wrap">
                                        <button class="bet-chip" @click="setBetAmount(slots, 5)">$5</button>
                                        <button class="bet-chip" @click="setBetAmount(slots, 25)">$25</button>
                                        <button class="bet-chip" @click="setBetAmount(slots, 100)">$100</button>
                                        <button class="bet-chip" @click="scaleBetAmount(slots, 0.5)">½</button>
                                        <button class="bet-chip" @click="scaleBetAmount(slots, 2)">2×</button>
                                    </div>
                                </div>
                                <div><label class="label">Client Seed</label><input v-model="slots.seed" type="text" class="input font-mono text-xs"></div>
                            </div>
                            <button class="btn btn-gold w-full py-3" :disabled="slots.spinning" @click="placeSlotBet">{{ slots.spinning ? 'Spinning…' : 'Spin Reels' }}</button>
                            <div class="mt-4">
                                <div class="text-xs font-semibold mb-2" style="color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">Three of a kind pays</div>
                                <div class="paytable">
                                    <div v-for="k in SLOT_KEYS" :key="k" class="paytable-cell" :title="SLOT_SYMBOLS[k].label">
                                        <div class="paytable-symbol">{{ SLOT_SYMBOLS[k].icon }}</div>
                                        <div class="paytable-payout">{{ SLOT_SYMBOLS[k].payout }}×</div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="slots.proof" class="p-3 rounded-lg text-xs font-mono break-all mt-4" style="background:var(--surface-1);border:1px solid var(--border-subtle);color:var(--text-muted);line-height:1.6">Server: <span style="color:var(--text-primary)">{{ slots.proof.result.server_seed }}</span><br>Client: <span style="color:var(--text-primary)">{{ slots.proof.client_seed }}</span><br>Hash: <span style="color:var(--text-primary)">{{ slots.proof.server_seed_hash }}</span></div>
                        </template>

                        <!-- ─── BLACKJACK ─── -->
                        <template v-else-if="isBlackjack">
                            <div class="bj-stage" ref="bjStageEl">
                                <div class="bj-table" ref="bjTableEl">
                                    <div class="bj-side bj-dealer">
                                        <div class="bj-side-header">
                                            <span class="bj-side-label">Dealer</span>
                                            <span v-if="blackjack.dealerScore !== null" class="bj-score-badge">{{ blackjack.dealerScore }}</span>
                                            <span v-else-if="blackjack.dealerVisibleScore" class="bj-score-badge bj-score-partial">{{ blackjack.dealerVisibleScore }} + ?</span>
                                        </div>
                                        <div class="bj-hand">
                                            <div v-for="(c, i) in blackjack.dealerCards" :key="`d-${i}`" :class="['playing-card', SUIT_COLOR[c.suit]]" :style="{ '--n': i }">
                                                <div class="card-corner top">{{ c.rank }}<br><span class="card-corner-suit">{{ SUIT_GLYPH[c.suit] }}</span></div>
                                                <div class="card-center">{{ SUIT_GLYPH[c.suit] }}</div>
                                                <div class="card-corner bottom">{{ c.rank }}<br><span class="card-corner-suit">{{ SUIT_GLYPH[c.suit] }}</span></div>
                                            </div>
                                            <div v-for="n in blackjack.dealerHiddenCount" :key="`h-${n}`" class="playing-card card-back" :style="{ '--n': blackjack.dealerCards.length + n - 1 }"></div>
                                            <div v-if="!blackjack.dealerCards.length && !blackjack.dealerHiddenCount" class="playing-card-slot">Dealer's cards</div>
                                        </div>
                                    </div>
                                    <div class="bj-status-bar">
                                        <div class="bj-status-message" :style="{ color: blackjack.statusColor }">{{ blackjack.statusMessage }}</div>
                                        <div v-if="blackjack.payout" :class="['bj-status-payout', blackjack.payout.kind]">{{ blackjack.payout.text }}</div>
                                    </div>
                                    <div class="bj-side bj-player">
                                        <div class="bj-side-header">
                                            <span class="bj-side-label">You</span>
                                            <span v-if="blackjack.playerScore" class="bj-score-badge">{{ blackjack.playerScore }}</span>
                                        </div>
                                        <div class="bj-hand">
                                            <div v-for="(c, i) in blackjack.playerCards" :key="`p-${i}`" :class="['playing-card', SUIT_COLOR[c.suit]]" :style="{ '--n': i }">
                                                <div class="card-corner top">{{ c.rank }}<br><span class="card-corner-suit">{{ SUIT_GLYPH[c.suit] }}</span></div>
                                                <div class="card-center">{{ SUIT_GLYPH[c.suit] }}</div>
                                                <div class="card-corner bottom">{{ c.rank }}<br><span class="card-corner-suit">{{ SUIT_GLYPH[c.suit] }}</span></div>
                                            </div>
                                            <div v-if="!blackjack.playerCards.length" class="playing-card-slot">Your cards</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="bjIsPlaying" class="grid grid-cols-2 gap-3 mt-4">
                                <button class="btn btn-gold py-3" :disabled="blackjack.busy" @click="blackjackAction('hit')">Hit</button>
                                <button class="btn btn-ghost py-3" :disabled="blackjack.busy" @click="blackjackAction('stand')">Stand</button>
                            </div>
                            <button v-else-if="bjFinished" class="btn btn-gold w-full py-3 mt-4" @click="blackjackNewHand">New Hand</button>
                            <template v-else>
                                <div class="grid grid-cols-2 gap-4 mt-4 mb-4">
                                    <div>
                                        <label class="label">Bet Amount ($)</label>
                                        <input v-model="blackjack.amount" type="number" min="1" max="5000" step="1" class="input">
                                        <div class="flex gap-2 mt-2 flex-wrap">
                                            <button class="bet-chip" @click="setBetAmount(blackjack, 5)">$5</button>
                                            <button class="bet-chip" @click="setBetAmount(blackjack, 25)">$25</button>
                                            <button class="bet-chip" @click="setBetAmount(blackjack, 100)">$100</button>
                                            <button class="bet-chip" @click="setBetAmount(blackjack, 500)">$500</button>
                                            <button class="bet-chip" @click="scaleBetAmount(blackjack, 0.5)">½</button>
                                            <button class="bet-chip" @click="scaleBetAmount(blackjack, 2)">2×</button>
                                        </div>
                                    </div>
                                    <div><label class="label">Client Seed</label><input v-model="blackjack.seed" type="text" class="input font-mono text-xs"></div>
                                </div>
                                <button class="btn btn-gold w-full py-3" :disabled="blackjack.busy" @click="blackjackDeal">{{ blackjack.busy ? 'Dealing…' : 'Deal' }}</button>
                            </template>

                            <div v-if="blackjack.proof?.result?.server_seed" class="p-3 rounded-lg text-xs font-mono break-all mt-4" style="background:var(--surface-1);border:1px solid var(--border-subtle);color:var(--text-muted);line-height:1.6">Server: <span style="color:var(--text-primary)">{{ blackjack.proof.result.server_seed }}</span><br>Client: <span style="color:var(--text-primary)">{{ blackjack.proof.client_seed }}</span><br>Hash: <span style="color:var(--text-primary)">{{ blackjack.proof.server_seed_hash }}</span></div>
                        </template>

                        <!-- ─── SPIN WHEEL ─── -->
                        <template v-else>
                            <div class="text-center mb-2">
                                <div class="roll-display silver-text" :style="{ color: spin.resultColor }">{{ spin.result }}</div>
                                <div class="text-sm font-semibold mt-1" :style="{ minHeight: '1.5rem', color: spin.resultColor || 'var(--text-muted)' }">{{ spin.status }}</div>
                            </div>
                            <div class="wheel-outer" ref="wheelOuterEl">
                                <div class="wheel-pointer" :class="{ wobbling: spin.pointerWobble }"></div>
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
                                <div>
                                    <label class="label">Bet Amount ($)</label>
                                    <input v-model="spin.amount" type="number" min="0.10" max="5000" step="1.00" class="input">
                                    <div class="flex gap-2 mt-2 flex-wrap">
                                        <button class="bet-chip" @click="setBetAmount(spin, 5)">$5</button>
                                        <button class="bet-chip" @click="setBetAmount(spin, 25)">$25</button>
                                        <button class="bet-chip" @click="setBetAmount(spin, 100)">$100</button>
                                        <button class="bet-chip" @click="scaleBetAmount(spin, 0.5)">½</button>
                                        <button class="bet-chip" @click="scaleBetAmount(spin, 2)">2×</button>
                                    </div>
                                </div>
                                <div><label class="label">Client Seed</label><input v-model="spin.seed" type="text" class="input font-mono text-xs"></div>
                            </div>
                            <button class="btn btn-silver w-full py-3" :disabled="spin.spinning" @click="placeSpinBet">{{ spin.spinning ? 'Spinning…' : 'Spin Wheel' }}</button>
                            <div v-if="spin.proof" class="p-3 rounded-lg text-xs font-mono break-all mt-4" style="background:var(--surface-1);border:1px solid var(--border-subtle);color:var(--text-muted);line-height:1.6">Server: <span style="color:var(--text-primary)">{{ spin.proof.result.server_seed }}</span><br>Client: <span style="color:var(--text-primary)">{{ spin.proof.client_seed }}</span></div>
                        </template>
                    </div>
                </div>
                <div class="game-rail">
                    <div class="card mb-4"><div class="stat-label">Balance</div><div class="text-2xl font-black gold-text mt-1" ref="balanceEl">${{ money(balance) }}</div></div>
                    <div class="card">
                        <div class="flex items-center justify-between mb-3">
                            <div class="stat-label" style="margin-bottom:0">Recent Bets</div>
                            <span class="badge badge-gold">{{ history.length }}</span>
                        </div>
                        <div class="space-y-3 text-xs font-mono max-h-72 overflow-y-auto">
                            <div v-for="item in history" :key="item.id" :class="['p-3 rounded flex items-center justify-between border-l-4', item.isWin ? 'border-green-500 text-green-400' : 'border-red-500 text-red-400']" style="background:var(--surface-1)"><span class="truncate" style="max-width:60%">{{ item.text }}</span><span>{{ item.amount }}</span></div>
                            <div v-if="!history.length" class="recent-bets-empty">
                                <div class="recent-bets-empty-icon">🎲</div>
                                <div class="recent-bets-empty-title">No bets yet</div>
                                <div class="recent-bets-empty-sub">Your last 12 wagers will show up here once you play.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </AppLayout>
</template>
