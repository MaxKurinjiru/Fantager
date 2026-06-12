import { Controller } from '@hotwired/stimulus';

// =============================================================================
//  FLASH CONTROLLER
//  Handles dismissing flash/alert messages.
//  The controller element is the .alert div itself (or a wrapper).
//
//  Usage (on the alert element):
//    <div class="alert alert-success" data-controller="flash" role="alert">
//      ...
//      <button data-action="click->flash#dismiss">✕</button>
//    </div>
// =============================================================================

export default class extends Controller {
    connect() {}

    disconnect() {}

    dismiss(e) {
        e.preventDefault();
        // Remove the closest alert element
        this.element.remove();
    }
}
