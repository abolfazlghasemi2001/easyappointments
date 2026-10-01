/* Booking-flow orientation. Hidden wizard frames are presented as centered guide steps until they become active. */
(function (global) {
    'use strict';

    const fa = document.documentElement.lang === 'fa' || document.documentElement.lang.startsWith('fa-');
    const copy = fa
        ? [
              ['انتخاب خدمت', 'خدمت موردنظرتان را انتخاب کنید؛ مدت و هزینه را پیش از ادامه می‌بینید.'],
              ['انتخاب آرایشگر', 'پس از انتخاب خدمت، آرایشگرهای در دسترس همان خدمت نمایش داده می‌شوند.'],
              ['انتخاب تاریخ', 'در تقویم جلالی، روز مناسب خود را انتخاب کنید.'],
              ['انتخاب ساعت', 'از بین زمان‌های آزاد، ساعت مناسب را لمس کنید.'],
              ['تأیید نهایی', 'اطلاعات نوبت را مرور کنید و پس از تکمیل اطلاعات، رزرو را تأیید کنید.'],
          ]
        : [
              ['Choose a service', 'Select the service you want. Duration and price are shown before you continue.'],
              ['Choose a barber', 'After choosing a service, the available barbers for it appear here.'],
              ['Choose a date', 'Pick a suitable day from the Jalali calendar.'],
              ['Choose a time', 'Select one of the available appointment times.'],
              ['Review and confirm', 'Review the appointment, enter your details and confirm when everything is correct.'],
          ];

    global.EATours = global.EATours || {};
    global.EATours.booking = new global.SpotlightTour({
        id: 'booking',
        showProgress: true,
        allowSkip: true,
        keyboardNavigation: true,
        persistProgress: true,
        steps: [
            { element: '.booking-service-field', title: copy[0][0], description: copy[0][1], position: 'bottom' },
            { element: '.booking-provider-field', title: copy[1][0], description: copy[1][1], position: 'bottom' },
            { element: '.booking-date-field', title: copy[2][0], description: copy[2][1], position: 'bottom' },
            { element: '#available-hours', title: copy[3][0], description: copy[3][1], position: 'left' },
            { element: '#wizard-frame-4', title: copy[4][0], description: copy[4][1], position: 'top' },
        ],
    });

    function bindBookingGuide() {
        document.querySelectorAll('[data-start-booking-tour]').forEach((button) => {
            button.addEventListener('click', () => global.EATours.booking.start({ force: true, resume: true }));
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindBookingGuide, { once: true });
    } else {
        bindBookingGuide();
    }
})(window);
