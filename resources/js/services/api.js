import { clearSession, session } from '../state/session';
import { navigate } from '../state/router';
import { showToast } from './toast';

const API = '/api/v1';

export async function api(path, options = {}) {
    const headers = { 'Content-Type': 'application/json', Accept: 'application/json' };

    if (session.token) {
        headers.Authorization = `Bearer ${session.token}`;
    }

    try {
        const res = await fetch(`${API}${path}`, { ...options, headers });
        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            if (res.status === 401 && session.token) {
                clearSession();
                navigate('login');
                return null;
            }

            return { ...data, _status: res.status };
        }

        return data;
    } catch (error) {
        showToast('Network error. Check connection.', 'error');
        return null;
    }
}

