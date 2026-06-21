/**
 * Locale helpers — read document language and format numbers/dates consistently.
 * Locale comes from <html lang="…"> (set in base.html.twig from app.request.locale).
 */

export function getDocumentLocale() {
    const lang = document.documentElement.getAttribute('lang');
    return lang && lang.trim() !== '' ? lang : 'en';
}

export function formatNumber(value, options = {}) {
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) {
        return String(value ?? '');
    }

    return numeric.toLocaleString(getDocumentLocale(), options);
}

export function formatDateTime(value, options = undefined) {
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleString(getDocumentLocale(), options);
}

export function formatBadgeCount(count, overflowLabel = '99+') {
    const numeric = Number(count);
    if (!Number.isFinite(numeric) || numeric <= 0) {
        return '0';
    }

    return numeric > 99 ? overflowLabel : String(numeric);
}
