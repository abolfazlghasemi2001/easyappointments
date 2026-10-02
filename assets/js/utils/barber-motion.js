/* Progressive motion: never hold the page hostage to an image or network request. */
(function () {
    'use strict';
    const loader = document.getElementById('ea-page-loader');
    const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)');
    if (loader) loader.hidden = false;
    const dismiss = () => {
        if (loader) loader.hidden = true;
    };
    const fallback = window.setTimeout(dismiss, 4000);
    window.addEventListener('pageshow', dismiss);

    function initialize() {
        requestAnimationFrame(() => {
            dismiss();
            clearTimeout(fallback);
        });
        if (window.jQuery) {
            const feedback = document.getElementById('ea-request-loader');
            let delay;
            jQuery(document)
                .on('ajaxStart.barberMotion', () => {
                    clearTimeout(delay);
                    delay = setTimeout(() => {
                        if (feedback) feedback.hidden = false;
                    }, 180);
                })
                .on('ajaxStop.barberMotion', () => {
                    clearTimeout(delay);
                    if (feedback) feedback.hidden = true;
                });
            if (reduced?.matches) jQuery.fx.off = true;
            reduced?.addEventListener?.('change', (event) => {
                jQuery.fx.off = event.matches;
            });
        }
        if ('IntersectionObserver' in window && !reduced?.matches) {
            const observer = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        if (!entry.isIntersecting) return;
                        entry.target.classList.add('ea-entered');
                        observer.unobserve(entry.target);
                    });
                },
                {threshold: 0.12},
            );
            document.querySelectorAll('.showcase-section__inner').forEach((el) => observer.observe(el));
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, {once: true});
    else initialize();
})();
