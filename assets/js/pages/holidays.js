/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * ---------------------------------------------------------------------------- */

/**
 * Holidays page.
 *
 * This module implements the functionality of the holidays page (listing, searching, creating, updating, deleting and
 * importing the official Iranian holidays of a Jalali year).
 */
App.Pages.Holidays = (function () {
    const $holidays = $('#holidays');
    const $filterHolidays = $('#filter-holidays');
    const $id = $('#id');
    const $title = $('#title');
    const $holidayDate = $('#holiday-date');
    const $holidayDateHint = $('#holiday-date-hint');
    const $isRecurring = $('#is-recurring');
    const $description = $('#description');
    const moment = window.moment;

    let filterResults = [];
    let filterLimit = 20;

    /**
     * Add the page event listeners.
     */
    function addEventListeners() {
        /**
         * Event: Filter Holidays Form "Submit"
         *
         * @param {jQuery.Event} event
         */
        $holidays.on('submit', '#filter-holidays form', (event) => {
            event.preventDefault();

            const key = $('#filter-holidays .key').val();

            $('.selected').removeClass('selected');

            App.Pages.Holidays.resetForm();
            App.Pages.Holidays.filter(key);
        });

        /**
         * Event: Holiday Row "Click"
         *
         * Displays the selected holiday data on the right side of the page.
         *
         * @param {jQuery.Event} event
         */
        $holidays.on('click', '.holiday-row', (event) => {
            if ($('#filter-holidays .filter').prop('disabled')) {
                $('#filter-holidays .results').css('color', '#AAA');
                return; // exit because we are on edit mode
            }

            const holidayId = $(event.currentTarget).attr('data-id');

            const holiday = filterResults.find((filterResult) => Number(filterResult.id) === Number(holidayId));

            App.Pages.Holidays.display(holiday);
            $('#filter-holidays .selected').removeClass('selected');
            $(event.currentTarget).addClass('selected');
            $('#edit-holiday').prop('disabled', false);
        });

        /**
         * Event: Add Holiday Button "Click"
         */
        $holidays.on('click', '#add-holiday', () => {
            App.Pages.Holidays.resetForm();
            $('#holidays-page').addClass('editing');
            $holidays.find('.add-edit-delete-group').hide();
            $holidays.find('.save-cancel-group').show();
            $holidays.find('#delete-holiday').hide(); // Hide the delete button when adding
            $holidays.find('.record-details').find('input, textarea').prop('disabled', false);
            $holidays.find('.record-details .form-label span').prop('hidden', false);
            $filterHolidays.find('button').prop('disabled', true);
            $filterHolidays.find('.results').css('color', '#AAA');

            App.Utils.UI.setDateTimePickerValue($holidayDate, new Date());
        });

        /**
         * Event: Edit Holiday Button "Click"
         */
        $holidays.on('click', '#edit-holiday', () => {
            $('#holidays-page').addClass('editing');
            $holidays.find('.add-edit-delete-group').hide();
            $holidays.find('.save-cancel-group').show();
            $holidays.find('#delete-holiday').show();
            $holidays.find('.record-details').find('input, textarea').prop('disabled', false);
            $holidays.find('.record-details .form-label span').prop('hidden', false);
            $filterHolidays.find('button').prop('disabled', true);
            $filterHolidays.find('.results').css('color', '#AAA');
        });

        /**
         * Event: Delete Holiday Button "Click"
         */
        $holidays.on('click', '#delete-holiday', () => {
            const holidayId = $id.val();

            const buttons = [
                {
                    text: lang('cancel'),
                    click: (event, messageModal) => {
                        messageModal.hide();
                    },
                },
                {
                    text: lang('delete'),
                    click: (event, messageModal) => {
                        App.Pages.Holidays.remove(holidayId);
                        messageModal.hide();
                    },
                },
            ];

            App.Utils.Message.show(lang('delete_holiday'), lang('delete_record_prompt'), buttons);
        });

        /**
         * Event: Save Holiday Button "Click"
         */
        $holidays.on('click', '#save-holiday', () => {
            const dateTimeObject = App.Utils.UI.getDateTimePickerValue($holidayDate);

            const holiday = {
                title: $title.val(),
                holiday_date: dateTimeObject ? moment(dateTimeObject).format('YYYY-MM-DD') : null,
                is_recurring: $isRecurring.prop('checked') ? 1 : 0,
                description: $description.val(),
            };

            if ($id.val() !== '') {
                holiday.id = $id.val();
            }

            if (!App.Pages.Holidays.validate()) {
                return;
            }

            App.Pages.Holidays.save(holiday);
        });

        /**
         * Event: Cancel Holiday Button "Click"
         */
        $holidays.on('click', '#cancel-holiday', () => {
            const id = $id.val();

            App.Pages.Holidays.resetForm();
            $('#holidays-page').removeClass('editing');

            if (id !== '') {
                App.Pages.Holidays.select(id, true);
            }
        });

        /**
         * Event: Import Holidays Button "Click"
         */
        $holidays.on('click', '#import-holidays', () => {
            const jalaliYear = App.Utils.Jalali.enabled()
                ? App.Utils.Jalali.toJalali(new Date()).year
                : new Date().getFullYear();

            const buttons = [
                {
                    text: lang('cancel'),
                    click: (event, messageModal) => {
                        messageModal.hide();
                    },
                },
                {
                    text: lang('import_official_holidays'),
                    click: (event, messageModal) => {
                        messageModal.hide();
                        App.Pages.Holidays.importOfficial(jalaliYear);
                    },
                },
            ];

            App.Utils.Message.show(
                lang('import_official_holidays'),
                lang('import_official_holidays_prompt').replace('{year}', App.Utils.Jalali.toPersianDigits(jalaliYear)),
                buttons,
            );
        });

        /**
         * Event: Holiday Date Input "Change"
         *
         * Displays the Gregorian equivalent of the selected Jalali date.
         */
        $holidays.on('change', '#holiday-date', () => {
            App.Pages.Holidays.updateDateHint();
        });
    }

    /**
     * Filter holiday records.
     *
     * @param {String} keyword This key string is used to filter the holiday records.
     * @param {Number} selectId Optional, if set then after the filter operation the record with the given ID will be
     * selected (but not displayed).
     * @param {Boolean} show Optional (false), if true then the selected record will be displayed on the form.
     */
    function filter(keyword, selectId = null, show = false) {
        App.Http.Holidays.search(keyword, filterLimit).then((response) => {
            filterResults = response;

            $('#filter-holidays .results').empty();

            response.forEach((holiday) => {
                $('#filter-holidays .results')
                    .append(App.Pages.Holidays.getFilterHtml(holiday))
                    .append($('<hr/>'));
            });

            if (response.length === 0) {
                $('#filter-holidays .results').append(
                    $('<em/>', {
                        'text': lang('no_records_found'),
                    }),
                );
            } else if (response.length === filterLimit) {
                $('<button/>', {
                    'type': 'button',
                    'class': 'btn btn-outline-secondary w-100 load-more text-center',
                    'text': lang('load_more'),
                    'click': () => {
                        filterLimit += 20;
                        App.Pages.Holidays.filter(keyword, selectId, show);
                    },
                }).appendTo('#filter-holidays .results');
            }

            if (selectId) {
                App.Pages.Holidays.select(selectId, show);
            }
        });
    }

    /**
     * Save a holiday record to the database (via AJAX post).
     *
     * @param {Object} holiday Contains the holiday data.
     */
    function save(holiday) {
        App.Http.Holidays.save(holiday).then((response) => {
            App.Layouts.Backend.displayNotification(lang('holiday_saved'));
            App.Pages.Holidays.resetForm();
            $('#holidays-page').removeClass('editing');
            $filterHolidays.find('.key').val('');
            App.Pages.Holidays.filter('', response.id, true);
        });
    }

    /**
     * Import the official Iranian holidays of a Jalali year.
     *
     * @param {Number} jalaliYear
     */
    function importOfficial(jalaliYear) {
        App.Http.Holidays.importOfficial(jalaliYear).then((response) => {
            App.Layouts.Backend.displayNotification(
                lang('official_holidays_imported').replace(
                    '{count}',
                    App.Utils.Jalali.toPersianDigits(response.imported),
                ),
            );

            App.Pages.Holidays.resetForm();
            App.Pages.Holidays.filter('');
        });
    }

    /**
     * Delete a holiday record.
     *
     * @param {Number} id Record ID to be deleted.
     */
    function remove(id) {
        App.Http.Holidays.destroy(id).then(() => {
            App.Layouts.Backend.displayNotification(lang('holiday_deleted'));
            App.Pages.Holidays.resetForm();
            $('#holidays-page').removeClass('editing');
            App.Pages.Holidays.filter($('#filter-holidays .key').val());
        });
    }

    /**
     * Display a holiday record on the form.
     *
     * @param {Object} holiday Contains the holiday data.
     */
    function display(holiday) {
        $id.val(holiday.id);
        $title.val(holiday.title);
        App.Utils.UI.setDateTimePickerValue($holidayDate, moment(holiday.holiday_date, 'YYYY-MM-DD').toDate());
        $isRecurring.prop('checked', Boolean(Number(holiday.is_recurring)));
        $description.val(holiday.description);

        App.Pages.Holidays.updateDateHint();
    }

    /**
     * Display the Gregorian equivalent of the selected holiday date.
     */
    function updateDateHint() {
        const dateTimeObject = App.Utils.UI.getDateTimePickerValue($holidayDate);

        if (!dateTimeObject) {
            $holidayDateHint.text('');

            return;
        }

        if (!App.Utils.Jalali.enabled()) {
            $holidayDateHint.text(lang('holiday_date_hint').replace('{date}', moment(dateTimeObject).format('YYYY-MM-DD')));

            return;
        }

        const gregorian = moment(dateTimeObject).format('YYYY-MM-DD');

        $holidayDateHint.text(lang('holiday_date_hint').replace('{date}', gregorian));
    }

    /**
     * Validate holiday data before save (insert or update).
     *
     * @return {Boolean} Returns the validation result.
     */
    function validate() {
        $holidays.find('.is-invalid').removeClass('is-invalid');
        $holidays.find('.form-message').removeClass('alert-danger').hide();

        try {
            let missingRequired = false;

            $holidays.find('.required').each((index, fieldEl) => {
                if (!$(fieldEl).val()) {
                    $(fieldEl).addClass('is-invalid');
                    missingRequired = true;
                }
            });

            if (missingRequired) {
                throw new Error(lang('fields_are_required'));
            }

            return true;
        } catch (error) {
            $holidays.find('.form-message').addClass('alert-danger').text(error.message).show();
            return false;
        }
    }

    /**
     * Bring the holiday form back to its initial state.
     */
    function resetForm() {
        $filterHolidays.find('.selected').removeClass('selected');
        $filterHolidays.find('button').prop('disabled', false);
        $filterHolidays.find('.results').css('color', '');

        $holidays.find('.add-edit-delete-group').show();
        $holidays.find('.save-cancel-group').hide();
        $holidays.find('.record-details').find('input, textarea').val('').prop('disabled', true);
        $holidays.find('.record-details .form-label span').prop('hidden', true);
        $('#edit-holiday, #delete-holiday').prop('disabled', true);

        $holidays.find('.record-details .is-invalid').removeClass('is-invalid');
        $holidays.find('.record-details .form-message').hide();

        $isRecurring.prop('checked', false);
        $holidayDateHint.text('');
    }

    /**
     * Get the filter results row HTML code.
     *
     * @param {Object} holiday Contains the holiday data.
     *
     * @return {String} Returns the record HTML code.
     */
    function getFilterHtml(holiday) {
        return $('<div/>', {
            'class': 'holiday-row entry',
            'data-id': holiday.id,
            'html': [
                $('<strong/>', {
                    'text': App.Utils.Date.format(holiday.holiday_date, vars('date_format'), vars('time_format'), false),
                }),
                $('<br/>'),
                $('<span/>', {
                    'text': holiday.title,
                }),
            ],
        });
    }

    /**
     * Select a specific record from the current filter results.
     *
     * If the holiday ID does not exist in the list then no record will be selected.
     *
     * @param {Number} id The record ID to be selected from the filter results.
     * @param {Boolean} show Optional (false), if true then the method will display the record on the form.
     */
    function select(id, show = false) {
        $filterHolidays.find('.selected').removeClass('selected');

        $filterHolidays.find('.holiday-row[data-id="' + id + '"]').addClass('selected');

        if (show) {
            const holiday = filterResults.find((filterResult) => Number(filterResult.id) === Number(id));

            App.Pages.Holidays.display(holiday);

            $('#edit-holiday').prop('disabled', false);
        }
    }

    /**
     * Initialize the module.
     */
    function initialize() {
        App.Pages.Holidays.resetForm();
        App.Pages.Holidays.filter('');
        App.Pages.Holidays.addEventListeners();
        App.Utils.UI.initializeDatePicker($holidayDate);
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {
        filter,
        save,
        remove,
        importOfficial,
        display,
        updateDateHint,
        validate,
        resetForm,
        getFilterHtml,
        select,
        addEventListeners,
        initialize,
    };
})();
