/* Accessible, dependency-free scroll-snap carousel for the public barber showcases. */
(function (global) {
    'use strict';

    const reducedMotion = global.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

    function initializeCarousel(root) {
        const track = root.querySelector('[data-carousel-track]');
        const slides = track ? Array.from(track.children) : [];

        if (!track || slides.length < 2) {
            root.querySelectorAll('[data-carousel-prev], [data-carousel-next], [data-carousel-dots]').forEach((control) => {
                control.hidden = true;
            });
            return;
        }

        const previousButton = root.querySelector('[data-carousel-prev]');
        const nextButton = root.querySelector('[data-carousel-next]');
        const dotsContainer = root.querySelector('[data-carousel-dots]');
        const dots = [];
        let activeIndex = 0;
        let scrollFrame = 0;
        let autoplayTimer = null;
        let isVisible = true;
        const autoplayDelay = Number(root.dataset.autoplay || 0);

        if (dotsContainer) {
            slides.forEach((slide, index) => {
                const dot = document.createElement('button');
                dot.type = 'button';
                dot.className = 'ea-carousel__dot';
                const itemLabel = root.dataset.carouselItemLabel || (document.documentElement.dir === 'rtl' ? 'رفتن به اسلاید' : 'Go to slide');
                const ofLabel = document.documentElement.dir === 'rtl' ? 'از' : 'of';
                dot.setAttribute('aria-label', `${itemLabel} ${index + 1} ${ofLabel} ${slides.length}`);
                dot.setAttribute('aria-current', index === 0 ? 'true' : 'false');
                dot.addEventListener('click', () => goTo(index));
                dotsContainer.append(dot);
                dots.push(dot);
            });
        }

        function closestIndex() {
            const center = track.getBoundingClientRect().left + track.clientWidth / 2;
            let closest = 0;
            let distance = Number.POSITIVE_INFINITY;

            slides.forEach((slide, index) => {
                const rect = slide.getBoundingClientRect();
                const slideCenter = rect.left + rect.width / 2;
                const nextDistance = Math.abs(center - slideCenter);
                if (nextDistance < distance) {
                    distance = nextDistance;
                    closest = index;
                }
            });

            return closest;
        }

        function updateControls() {
            activeIndex = closestIndex();
            dots.forEach((dot, index) => dot.setAttribute('aria-current', index === activeIndex ? 'true' : 'false'));
            if (previousButton) previousButton.disabled = false;
            if (nextButton) nextButton.disabled = false;
        }

        function goTo(index) {
            activeIndex = (index + slides.length) % slides.length;
            const slide = slides[activeIndex];
            slide.scrollIntoView({
                block: 'nearest',
                inline: 'center',
                behavior: reducedMotion ? 'auto' : 'smooth',
            });
            updateControls();
        }

        function stopAutoplay() {
            if (autoplayTimer) {
                global.clearInterval(autoplayTimer);
                autoplayTimer = null;
            }
        }

        function startAutoplay() {
            stopAutoplay();
            if (!autoplayDelay || reducedMotion || !isVisible || document.hidden || document.getElementById('ea-tour-root')) return;
            autoplayTimer = global.setInterval(() => goTo(activeIndex + 1), autoplayDelay);
        }

        previousButton?.addEventListener('click', () => goTo(activeIndex - 1));
        nextButton?.addEventListener('click', () => goTo(activeIndex + 1));

        root.addEventListener('keydown', (event) => {
            if (event.altKey || event.ctrlKey || event.metaKey) return;
            if (event.key === 'ArrowRight') {
                event.preventDefault();
                goTo(activeIndex + (document.documentElement.dir === 'rtl' ? -1 : 1));
            } else if (event.key === 'ArrowLeft') {
                event.preventDefault();
                goTo(activeIndex + (document.documentElement.dir === 'rtl' ? 1 : -1));
            } else if (event.key === 'Home') {
                event.preventDefault();
                goTo(0);
            } else if (event.key === 'End') {
                event.preventDefault();
                goTo(slides.length - 1);
            }
        });

        track.addEventListener('scroll', () => {
            if (scrollFrame) global.cancelAnimationFrame(scrollFrame);
            scrollFrame = global.requestAnimationFrame(updateControls);
        }, { passive: true });

        root.addEventListener('mouseenter', stopAutoplay);
        root.addEventListener('mouseleave', startAutoplay);
        root.addEventListener('focusin', stopAutoplay);
        root.addEventListener('focusout', (event) => {
            if (!root.contains(event.relatedTarget)) startAutoplay();
        });
        root.addEventListener('pointerdown', stopAutoplay, { passive: true });
        root.addEventListener('pointerup', () => global.setTimeout(startAutoplay, 7000), { passive: true });
        document.addEventListener('visibilitychange', () => (document.hidden ? stopAutoplay() : startAutoplay()));

        if ('IntersectionObserver' in global) {
            const observer = new IntersectionObserver(([entry]) => {
                isVisible = entry.isIntersecting;
                isVisible ? startAutoplay() : stopAutoplay();
            }, { threshold: 0.15 });
            observer.observe(root);
        } else {
            startAutoplay();
        }

        if ('MutationObserver' in global) {
            const pageDocument = global.document;
            const tourObserver = new global.MutationObserver(() => {
                if (!pageDocument.body) return;
                pageDocument.getElementById('ea-tour-root') ? stopAutoplay() : startAutoplay();
            });
            tourObserver.observe(pageDocument.body, { childList: true });
        }

        root.dataset.carouselReady = 'true';
        updateControls();
        startAutoplay();
    }

    function initializeAll() {
        document.querySelectorAll('[data-carousel]').forEach(initializeCarousel);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeAll, { once: true });
    } else {
        initializeAll();
    }

    global.EALuxuryCarousel = { initialize: initializeAll };
})(window);
