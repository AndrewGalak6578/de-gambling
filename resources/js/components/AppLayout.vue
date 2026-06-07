<script setup>
import { ref } from 'vue';
import AppIcon from './AppIcon.vue';
import { api } from '../services/api';
import { clearSession, session } from '../state/session';
import { navigate, route } from '../state/router';
import { icons } from '../utils/icons';

const sidebarOpen = ref(false);

function toggleSidebar() {
    sidebarOpen.value = !sidebarOpen.value;
}

function go(page, params = {}) {
    navigate(page, params);
    sidebarOpen.value = false;
}

async function logout() {
    await api('/auth/logout', { method: 'POST' });
    clearSession();
    navigate('login');
}
</script>

<template>
    <button class="sidebar-toggle" @click="toggleSidebar" v-html="icons.menu"></button>
    <div :class="['mobile-overlay', { show: sidebarOpen }]" @click="toggleSidebar"></div>
    <aside :class="['sidebar', { open: sidebarOpen }]">
        <div class="sidebar-header"><img :src="'/logo.png'" alt="G-SHT"></div>
        <nav class="sidebar-nav">
            <button :class="['nav-item', { active: route.page === 'dashboard' }]" data-page="dashboard" @click="go('dashboard')"><AppIcon name="dashboard" /> Dashboard</button>
            <button :class="['nav-item', { active: route.page === 'wallet' }]" data-page="wallet" @click="go('wallet')"><AppIcon name="wallet" /> Wallet</button>
            <button :class="['nav-item', { active: route.page === 'games' || route.page === 'game-play' }]" data-page="games" @click="go('games')"><AppIcon name="games" /> Games</button>
            <button :class="['nav-item', { active: route.page === 'history' }]" data-page="history" @click="go('history')"><AppIcon name="history" /> History</button>
            <button :class="['nav-item', { active: route.page === 'profile' }]" data-page="profile" @click="go('profile')"><AppIcon name="settings" /> Settings</button>
            <button v-if="session.isAdmin" :class="['nav-item', { active: route.page === 'admin' }]" data-page="admin" @click="go('admin')"><AppIcon name="admin" /> Admin</button>
        </nav>
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-avatar">{{ session.user?.name?.[0]?.toUpperCase() || '?' }}</div>
                <div class="sidebar-user-info">
                    <p class="sidebar-user-name">{{ session.user?.name || 'User' }}</p>
                    <p class="sidebar-user-email">{{ session.user?.email || '' }}</p>
                </div>
            </div>
            <button class="btn btn-ghost w-full btn-sm" @click="logout"><AppIcon name="logout" /> Sign Out</button>
        </div>
    </aside>
    <main class="main-content">
        <slot />
    </main>
</template>
