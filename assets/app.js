import './stimulus_bootstrap.js';
// Entry point for the app
import '@hotwired/turbo';
import { Application } from '@hotwired/stimulus';
import './styles/app.scss';

const app = Application.start();

// Dynamically import and register all controllers from the "./controllers" directory
const context = require.context('./controllers', true, /\.js$/);
context.keys().forEach((key) => {
    const name = key
        .replace(/^\.\//, '')
        .replace(/_controller\.js$/, '')
        .replace(/\//g, '--')
        .replace(/_/g, '-');

    const controllerModule = context(key);
    app.register(name, controllerModule.default);
});

console.log('Fantager assets loaded');
