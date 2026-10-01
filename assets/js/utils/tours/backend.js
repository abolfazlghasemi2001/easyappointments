/* First-entry tours for staff. Selectors are role-aware and safe when a user lacks a permission. */
(function (global) {
    'use strict';

    const fa = document.documentElement.lang === 'fa' || document.documentElement.lang.startsWith('fa-');
    const copy = fa
        ? {
              admin: [
                  ['نمای کلی امروز', 'تقویم، نوبت‌ها و برنامهٔ روز را از اینجا مدیریت کنید.'],
                  ['منوی امکانات', 'از منوی کناری به تقویم، مشتریان، خدمات و کاربران دسترسی دارید.'],
                  ['اعلان‌های سیستم', 'نتیجهٔ عملیات و خطاهای قابل‌اقدام به‌صورت اعلان کوتاه نمایش داده می‌شوند.'],
                  ['تنظیمات و حساب', 'تنظیمات مجموعه و اطلاعات حساب کاربری از این منو در دسترس‌اند.'],
              ],
              provider: [
                  ['تقویم شخصی', 'نوبت‌های برنامهٔ کاری خود را در تقویم ببینید.'],
                  ['مشتریان', 'در صورت داشتن دسترسی، فهرست مشتریان و سوابق نوبت را از این بخش باز کنید.'],
                  ['زمان‌های مسدود', 'برای ثبت مرخصی یا زمان غیرقابل رزرو، از گزینهٔ عدم دسترس‌پذیری استفاده کنید.'],
              ],
          }
        : {
              admin: [
                  ['Today at a glance', 'Manage the day’s appointments and working schedule from the calendar.'],
                  ['Main navigation', 'Use the side menu to reach the calendar, customers, services and users.'],
                  ['System notifications', 'Important results and actionable errors appear as brief notifications.'],
                  ['Settings and account', 'Business settings and your account are available from this menu.'],
              ],
              provider: [
                  ['Your calendar', 'Review appointments in your personal working schedule.'],
                  ['Customers', 'If your role allows it, open the customer list and appointment history here.'],
                  ['Blocked time', 'Use unavailability to mark leave or time that cannot be booked.'],
              ],
          };

    const commonOptions = {
        showProgress: true,
        allowSkip: true,
        keyboardNavigation: true,
        persistProgress: true,
    };

    global.EATours = global.EATours || {};
    global.EATours.admin = new global.SpotlightTour({
        ...commonOptions,
        id: 'admin',
        steps: [
            { element: '#calendar-page', title: copy.admin[0][0], description: copy.admin[0][1], position: 'bottom' },
            { element: '#header-menu', title: copy.admin[1][0], description: copy.admin[1][1], position: 'right' },
            { element: '#notification', title: copy.admin[2][0], description: copy.admin[2][1], position: 'top' },
            { element: '[data-tour-settings]', title: copy.admin[3][0], description: copy.admin[3][1], position: 'bottom' },
        ],
    });

    global.EATours.provider = new global.SpotlightTour({
        ...commonOptions,
        id: 'provider',
        steps: [
            { element: '#calendar-page', title: copy.provider[0][0], description: copy.provider[0][1], position: 'bottom' },
            { element: '#header-menu a[href*="customers"]', title: copy.provider[1][0], description: copy.provider[1][1], position: 'bottom' },
            { element: '#insert-unavailability', title: copy.provider[2][0], description: copy.provider[2][1], position: 'bottom' },
        ],
    });

    function bindBackendTours() {
        const role = document.body.dataset.eaTourRole;
        const tour = role === 'admin' ? global.EATours.admin : role === 'provider' ? global.EATours.provider : null;
        if (!tour) return;

        document.querySelectorAll('[data-ea-tour-help]').forEach((button) => {
            button.addEventListener('click', () => tour.start({ force: true, resume: true }));
        });

        global.setTimeout(() => tour.start(), 750);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindBackendTours, { once: true });
    } else {
        bindBackendTours();
    }
})(window);
