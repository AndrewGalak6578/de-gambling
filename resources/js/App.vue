<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import ConfirmModal from './components/ConfirmModal.vue';
import ToastContainer from './components/ToastContainer.vue';
import AdminPage from './pages/AdminPage.vue';
import DashboardPage from './pages/DashboardPage.vue';
import GamePlayPage from './pages/GamePlayPage.vue';
import GamesPage from './pages/GamesPage.vue';
import HistoryPage from './pages/HistoryPage.vue';
import LoginPage from './pages/LoginPage.vue';
import ProfilePage from './pages/ProfilePage.vue';
import RegisterPage from './pages/RegisterPage.vue';
import WalletPage from './pages/WalletPage.vue';
import { route } from './state/router';
import { session } from './state/session';
import { api } from './services/api';

const booting = ref(Boolean(session.token));

const pageComponent = computed(() => {
    if (!session.token && !['login', 'register'].includes(route.page)) return LoginPage;
    if (booting.value && !['login', 'register'].includes(route.page)) return null;

    return {
        login: LoginPage,
        register: RegisterPage,
        dashboard: DashboardPage,
        wallet: WalletPage,
        games: GamesPage,
        'game-play': GamePlayPage,
        history: HistoryPage,
        profile: ProfilePage,
        admin: AdminPage,
    }[route.page] || DashboardPage;
});

async function hydrateSession() {
    if (!session.token || session.user) {
        booting.value = false;
        return;
    }

    const data = await api('/auth/me');
    if (data?.user) session.user = data.user;
    booting.value = false;
}

onMounted(hydrateSession);

watch(() => route.page, (page) => {
    if (!session.token && !['login', 'register'].includes(page)) {
        window.location.hash = 'login';
    }
}, { immediate: true });
</script>

<template>
    <div v-if="booting && !['login', 'register'].includes(route.page)" class="spinner"></div>
    <component v-else-if="pageComponent" :is="pageComponent" v-bind="route.params" />
    <ToastContainer />
    <ConfirmModal />
</template>
