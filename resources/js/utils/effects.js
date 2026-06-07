/* ───────────────────────────────────────────────
   Visual effects for the game pages.
   Pure DOM helpers — no Vue reactivity required.
   ─────────────────────────────────────────────── */

const COIN_ICONS = ['💰', '🪙', '💎'];

export function coinRain({ count = 36, duration = 2500 } = {}) {
    const layer = document.createElement('div');
    layer.className = 'coin-rain';
    document.body.appendChild(layer);
    for (let i = 0; i < count; i++) {
        const coin = document.createElement('div');
        coin.className = 'coin-rain-coin';
        coin.textContent = COIN_ICONS[Math.floor(Math.random() * COIN_ICONS.length)];
        coin.style.left = `${Math.random() * 100}vw`;
        coin.style.animationDuration = `${1.8 + Math.random() * 1.8}s`;
        coin.style.animationDelay = `${Math.random() * 0.8}s`;
        layer.appendChild(coin);
    }
    setTimeout(() => layer.remove(), duration + 1500);
}

export function coinShower(container, { count = 14 } = {}) {
    if (!container) return;
    if (getComputedStyle(container).position === 'static') container.style.position = 'relative';
    const shower = document.createElement('div');
    shower.className = 'coin-shower';
    for (let i = 0; i < count; i++) {
        const coin = document.createElement('div');
        coin.className = 'coin-shower-coin';
        coin.textContent = '🪙';
        coin.style.left = `${Math.random() * 90 + 5}%`;
        coin.style.animationDuration = `${0.8 + Math.random() * 0.7}s`;
        coin.style.animationDelay = `${Math.random() * 0.3}s`;
        shower.appendChild(coin);
    }
    container.appendChild(shower);
    setTimeout(() => shower.remove(), 2000);
}

export function screenShake(intensity = 'soft') {
    const target = document.getElementById('app') || document.body;
    const cls = intensity === 'hard' ? 'screen-shake-hard' : 'screen-shake-soft';
    target.classList.remove('screen-shake-soft', 'screen-shake-hard');
    void target.offsetHeight;
    target.classList.add(cls);
    setTimeout(() => target.classList.remove(cls), 800);
}

export function showJackpotBanner(payoutText) {
    const banner = document.createElement('div');
    banner.className = 'jackpot-banner';
    banner.innerHTML = `
        <div class="jackpot-banner-title">JACKPOT!</div>
        <div class="jackpot-banner-payout">${payoutText}</div>
    `;
    document.body.appendChild(banner);
    setTimeout(() => banner.remove(), 3500);
}

export function showBigWinBanner(container, title, subtitle = '') {
    if (!container) return;
    if (getComputedStyle(container).position === 'static') container.style.position = 'relative';
    const banner = document.createElement('div');
    banner.className = 'big-win-banner';
    banner.innerHTML = `
        <div class="big-win-text">${title}</div>
        ${subtitle ? `<div class="big-win-sub">${subtitle}</div>` : ''}
    `;
    container.appendChild(banner);
    setTimeout(() => banner.remove(), 2400);
}

export function spawnWinBurst(container, count = 22) {
    if (!container) return;
    if (getComputedStyle(container).position === 'static') container.style.position = 'relative';
    const burst = document.createElement('div');
    burst.className = 'win-burst';
    for (let i = 0; i < count; i++) {
        const spark = document.createElement('div');
        spark.className = 'spark';
        const angle = (i / count) * 2 * Math.PI + Math.random() * 0.3;
        const dist = 80 + Math.random() * 120;
        spark.style.setProperty('--dx', `${Math.cos(angle) * dist}px`);
        spark.style.setProperty('--dy', `${Math.sin(angle) * dist}px`);
        spark.style.animationDelay = `${Math.random() * 0.15}s`;
        burst.appendChild(spark);
    }
    container.appendChild(burst);
    setTimeout(() => burst.remove(), 1300);
}

export function flashWin(container) {
    if (!container) return;
    if (getComputedStyle(container).position === 'static') container.style.position = 'relative';
    const overlay = document.createElement('div');
    overlay.className = 'win-flash-overlay';
    container.appendChild(overlay);
    setTimeout(() => overlay.remove(), 850);
}

export function animateNumber(el, from, to, { duration = 700, prefix = '$', decimals = 2 } = {}) {
    if (!el) return;
    const start = performance.now();
    const delta = to - from;
    function step(now) {
        const t = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - t, 3);
        el.textContent = `${prefix}${(from + delta * eased).toFixed(decimals)}`;
        if (t < 1) requestAnimationFrame(step);
        else {
            el.textContent = `${prefix}${to.toFixed(decimals)}`;
            el.classList.remove('balance-flash');
            void el.offsetHeight;
            el.classList.add('balance-flash');
            setTimeout(() => el.classList.remove('balance-flash'), 800);
        }
    }
    requestAnimationFrame(step);
}

export function mountAmbientParticles(container, count = 6) {
    if (!container) return () => {};
    if (container.querySelector(':scope > .ambient-particles')) return () => {};
    if (getComputedStyle(container).position === 'static') container.style.position = 'relative';
    const wrapper = document.createElement('div');
    wrapper.className = 'ambient-particles';
    for (let i = 0; i < count; i++) {
        const p = document.createElement('div');
        p.className = 'ambient-particle';
        p.style.left = `${Math.random() * 100}%`;
        p.style.setProperty('--drift', `${Math.random() * 60 - 30}px`);
        p.style.animationDuration = `${5 + Math.random() * 5}s`;
        p.style.animationDelay = `${Math.random() * 6}s`;
        wrapper.appendChild(p);
    }
    container.appendChild(wrapper);
    return () => wrapper.remove();
}
