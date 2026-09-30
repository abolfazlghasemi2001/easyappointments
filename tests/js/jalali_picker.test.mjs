/**
 * Jalali date picker tests (jsdom based).
 *
 * These tests exercise the real project files (assets/js/utils/jalali_date.js, jalali_picker.js, ui.js) inside a
 * DOM so that the Persian calendar behaviour is verified without a browser. They are executed with:
 *
 *     npm run test:js
 *
 * The tests load the same jQuery/flatpickr copies that the application bundles from node_modules.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { JSDOM } from 'jsdom';

const REPO = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');

let passed = 0;
let failed = 0;

function assert(description, condition, extra = '') {
    if (condition) {
        passed++;
        console.log(`  ok   ${description}`);
    } else {
        failed++;
        console.log(`  FAIL ${description}${extra ? ' -> ' + extra : ''}`);
    }
}

/**
 * Create a DOM window with the application scripts loaded.
 *
 * @param {Object} overrides window.vars overrides.
 *
 * @return {{window: Object, $: Function, App: Object, vars: Object}}
 */
function createWindow(overrides = {}) {
    const dom = new JSDOM('<!doctype html><html><body><div id="holder"></div></body></html>', {
        runScripts: 'outside-only',
        pretendToBeVisual: true,
        url: 'http://localhost:8080/index.php/booking',
    });

    const { window } = dom;

    window.matchMedia =
        window.matchMedia ||
        (() => ({ matches: false, addListener() {}, removeListener() {}, addEventListener() {}, removeEventListener() {} }));

    const vars = {
        base_url: 'http://localhost:8080',
        index_page: 'index.php',
        language: 'persian',
        language_code: 'fa',
        calendar_type: 'jalali',
        persian_digits: '1',
        display_timezone: 'Asia/Tehran',
        text_direction: 'rtl',
        is_rtl: true,
        date_format: 'YMD',
        time_format: 'regular',
        first_weekday: 'saturday',
        future_booking_limit: '90',
        ...overrides,
    };

    window.vars = (key) => (key ? vars[key] || undefined : vars);
    window.lang = (key) => key;

    const load = (relative) => window.eval(fs.readFileSync(path.join(REPO, relative), 'utf8'));
    const loadModule = (modulePath) => window.eval(fs.readFileSync(path.join(REPO, 'node_modules', modulePath), 'utf8'));

    loadModule('jquery/dist/jquery.min.js');
    loadModule('flatpickr/dist/flatpickr.min.js');
    loadModule('moment/min/moment.min.js');

    load('assets/js/app.js');
    load('assets/js/utils/date.js');
    load('assets/js/utils/jalali_date.js');
    load('assets/js/utils/jalali_picker.js');
    load('assets/js/utils/ui.js');

    return { window, $: window.jQuery, App: window.App, vars };
}

/**
 * Build a picker through the same helper the application pages use.
 *
 * @param {Object} window DOM window.
 * @param {Object} [params] Extra picker parameters.
 *
 * @return {Object} The flatpickr instance.
 */
function createPicker(window, params = {}) {
    const $ = window.jQuery;
    const $input = $('<input type="text">').appendTo('#holder');

    window.App.Utils.UI.initializeDatePicker($input, { inline: true, ...params });

    return { instance: $input[0]._flatpickr, $input };
}

console.log('Jalali date picker');

// ---------------------------------------------------------------------------
console.log('\n* jalali installation');
{
    const { window, $ } = createWindow({ calendar_type: 'jalali' });
    const { instance } = createPicker(window, { defaultDate: new window.Date(2026, 8, 30) });

    const calendar = window.document.querySelector('.flatpickr-calendar');
    const wrapper = window.document.querySelector('.ea-jalali-wrapper');

    assert('the Jalali wrapper is attached to the calendar', Boolean(wrapper));
    assert(
        'the Gregorian grid is hidden',
        calendar?.querySelector('.flatpickr-innerContainer')?.style.display === 'none',
    );
    assert(
        'the Persian month and year are displayed',
        wrapper?.querySelector('.ea-jalali-title')?.textContent?.trim() === 'مهر ۱۴۰۵',
        wrapper?.querySelector('.ea-jalali-title')?.textContent,
    );
    assert('the Jalali grid holds 6 weeks', wrapper?.querySelectorAll('tbody tr').length === 6);
    assert(
        'the first column of the week is Saturday',
        wrapper?.querySelector('thead th')?.textContent?.trim() === 'ش',
        wrapper?.querySelector('thead th')?.textContent,
    );
    assert(
        'the first day of Mehr starts after 4 leading cells (1405/07/01 is a Wednesday)',
        wrapper?.querySelectorAll('tbody tr:first-child .ea-jalali-empty').length === 4,
        String(wrapper?.querySelectorAll('tbody tr:first-child .ea-jalali-empty').length),
    );
    assert(
        'the first day of the month is rendered in the correct cell',
        wrapper?.querySelector('tbody tr:first-child td:nth-child(5) .ea-jalali-day-button')?.textContent?.trim() === '۱',
    );
    assert(
        'the selected day is rendered as a Persian numeral',
        wrapper?.querySelector('.ea-jalali-selected .ea-jalali-day-button')?.textContent?.trim() === '۸',
        wrapper?.querySelector('.ea-jalali-selected .ea-jalali-day-button')?.textContent,
    );
    assert('the input holds the Jalali value', $inputValue(window) === '۱۴۰۵/۰۷/۰۸', $inputValue(window));

    // Selecting a day must keep the internal state Gregorian.
    wrapper.querySelectorAll('.ea-jalali-day-button')[20].click();

    const selected = instance.selectedDates[0];

    assert(
        'clicking a day selects the corresponding Gregorian date',
        selected instanceof window.Date && selected.getFullYear() === 2026,
        String(selected),
    );
    assert('the selected value stays Gregorian for the API', /^\d{4}-\d{2}-\d{2}$/.test(selected.toISOString().slice(0, 10)));
}

// ---------------------------------------------------------------------------
console.log('\n* Gregorian ISO values keep working (no 621 year shift)');
{
    const { window } = createWindow({ calendar_type: 'jalali' });
    const { instance, $input } = createPicker(window);

    instance.setDate('2026-09-30', false);

    assert('an ISO value is parsed as Gregorian', instance.selectedDates[0]?.getFullYear() === 2026, String(instance.selectedDates[0]));
    assert('the displayed value is Jalali', $input.val() === '۱۴۰۵/۰۷/۰۸', $input.val());

    instance.setDate('2026-09-30 14:30', false);

    assert('an ISO date-time value is parsed as Gregorian', instance.selectedDates[0]?.getFullYear() === 2026);
}

// ---------------------------------------------------------------------------
console.log('\n* Jalali values typed by the user');
{
    const { window, App } = createWindow({ calendar_type: 'jalali' });

    assert(
        'a Jalali value with Persian digits is parsed',
        App.Utils.Jalali.parse('۱۴۰۵/۰۷/۰۸')?.getFullYear() === 2026,
    );

    const { window: dmyWindow } = createWindow({ calendar_type: 'jalali', date_format: 'DMY' });
    const { instance, $input } = createPicker(dmyWindow, { defaultDate: new dmyWindow.Date(2026, 8, 30) });

    assert('the day/month/year order is used when configured', $input.val() === '۰۸/۰۷/۱۴۰۵', $input.val());

    instance.setDate('08/07/1405', false);

    assert('a day/month/year Jalali value is parsed', instance.selectedDates[0]?.getFullYear() === 2026, String(instance.selectedDates[0]));

    const { window: mdyWindow } = createWindow({ calendar_type: 'jalali', date_format: 'MDY' });
    const { $input: mdyInput } = createPicker(mdyWindow, { defaultDate: new mdyWindow.Date(2026, 8, 30) });

    assert('the month/day/year order is used when configured', mdyInput.val() === '۰۷/۰۸/۱۴۰۵', mdyInput.val());
}

// ---------------------------------------------------------------------------
console.log('\n* date-time pickers and ranges');
{
    const { window, $ } = createWindow({ calendar_type: 'jalali' });
    const $input = $('<input type="text">').appendTo('#holder');

    window.App.Utils.UI.initializeDateTimePicker($input, { defaultDate: new window.Date(2026, 8, 30, 14, 30) });

    assert('the date-time value is Jalali with the time part', $input.val() === '۱۴۰۵/۰۷/۰۸ ۲:۳۰ pm', $input.val());

    const { window: rangeWindow, $: rangeJQuery } = createWindow({ calendar_type: 'jalali' });
    const $rangeInput = rangeJQuery('<input type="text">').appendTo('#holder');

    rangeWindow.App.Utils.UI.initializeDatePicker($rangeInput, {
        inline: true,
        defaultDate: new rangeWindow.Date(2026, 8, 30),
        minDate: new rangeWindow.Date(2026, 8, 20),
        maxDate: new rangeWindow.Date(2026, 9, 10),
    });

    const disabled = rangeWindow.document.querySelectorAll('.ea-jalali-disabled');

    assert('days out of the min/max range are disabled', disabled.length > 0, `${disabled.length} disabled days`);
}

// ---------------------------------------------------------------------------
console.log('\n* gregorian installation (regression guard)');
{
    const { window, $ } = createWindow({ calendar_type: 'gregorian' });
    const { $input } = createPicker(window, { defaultDate: new window.Date(2026, 8, 30) });

    assert('no Jalali wrapper is rendered', window.document.querySelector('.ea-jalali-wrapper') === null);
    assert(
        'the Gregorian grid is visible',
        window.document.querySelector('.flatpickr-innerContainer')?.style.display !== 'none',
    );
    assert('the input keeps the Gregorian value', $input.val() === '2026/09/30', $input.val());
}

// ---------------------------------------------------------------------------
console.log(`\n${passed} passed, ${failed} failed`);

process.exit(failed === 0 ? 0 : 1);

/**
 * Read the value of the single input of the page.
 *
 * @param {Object} window DOM window.
 *
 * @return {String}
 */
function $inputValue(window) {
    return window.document.querySelector('#holder input')?.value;
}
