/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * ---------------------------------------------------------------------------- */

/**
 * Holidays HTTP client.
 *
 * This module implements the holiday related HTTP requests.
 */
App.Http.Holidays = (function () {
    /**
     * Save (create or update) a holiday.
     *
     * @param {Object} holiday
     *
     * @return {Object}
     */
    function save(holiday) {
        return holiday.id ? update(holiday) : store(holiday);
    }

    /**
     * Create a holiday.
     *
     * @param {Object} holiday
     *
     * @return {Object}
     */
    function store(holiday) {
        const url = App.Utils.Url.siteUrl('holidays/store');

        const data = {
            csrf_token: vars('csrf_token'),
            holiday: holiday,
        };

        return $.post(url, data);
    }

    /**
     * Update a holiday.
     *
     * @param {Object} holiday
     *
     * @return {Object}
     */
    function update(holiday) {
        const url = App.Utils.Url.siteUrl('holidays/update');

        const data = {
            csrf_token: vars('csrf_token'),
            holiday: holiday,
        };

        return $.post(url, data);
    }

    /**
     * Delete a holiday.
     *
     * @param {Number} holidayId
     *
     * @return {Object}
     */
    function destroy(holidayId) {
        const url = App.Utils.Url.siteUrl('holidays/destroy');

        const data = {
            csrf_token: vars('csrf_token'),
            holiday_id: holidayId,
        };

        return $.post(url, data);
    }

    /**
     * Search holidays by keyword.
     *
     * @param {String} keyword
     * @param {Number} [limit]
     * @param {Number} [offset]
     *
     * @return {Object}
     */
    function search(keyword, limit = null, offset = null) {
        const url = App.Utils.Url.siteUrl('holidays/search');

        const data = {
            csrf_token: vars('csrf_token'),
            keyword: keyword,
            length: limit,
            offset: offset,
        };

        return $.post(url, data);
    }

    /**
     * Find a holiday.
     *
     * @param {Number} holidayId
     *
     * @return {Object}
     */
    function find(holidayId) {
        const url = App.Utils.Url.siteUrl('holidays/find');

        const data = {
            csrf_token: vars('csrf_token'),
            holiday_id: holidayId,
        };

        return $.post(url, data);
    }

    /**
     * Import the official Iranian holidays of a Jalali year.
     *
     * @param {Number} jalaliYear
     *
     * @return {Object}
     */
    function importOfficial(jalaliYear) {
        const url = App.Utils.Url.siteUrl('holidays/import');

        const data = {
            csrf_token: vars('csrf_token'),
            jalali_year: jalaliYear,
        };

        return $.post(url, data);
    }

    /**
     * Get the holidays of a Gregorian date range.
     *
     * @param {String} startDate "Y-m-d"
     * @param {String} endDate "Y-m-d"
     *
     * @return {Object}
     */
    function feed(startDate, endDate) {
        const url = App.Utils.Url.siteUrl('holidays/feed');

        const data = {
            start_date: startDate,
            end_date: endDate,
        };

        return $.get(url, data);
    }

    return {
        save,
        store,
        update,
        destroy,
        search,
        find,
        importOfficial,
        feed,
    };
})();
