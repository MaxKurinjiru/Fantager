const DEFAULT_PRIMARY = '#10b981';
const DEFAULT_SECONDARY = '#0f1720';

export function applyTeamColors(element, colors) {
    if (!element) return;
    element.style.setProperty('--team-color-primary', colors?.primary ?? DEFAULT_PRIMARY);
    element.style.setProperty('--team-color-secondary', colors?.secondary ?? DEFAULT_SECONDARY);
}
