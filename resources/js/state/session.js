import { reactive } from 'vue';

export const session = reactive({
    token: localStorage.getItem('token'),
    user: null,
    isAdmin: false,
});

export function setSession(token, user) {
    session.token = token;
    session.user = user;
    localStorage.setItem('token', token);
}

export function clearSession() {
    session.token = null;
    session.user = null;
    session.isAdmin = false;
    localStorage.removeItem('token');
}

