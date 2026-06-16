const stack = [];
let listening = false;
let skipNextPop = false;

function onPopState() {
    if (skipNextPop) {
        skipNextPop = false;
        return;
    }

    const entry = stack.pop();
    entry?.onClose(true);

    if (stack.length === 0 && listening) {
        window.removeEventListener('popstate', onPopState);
        listening = false;
    }
}

function ensureListener() {
    if (listening) {
        return;
    }

    listening = true;
    window.addEventListener('popstate', onPopState);
}

export function registerModalHistory(onClose) {
    history.pushState({ fantagerModal: stack.length }, '');
    const entry = { onClose };
    stack.push(entry);
    ensureListener();

    return entry;
}

export function unregisterModalHistory(entry, fromPopState = false) {
    const idx = stack.indexOf(entry);
    if (idx === -1) {
        return;
    }

    stack.splice(idx, 1);

    if (!fromPopState) {
        skipNextPop = true;
        history.back();
    }

    if (stack.length === 0 && listening) {
        window.removeEventListener('popstate', onPopState);
        listening = false;
    }
}
