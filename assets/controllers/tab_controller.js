import { Controller } from '@hotwired/stimulus';

// =============================================================================
//  TAB CONTROLLER
//  Generic tab-switcher. Buttons declare which panel ID to show via data-tab.
//  Panels are found by their HTML id attribute (works across included sub-templates).
//
//  Usage:
//    <div data-controller="tab">
//      <button data-tab-target="btn" data-action="click->tab#switch" data-tab="panel-a" class="tab-btn tab-btn--active">Tab 1</button>
//      <button data-tab-target="btn" data-action="click->tab#switch" data-tab="panel-b" class="tab-btn">Tab 2</button>
//    </div>
//    <div id="panel-a">Content 1</div>
//    <div id="panel-b" class="hidden">Content 2</div>
// =============================================================================

export default class extends Controller {
    static targets = ['btn'];

    connect() {
        // Show the panel referenced by the first active button; hide all others
        const activeBtn = this.btnTargets.find(b => b.classList.contains('tab-btn--active'))
            || this.btnTargets[0];

        if (activeBtn) {
            this._activate(activeBtn);
        }
    }

    switch(e) {
        this._activate(e.currentTarget);
    }

    _activate(activeBtn) {
        const targetId = activeBtn.dataset.tab;

        // Show/hide panels by ID, toggle button active state
        this.btnTargets.forEach(btn => {
            const panelId = btn.dataset.tab;
            const panel = document.getElementById(panelId);
            if (panel) {
                panel.classList.toggle('hidden', panelId !== targetId);
            }
            btn.classList.toggle('tab-btn--active', btn === activeBtn);
        });
    }
}
