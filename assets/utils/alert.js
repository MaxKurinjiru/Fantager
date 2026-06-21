/**
 * Shared alert/feedback utility for Stimulus controllers.
 *
 * Elements must already carry the `.alert` base class in their template
 * (e.g. via `alert_box.html.twig`). This utility only switches the type
 * modifier and toggles visibility — it never resets the full `className`
 * or hard-codes colour utilities.
 *
 * @module utils/alert
 */

/**
 * Show an alert element with the given type and message.
 *
 * @param {HTMLElement} containerEl  - Element with `.alert` base class
 * @param {HTMLElement} messageEl    - Element that receives the message text
 * @param {'success'|'error'|'warning'|'info'} type
 * @param {string} message
 */
export function showAlert(containerEl, messageEl, type, message) {
    messageEl.textContent = message;
    containerEl.classList.remove('alert-success', 'alert-error', 'alert-warning', 'alert-info', 'hidden');
    containerEl.classList.add(`alert-${type}`);
}

/**
 * Hide an alert element and strip its type modifier.
 *
 * @param {HTMLElement} containerEl
 */
export function hideAlert(containerEl) {
    containerEl.classList.add('hidden');
    containerEl.classList.remove('alert-success', 'alert-error', 'alert-warning', 'alert-info');
}
