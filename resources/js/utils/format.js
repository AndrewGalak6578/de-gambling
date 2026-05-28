export function fmtDate(value) {
    return value ? new Date(value).toLocaleString() : 'Open-ended';
}

export function interventionLabel(type) {
    return {
        admin_bet_block: 'Betting block',
        admin_deposit_block: 'Deposit block',
        admin_win_limit: 'Win count limit',
        admin_cool_off: 'Cool-off',
        self_exclusion: 'Self-exclusion',
        circuit_breaker: 'Circuit breaker',
    }[type] || type;
}

export function money(value) {
    return parseFloat(value || 0).toFixed(2);
}

export function randomSeed() {
    return Math.random().toString(36).substring(2, 10);
}

export function normalizeCollection(data, key = null) {
    if (Array.isArray(data)) return data;
    if (key && Array.isArray(data?.[key])) return data[key];
    if (data && typeof data.length === 'number') return Array.from(data);
    return [];
}
