/**
 * Shared confirmation modal utility.
 * Dispatches a custom window event and returns a Promise that resolves with the user's choice.
 *
 * @module utils/confirm
 */

export function showConfirm(title, message, confirmText = 'Confirm', cancelText = 'Cancel', confirmStyle = 'primary') {
    return new Promise((resolve) => {
        window.dispatchEvent(new CustomEvent('confirm:show', {
            detail: {
                title,
                message,
                confirmText,
                cancelText,
                confirmStyle,
                resolve
            }
        }));
    });
}
