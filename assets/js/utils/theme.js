/* Small persisted light/dark theme switch. Bootstrap's data-bs-theme handles built-in components. */
(function (global) {
    'use strict';

    const key = 'ea-theme';
    const root = document.documentElement;

    function readTheme() {
        try {
            const stored = global.localStorage.getItem(key);
            return stored === 'dark' ? 'dark' : 'light';
        } catch (error) {
            return 'light';
        }
    }

    function setTheme(theme, persist = true) {
        const next = theme === 'dark' ? 'dark' : 'light';
        root.dataset.bsTheme = next;
        root.style.colorScheme = next;

        if (persist) {
            try {
                global.localStorage.setItem(key, next);
            } catch (error) {
                // The theme remains available for this visit even when storage is disabled.
            }
        }

        document.querySelectorAll('[data-ea-theme-toggle]').forEach((button) => {
            const icon = button.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-sun', next === 'dark');
                icon.classList.toggle('fa-moon', next !== 'dark');
            }
            const label = next === 'dark' ? 'switch_to_light_mode' : 'switch_to_dark_mode';
            const fallback = next === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';
            const accessibleLabel = typeof global.lang === 'function' ? global.lang(label) : fallback;
            button.setAttribute('aria-pressed', next === 'dark' ? 'true' : 'false');
            button.setAttribute('aria-label', accessibleLabel);
            button.title = accessibleLabel;
        });
    }

    function initialize() {
        setTheme(readTheme(), false);
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-ea-theme-toggle]');
            if (!button) return;
            setTheme(root.dataset.bsTheme === 'dark' ? 'light' : 'dark');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }

    global.EATheme = { set: setTheme };
})(window);
