/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * ---------------------------------------------------------------------------- */

/**
 * Jalali calendar integration for the flatpickr date pickers.
 *
 * The plugin keeps the internal flatpickr state untouched (selected dates remain Gregorian Date objects, which is what
 * the rest of the application expects) while it renders a Jalali month grid inside the picker popup and formats the
 * input value with the Jalali date and Persian numerals.
 *
 * Usage: App.Utils.JalaliPicker.initialize($input, flatpickrOptions);
 */
window.App.Utils.JalaliPicker = (function () {
    const STATE_KEY = 'eaJalaliPicker';

    /**
     * Initialize a date picker input with the Jalali calendar support.
     *
     * @param {jQuery} $target Target input element.
     * @param {Object} [params] Extra flatpickr options.
     *
     * @return {Object} Returns the flatpickr instance.
     */
    function initialize($target, params = {}) {
        if (!$target || !$target.length) {
            throw new Error('Empty $target argument provided.');
        }

        const jalaliEnabled = App.Utils.Jalali.enabled();

        const options = { ...params };

        if (jalaliEnabled) {
            options.plugins = [...(options.plugins || []), createPlugin()];

            options.formatDate = function (date, format) {
                return formatValue(date, format);
            };

            options.parseDate = function (dateString, format) {
                return parseValue(dateString, format);
            };

            if (options.locale) {
                options.locale = {
                    ...options.locale,
                    months: {
                        shorthand: App.Utils.Jalali.MONTH_NAMES.map((name) => name.slice(0, 5)),
                        longhand: App.Utils.Jalali.MONTH_NAMES,
                    },
                };
            }
        }

        const instance = $target.flatpickr(options);

        $target.data(STATE_KEY, instance);

        return instance;
    }

    /**
     * Create the flatpickr plugin that renders the Jalali grid.
     *
     * @return {Function}
     */
    function createPlugin() {
        return function (instance) {
            let view = null;

            /**
             * Render the Jalali month grid inside the flatpickr calendar.
             */
            function render() {
                const $calendar = $(instance.calendarContainer);

                const $months = $calendar.find('.flatpickr-months');

                const $inner = $calendar.find('.flatpickr-innerContainer');

                if (!$calendar.length) {
                    return;
                }

                // Hide the Gregorian grid and navigation, the Jalali grid replaces them.
                $months.hide();

                $inner.hide();

                let $wrapper = $calendar.find('.ea-jalali-wrapper');

                if (!$wrapper.length) {
                    $wrapper = $('<div/>', { class: 'ea-jalali-wrapper' });

                    // The calendar container is the ".flatpickr-calendar" element itself, so the wrapper must be
                    // appended to it (a find() call would only look into its descendants and silently do nothing).
                    $calendar.append($wrapper);
                }

                if (!view) {
                    view = currentView(instance);
                }

                // Make the displayed Jalali month available to the page scripts (see getDisplayedMonth()).
                instance.eaJalaliView = { ...view };

                const jalali = App.Utils.Jalali;

                const weekdays = jalali.orderedWeekdayNames(true);

                const firstOfMonth = jalali.toGregorian(view.year, view.month, 1);

                const firstWeekday = firstOfMonth.getDay();

                const weekOrder = jalali.weekOrder();

                const leadingDays = weekOrder.indexOf(firstWeekday);

                const monthDays = jalali.monthDays(view.year, view.month);

                const today = jalali.toJalali(new Date());

                const selected = instance.selectedDates[0] ? jalali.toJalali(instance.selectedDates[0]) : null;

                const $header = $('<div/>', { class: 'ea-jalali-header' });

                $('<button/>', {
                    type: 'button',
                    class: 'ea-jalali-nav ea-jalali-prev',
                    html: '&#8250;',
                    'aria-label': lang('previous'),
                })
                    .on('click', function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        changeMonth(-1);
                    })
                    .appendTo($header);

                $('<div/>', {
                    class: 'ea-jalali-title',
                    text: `${jalali.monthName(view.month)} ${jalali.toPersianDigits(view.year)}`,
                }).appendTo($header);

                $('<button/>', {
                    type: 'button',
                    class: 'ea-jalali-nav ea-jalali-next',
                    html: '&#8249;',
                    'aria-label': lang('next'),
                })
                    .on('click', function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        changeMonth(1);
                    })
                    .appendTo($header);

                $wrapper.empty().append($header);

                const $table = $('<table/>', { class: 'ea-jalali-table' });

                const $head = $('<thead/>');

                const $headRow = $('<tr/>');

                weekdays.forEach((weekday) => {
                    $('<th/>', { text: weekday }).appendTo($headRow);
                });

                $head.append($headRow);

                $table.append($head);

                const $body = $('<tbody/>');

                let dayNumber = 1 - leadingDays;

                for (let week = 0; week < 6; week++) {
                    const $row = $('<tr/>');

                    for (let weekdayIndex = 0; weekdayIndex < 7; weekdayIndex++) {
                        if (dayNumber < 1 || dayNumber > monthDays) {
                            $('<td/>', { class: 'ea-jalali-empty' }).appendTo($row);

                            dayNumber++;

                            continue;
                        }

                        const gregorianDate = jalali.toGregorian(view.year, view.month, dayNumber);

                        const $cell = $('<td/>', { class: 'ea-jalali-day' });

                        const $button = $('<button/>', {
                            type: 'button',
                            class: 'ea-jalali-day-button',
                            text: jalali.toPersianDigits(dayNumber),
                            'aria-label': `${jalali.toPersianDigits(dayNumber)} ${jalali.monthName(view.month)} ${jalali.toPersianDigits(view.year)}`,
                            'aria-pressed': 'false',
                        });

                        if (isSheduleDisabled(instance, gregorianDate)) {
                            $cell.addClass('ea-jalali-disabled');

                            $button.prop('disabled', true);
                        }

                        if (
                            selected &&
                            selected.year === view.year &&
                            selected.month === view.month &&
                            selected.day === dayNumber
                        ) {
                            $cell.addClass('ea-jalali-selected');
                            $button.attr('aria-pressed', 'true');
                        }

                        if (today.year === view.year && today.month === view.month && today.day === dayNumber) {
                            $cell.addClass('ea-jalali-today');
                            $button.attr('aria-current', 'date');
                        }

                        $button.on('click', function (event) {
                            event.preventDefault();
                            event.stopPropagation();

                            instance.setDate(gregorianDate, true);

                            instance.close();
                        });

                        $cell.append($button);

                        $row.append($cell);

                        dayNumber++;
                    }

                    $body.append($row);
                }

                $table.append($body);

                $wrapper.append($table);
            }

            /**
             * Move the view to the previous/next Jalali month.
             *
             * @param {Number} step Month step (-1 or 1).
             */
            function changeMonth(step) {
                view.month += step;

                if (view.month > 12) {
                    view.month = 1;
                    view.year++;
                } else if (view.month < 1) {
                    view.month = 12;
                    view.year--;
                }

                render();

                notifyMonthChange(instance);
            }

            /**
             * Notify the page that the displayed month changed.
             *
             * The Gregorian month/year navigation of flatpickr is hidden by this plugin, so the "onMonthChange"
             * callback of the picker options would never fire. It is invoked manually whenever the Jalali view
             * moves to another month.
             *
             * @param {Object} instance flatpickr instance.
             */
            function notifyMonthChange(instance) {
                if (typeof instance.config.onMonthChange === 'function') {
                    instance.config.onMonthChange(instance.selectedDates, instance.input ? instance.input.value : '', instance);
                }
            }

            /**
             * Get the Jalali month that must be initially displayed.
             *
             * @param {Object} instance flatpickr instance.
             *
             * @return {{year: Number, month: Number}}
             */
            function currentView(instance) {
                const reference = instance.selectedDates[0] || instance.config.minDate || new Date();

                const jalali = App.Utils.Jalali.toJalali(reference);

                return { year: jalali.year, month: jalali.month };
            }

            /**
             * Check whether a date is disabled (outside the min/max range or manually disabled).
             *
             * @param {Object} instance flatpickr instance.
             * @param {Date} date Date to check.
             *
             * @return {Boolean}
             */
            function isSheduleDisabled(instance, date) {
                const minDate = instance.config.minDate;

                const maxDate = instance.config.maxDate;

                if (minDate) {
                    const min = new Date(minDate.getFullYear(), minDate.getMonth(), minDate.getDate());

                    if (date < min) {
                        return true;
                    }
                }

                if (maxDate) {
                    const max = new Date(maxDate.getFullYear(), maxDate.getMonth(), maxDate.getDate(), 23, 59, 59);

                    if (date > max) {
                        return true;
                    }
                }

                const disabledDates = instance.config.disable || [];

                const iso = moment(date).format('YYYY-MM-DD');

                const isDisabled = disabledDates.some((disabled) => {
                    if (typeof disabled === 'string') {
                        return disabled === iso;
                    }

                    if (disabled instanceof Date) {
                        return moment(disabled).format('YYYY-MM-DD') === iso;
                    }

                    if (typeof disabled === 'function') {
                        return disabled(date) === true;
                    }

                    return false;
                });

                return isDisabled;
            }

            return {
                onReady(selectedDates, dateStr, instance) {
                    view = currentView(instance);

                    render();
                },

                onOpen(selectedDates, dateStr, instance) {
                    view = currentView(instance);

                    render();
                },

                onMonthChange(selectedDates, dateStr, instance) {
                    render();
                },

                onYearChange(selectedDates, dateStr, instance) {
                    render();
                },

                onValueUpdate() {
                    render();
                },

                onDestroy() {
                    view = null;
                },
            };
        };
    }

    /**
     * Get the flatpickr pattern of the date format that the application is configured with.
     *
     * @return {String}
     */
    function inputDatePattern() {
        const patterns = {
            YMD: 'YYYY/MM/DD',
            DMY: 'DD/MM/YYYY',
            MDY: 'MM/DD/YYYY',
        };

        return patterns[vars('date_format')] || patterns.YMD;
    }

    /**
     * Check whether a value is a Gregorian ISO value.
     *
     * The application fills the picker inputs with ISO values ("YYYY-MM-DD[ HH:mm[:ss]]"), which must keep being
     * treated as Gregorian values. A Jalali value cannot be confused with them, because the Jalali years of today
     * are far below the Gregorian ones (1405 vs 2026), the same applies to the Persian digits that the users type.
     *
     * @param {String} value Input value.
     *
     * @return {Boolean}
     */
    function isGregorianIsoValue(value) {
        const match = App.Utils.Jalali.toLatinDigits(value)
            .trim()
            .match(/^(\d{4})[-/.](\d{1,2})[-/.](\d{1,2})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?$/);

        return Boolean(match) && Number(match[1]) >= 1700;
    }

    /**
     * Reorder a Jalali value into the year/month/day order expected by the Jalali parser.
     *
     * @param {String} value Input value (e.g. "08/07/1405" for a day/month/year installation).
     *
     * @return {String}
     */
    function reorderJalaliValue(value) {
        const reordered = App.Utils.Jalali.toLatinDigits(value).trim();

        if (!/DMY|MDY/.test(String(vars('date_format')))) {
            return reordered;
        }

        const match = reordered.match(/^(\d{1,4})[\/\-.](\d{1,2})[\/\-.](\d{1,4})([\s\d:]*)$/);

        if (!match) {
            return reordered;
        }

        const parts = vars('date_format') === 'DMY' ? [match[3], match[2], match[1]] : [match[3], match[1], match[2]];

        return `${parts.join('/')}${match[4] || ''}`;
    }

    /**
     * Format a date value for the picker input (Jalali when enabled).
     *
     * @param {Date} date Date value.
     * @param {String} format flatpickr format.
     * @param {Object} locale Locale object.
     *
     * @return {String}
     */
    function formatValue(date, format) {
        if (!date) {
            return '';
        }

        const persianDigits = App.Utils.Jalali.persianDigitsEnabled();

        const datePart = App.Utils.Jalali.format(date, inputDatePattern(), persianDigits);

        if (!/[HhiK]/.test(format)) {
            return datePart;
        }

        const timeFormat = vars('time_format') === 'military' ? 'HH:mm' : 'h:mm a';

        const timePart = moment(date).format(timeFormat);

        return `${datePart} ${App.Utils.Jalali.toPersianDigits(timePart)}`;
    }

    /**
     * Parse the value of a picker input.
     *
     * Gregorian ISO values (i.e. the values that the application itself fills in) are parsed as Gregorian, while
     * every other value is treated as a Jalali value that the user typed (in the configured date order and with
     * either ASCII or Persian digits).
     *
     * @param {String} dateString Input value.
     * @param {String} format flatpickr format.
     *
     * @return {Date|undefined}
     */
    function parseValue(dateString, format) {
        if (dateString === null || dateString === undefined || dateString === '') {
            return undefined;
        }

        // Values that are not strings (Date objects, timestamps, ...) are handled by the default flatpickr parser.
        if (typeof dateString !== 'string') {
            return window.flatpickr.parseDate(dateString, format);
        }

        if (isGregorianIsoValue(dateString)) {
            const gregorianDate = moment(
                App.Utils.Jalali.toLatinDigits(dateString),
                ['YYYY-MM-DD HH:mm:ss', 'YYYY-MM-DD HH:mm', 'YYYY-MM-DD'],
                true,
            );

            if (gregorianDate.isValid()) {
                return gregorianDate.toDate();
            }
        }

        const jalaliDate = App.Utils.Jalali.parse(reorderJalaliValue(dateString));

        if (jalaliDate) {
            return jalaliDate;
        }

        // The default flatpickr parser handles the remaining cases (timezone aware values, "today", ...).
        return window.flatpickr.parseDate(dateString, format);
    }

    /**
     * Get the first day of the Jalali month that is currently displayed by the picker.
     *
     * @param {Object} instance flatpickr instance.
     *
     * @return {Date|null} Returns a Gregorian Date object or null when the Jalali view is not available.
     */
    function getDisplayedMonth(instance) {
        const view = instance ? instance.eaJalaliView : null;

        if (!view) {
            return null;
        }

        return App.Utils.Jalali.toGregorian(view.year, view.month, 1);
    }

    return {
        initialize,
        createPlugin,
        getDisplayedMonth,
    };
})();
