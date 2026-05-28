import { reactive } from 'vue';

export const modal = reactive({
    open: false,
    title: '',
    description: '',
    inputLabel: '',
    inputValue: '',
    inputPlaceholder: '',
    confirmText: 'Confirm',
    confirmClass: 'btn-gold',
    showInput: false,
    onConfirm: null,
});

export function showModal(options) {
    Object.assign(modal, {
        open: true,
        title: options.title || '',
        description: options.description || '',
        inputLabel: options.inputLabel || 'Value',
        inputValue: options.inputValue || '',
        inputPlaceholder: options.inputPlaceholder || '',
        confirmText: options.confirmText || 'Confirm',
        confirmClass: options.confirmClass || 'btn-gold',
        showInput: Boolean(options.showInput),
        onConfirm: options.onConfirm || null,
    });
}

export function closeModal() {
    modal.open = false;
    modal.onConfirm = null;
}

