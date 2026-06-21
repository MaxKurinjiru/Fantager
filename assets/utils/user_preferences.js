let closeModalOnBackdrop = false;
let initialized = false;

function readInitialValue() {
    const el = document.querySelector('[data-user-pref-close-modal-on-backdrop]');
    if (el) {
        closeModalOnBackdrop = el.dataset.userPrefCloseModalOnBackdrop === 'true';
    }
}

export function isCloseModalOnBackdropEnabled() {
    if (!initialized) {
        readInitialValue();
        initialized = true;
    }

    return closeModalOnBackdrop;
}

export function setCloseModalOnBackdrop(value) {
    closeModalOnBackdrop = Boolean(value);
    initialized = true;
}
