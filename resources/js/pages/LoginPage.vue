<script setup>
import { nextTick, onMounted, reactive } from 'vue';
import { api } from '../services/api';
import { showToast } from '../services/toast';
import { setSession } from '../state/session';
import { navigate } from '../state/router';

const form = reactive({ email: '', password: '' });

onMounted(() => nextTick(() => document.getElementById('login-email')?.focus()));

async function login() {
    if (!form.email || !form.password) return showToast('Email and password required.', 'error');

    const data = await api('/auth/login', { method: 'POST', body: JSON.stringify(form) });
    if (!data || data._status) return showToast(data?.message || 'Login failed.', 'error');

    setSession(data.token, data.user);
    showToast('Welcome back!', 'success');
    navigate('dashboard');
}
</script>

<template>
    <div class="auth-bg">
        <div class="auth-card">
            <div class="auth-logo"><img :src="'/logo.png'" alt="G-SHT"></div>
            <form class="auth-form" @submit.prevent="login">
                <div class="auth-title gold-text">Welcome Back</div>
                <div class="auth-subtitle">Sign in to your VIP account</div>
                <div class="form-group"><label class="label">Email</label><input id="login-email" v-model="form.email" type="email" class="input" placeholder="email@example.com"></div>
                <div class="form-group"><label class="label">Password</label><input v-model="form.password" type="password" class="input" placeholder="Enter password"></div>
                <button class="btn btn-gold w-full py-3">Sign In</button>
                <div class="auth-divider">Don't have an account? <a href="#" @click.prevent="navigate('register')">Create one</a></div>
            </form>
        </div>
    </div>
</template>
