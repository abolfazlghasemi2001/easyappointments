/* Interactions for the public barbershop landing and its accessible showcases. */
(function (global) {
    'use strict';

    function emitChange(element) {
        if (!element) return;
        if (global.jQuery) global.jQuery(element).trigger('change');
        else element.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function scrollToBooking() {
        const target = document.getElementById('wizard-frame-1') || document.getElementById('booking-flow');
        target?.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
    }

    function selectService(serviceId, shouldScroll = true) {
        const select = document.getElementById('select-service');
        if (!select || !serviceId) return false;
        const option = Array.from(select.options).find((item) => String(item.value) === String(serviceId));
        if (!option) return false;

        select.value = option.value;
        emitChange(select);
        if (shouldScroll) {
            global.setTimeout(scrollToBooking, 60);
            global.setTimeout(() => document.getElementById('select-provider')?.focus({ preventScroll: true }), 450);
        }
        return true;
    }

    function selectProvider(providerId, serviceIds) {
        const select = document.getElementById('select-provider');
        if (!select || !providerId) return false;
        const preferredService = document.getElementById('select-service')?.value;
        const serviceId = serviceIds.includes(String(preferredService)) ? preferredService : serviceIds[0];
        if (!serviceId || !selectService(serviceId, false)) return false;

        const providerOption = Array.from(select.options).find((item) => String(item.value) === String(providerId));
        if (!providerOption) return false;
        select.value = providerOption.value;
        emitChange(select);
        global.setTimeout(scrollToBooking, 60);
        return true;
    }

    function bindShowcaseActions() {
        document.querySelectorAll('[data-booking-start]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                if (global.EATours?.onboarding?.active) global.EATours.onboarding.finish();
                scrollToBooking();
                global.setTimeout(() => {
                    if (!document.getElementById('ea-tour-root') && global.EATours?.booking) {
                        global.EATours.booking.start();
                    }
                }, 650);
            });
        });

        document.querySelectorAll('.service-shortcut').forEach((card) => {
            card.addEventListener('click', () => selectService(card.dataset.serviceId));
        });

        document.querySelectorAll('[data-provider-select]').forEach((button) => {
            button.addEventListener('click', () => {
                const card = button.closest('.provider-shortcut');
                const serviceIds = (card?.dataset.providerServices || '').split(',').filter(Boolean);
                selectProvider(button.dataset.providerSelect, serviceIds);
            });
        });
    }

    function initialize() {
        bindShowcaseActions();

        const page = document.body.classList.contains('ea-booking');
        if (!page) return;

        const tourRootObserver = new MutationObserver(() => {
            const tourOpen = Boolean(document.getElementById('ea-tour-root'));
            document.body.classList.toggle('ea-tour-active', tourOpen);
        });
        tourRootObserver.observe(document.body, { childList: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
})(window);
