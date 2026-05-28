<script setup>
import { reactive } from 'vue';
import { api } from '../services/api';
import { showToast } from '../services/toast';
import { setSession } from '../state/session';
import { navigate } from '../state/router';

const form = reactive({ name: '', email: '', password: '', password_confirmation: '' });

async function register() {
    if (!form.name || !form.email || !form.password) return showToast('All fields required.', 'error');
    if (form.password !== form.password_confirmation) return showToast('Passwords do not match.', 'error');

    const data = await api('/auth/register', { method: 'POST', body: JSON.stringify(form) });
    if (!data || data._status) return showToast(data?.message || 'Registration failed.', 'error');

    setSession(data.token, data.user);
    showToast('Account created!', 'success');
    navigate('dashboard');
}
</script>

<template>
    <div class="auth-bg">
        <div class="auth-card">
            <div class="auth-logo"><img :src="'/logo.png'" alt="G-SHT"></div>
            <form class="auth-form" @submit.prevent="register">
                <div class="auth-title gold-text">Create Account</div>
                <div class="auth-subtitle">Join the VIP club</div>
                <div class="form-group"><label class="label">Name</label><input v-model="form.name" type="text" class="input" placeholder="Your name"></div>
                <div class="form-group"><label class="label">Email</label><input v-model="form.email" type="email" class="input" placeholder="email@example.com"></div>
                <div class="form-group"><label class="label">Password</label><input v-model="form.password" type="password" class="input" placeholder="Min 8 characters"></div>
                <div class="form-group"><label class="label">Confirm Password</label><input v-model="form.password_confirmation" type="password" class="input" placeholder="Repeat password"></div>
                <button class="btn btn-gold w-full py-3">Create Account</button>
                <div class="auth-divider">Already a member? <a href="#" @click.prevent="navigate('login')">Sign in</a></div>
            </form>
        </div>
    </div>
</template>
