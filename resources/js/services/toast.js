import { reactive } from 'vue';

let id = 0;

export const toasts = reactive([]);

export function showToast(message, type = 'info') {
    const toast = { id: ++id, message, type, leaving: false };
    toasts.push(toast);

    setTimeout(() => {
        toast.leaving = true;
        setTimeout(() => {
            const index = toasts.findIndex((item) => item.id === toast.id);
            if (index !== -1) toasts.splice(index, 1);
        }, 300);
    }, 3500);
}

