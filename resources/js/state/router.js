import { reactive } from 'vue';

export const route = reactive(parseHash());

export function parseHash() {
    let hash = window.location.hash.slice(1) || (localStorage.getItem('token') ? 'dashboard' : 'login');
    const params = {};

    if (hash.startsWith('game-play/')) {
        params.gameId = hash.split('/')[1];
        hash = 'game-play';
    }

    return { page: hash, params };
}

export function navigate(page, params = {}) {
    window.location.hash = page === 'game-play' && params.gameId ? `game-play/${params.gameId}` : page;
    syncRoute();
}

export function syncRoute() {
    const next = parseHash();
    route.page = next.page;
    route.params = next.params;
}

window.addEventListener('hashchange', syncRoute);

