/* First-visit introduction for the public appointment page. */
(function (global) {
    'use strict';

    const fa = document.documentElement.lang === 'fa' || document.documentElement.lang.startsWith('fa-');
    const words = fa
        ? {
              welcome: 'به تجربه‌ای دقیق و آرام خوش آمدید',
              welcomeText: 'اینجا معرفی فضای رزرو شماست. دکمهٔ راهنما را هر زمان خواستید می‌توانید دوباره باز کنید.',
              services: 'خدمات را ببینید',
              servicesText: 'مدت و هزینهٔ هر خدمت را مرور کنید؛ با انتخاب کارت، همان خدمت برای رزرو انتخاب می‌شود.',
              team: 'آرایشگر دلخواهتان را انتخاب کنید',
              teamText: 'اعضای تیم و خدمت‌های قابل ارائه را اینجا می‌بینید.',
              booking: 'برای رزرو آماده‌اید؟',
              bookingText: 'با این دکمه به مراحل رزرو می‌روید. اگر بعداً خواستید، راهنمای رزرو هم در دسترس است.',
          }
        : {
              welcome: 'Welcome to a calmer way to book',
              welcomeText: 'This is your booking experience. Open the guide again any time from the help button.',
              services: 'Explore the services',
              servicesText: 'Review duration and price. Selecting a card carries that service into the booking flow.',
              team: 'Choose your barber',
              teamText: 'Meet the available team and see which services they can provide.',
              booking: 'Ready to book?',
              bookingText: 'This button takes you to the booking steps. The booking guide is here whenever you need it.',
          };

    global.EATours = global.EATours || {};
    global.EATours.onboarding = new global.SpotlightTour({
        id: 'onboarding',
        showProgress: true,
        allowSkip: true,
        keyboardNavigation: true,
        persistProgress: true,
        steps: [
            { element: '.barber-hero__copy', title: words.welcome, description: words.welcomeText, position: 'bottom' },
            { element: '#barber-services', title: words.services, description: words.servicesText, position: 'top' },
            { element: '#barber-team', title: words.team, description: words.teamText, position: 'top' },
            { element: '.booking-start-button', title: words.booking, description: words.bookingText, position: 'bottom' },
        ],
    });

    function bindOnboarding() {
        document.querySelectorAll('[data-start-onboarding-tour]').forEach((button) => {
            button.addEventListener('click', () => global.EATours.onboarding.start({ force: true, resume: true }));
        });

        if (!global.vars?.('manage_mode') && document.getElementById('barber-hero')) {
            global.setTimeout(() => global.EATours.onboarding.start(), 900);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindOnboarding, { once: true });
    } else {
        bindOnboarding();
    }
})(window);
