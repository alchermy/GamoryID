import './bootstrap';

document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
    if (!(toggle instanceof HTMLButtonElement)) return;
    toggle.addEventListener('click', () => {
        const inputId = toggle.dataset.passwordToggle;
        const input = inputId ? document.getElementById(inputId) : null;
        if (!(input instanceof HTMLInputElement)) return;

        const revealing = input.type === 'password';
        input.type = revealing ? 'text' : 'password';
        toggle.textContent = revealing ? 'ซ่อน' : 'แสดง';
        toggle.setAttribute('aria-pressed', String(revealing));
        toggle.setAttribute('aria-label', revealing ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน');
        input.focus();
    });
});

const errorSummary = document.querySelector('[data-error-summary]');
if (errorSummary instanceof HTMLElement) errorSummary.focus();

document.querySelectorAll('form').forEach((form) => {
    if (!(form instanceof HTMLFormElement)) return;
    form.addEventListener('submit', () => {
        form.querySelectorAll('button[type="submit"]').forEach((button) => {
            if (!(button instanceof HTMLButtonElement)) return;
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        });
    });
});

const confirmDialog = document.getElementById('admin-confirm-dialog');
if (confirmDialog instanceof HTMLDialogElement) {
    const description = confirmDialog.querySelector('[data-confirm-description]');
    const confirmButton = confirmDialog.querySelector('[data-confirm-submit]');
    const cancelButton = confirmDialog.querySelector('[data-confirm-cancel]');
    let pendingForm = null;
    let pendingButton = null;

    const clearPending = ({ restoreFocus = true } = {}) => {
        const trigger = pendingButton;
        pendingForm = null;
        pendingButton = null;
        if (restoreFocus && trigger instanceof HTMLButtonElement) trigger.focus();
    };

    document.querySelectorAll('[data-admin-confirm]').forEach((trigger) => {
        if (!(trigger instanceof HTMLButtonElement)) return;
        trigger.addEventListener('click', () => {
            const form = trigger.closest('form');
            if (!(form instanceof HTMLFormElement)) return;

            const requiredName = trigger.dataset.confirmRequires;
            if (requiredName) {
                const requiredField = form.querySelector(`[name="${requiredName}"]`);
                const error = form.querySelector('[data-review-error]');
                if (requiredField instanceof HTMLTextAreaElement && requiredField.value.trim().length < 3) {
                    requiredField.setAttribute('aria-invalid', 'true');
                    if (error instanceof HTMLElement) error.textContent = 'กรุณาระบุเหตุผลอย่างน้อย 3 ตัวอักษร';
                    requiredField.focus();
                    return;
                }
                requiredField?.removeAttribute('aria-invalid');
                if (error instanceof HTMLElement) error.textContent = '';
            }

            pendingForm = form;
            pendingButton = trigger;
            if (description) description.textContent = trigger.dataset.adminConfirm || 'ยืนยันการทำรายการนี้?';
            if (confirmButton instanceof HTMLButtonElement) {
                confirmButton.textContent = trigger.dataset.confirmLabel || 'ยืนยัน';
                confirmButton.className = `button ${trigger.dataset.confirmIntent || ''}`.trim();
            }
            confirmDialog.showModal();
            if (cancelButton instanceof HTMLButtonElement) cancelButton.focus();
        });
    });

    cancelButton?.addEventListener('click', () => {
        confirmDialog.close();
        clearPending();
    });
    confirmButton?.addEventListener('click', () => {
        if (!(pendingForm instanceof HTMLFormElement) || !(pendingButton instanceof HTMLButtonElement)) return;
        const form = pendingForm;
        const trigger = pendingButton;
        const hiddenDecision = form.querySelector('input[name="decision"]');
        if (hiddenDecision instanceof HTMLInputElement) hiddenDecision.value = trigger.value;
        confirmDialog.close();
        clearPending({ restoreFocus: false });
        form.requestSubmit();
    });
    confirmDialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        confirmDialog.close();
        clearPending();
    });
}
