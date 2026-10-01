/* Register the conservative static-only service worker when served from a secure context. */
(function (global) {
    'use strict';

    function register() {
        if (!('serviceWorker' in navigator) || !global.isSecureContext) return;

        const base = (global.EA_BASE_URL || global.location.origin).replace(/\/$/, '') + '/';
        const workerUrl = new URL('service-worker.js', base);
        const scopeUrl = new URL('./', base);

        navigator.serviceWorker.register(workerUrl.href, { scope: scopeUrl.pathname }).catch((error) => {
            // PWA support is optional; booking remains fully usable when unavailable.
            console.info('Offline asset cache was not registered.', error);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', register, { once: true });
    } else {
        register();
    }
})(window);
