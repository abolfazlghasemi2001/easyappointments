import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { JSDOM } from 'jsdom';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const source = fs.readFileSync(path.join(ROOT, 'assets/js/utils/spotlight-tour.js'), 'utf8');
let passed = 0;
let failed = 0;

function assert(description, condition) {
    if (condition) {
        passed += 1;
        console.log(`  ok   ${description}`);
    } else {
        failed += 1;
        console.log(`  FAIL ${description}`);
    }
}

function setupDom() {
    const dom = new JSDOM('<!doctype html><html lang="fa" dir="rtl"><body><button id="launch">Launch</button></body></html>', {
        runScripts: 'outside-only',
        pretendToBeVisual: true,
        url: 'https://example.test/booking',
    });
    dom.window.lang = (key) => ({
        spotlight_later: 'بعداً',
        spotlight_skip: 'رد کردن راهنما',
        spotlight_back: 'قبلی',
        spotlight_next: 'بعدی',
        spotlight_finish: 'پایان',
        spotlight_close: 'بستن راهنما',
        spotlight_progress: 'مرحله',
        spotlight_of: 'از',
    }[key] || key);
    dom.window.eval(source);
    return dom;
}

console.log('\nSpotlight tour accessibility and persistence');
const dom = setupDom();
const { window } = dom;
const launch = window.document.getElementById('launch');
launch.focus();
const tour = new window.SpotlightTour({
    id: 'unit-tour',
    steps: [
        { element: '#missing-target', title: 'مرحلهٔ اول', description: 'متن راهنما' },
        { element: '#also-missing', title: 'مرحلهٔ دوم', description: 'مرحله بعد' },
    ],
});

assert('tour opens and renders an accessible dialog when the selector is unavailable', tour.start());
const dialog = window.document.querySelector('[role="dialog"][aria-modal="true"]');
const portal = window.document.getElementById('ea-tour-root');
assert('tour portal is mounted directly in the body at the maximum stacking level', portal?.parentElement === window.document.body && portal.style.zIndex === '2147483647');
assert('tooltip controls live inside the top-level portal', dialog?.parentElement === portal && dialog.querySelector('[data-tour-skip]')?.textContent === 'رد کردن راهنما');
assert('dialog announces the first title and explanation', dialog?.getAttribute('aria-labelledby') === 'ea-tour-title' && dialog?.getAttribute('aria-describedby') === 'ea-tour-description');
assert('step progress is visible and accurate with Persian digits', window.document.querySelector('[data-tour-progress-text]')?.textContent === 'مرحله ۱ از ۲');

window.document.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'ArrowLeft', bubbles: true }));
assert('RTL ArrowLeft advances the guide', window.document.getElementById('ea-tour-title')?.textContent === 'مرحلهٔ دوم');
assert('progress updates after moving forward', window.document.querySelector('[data-tour-progress-text]')?.textContent === 'مرحله ۲ از ۲');
const backButton = window.document.querySelector('.ea-tour-tooltip__back');
assert('Previous becomes available on later steps', backButton?.hidden === false && backButton.textContent === 'قبلی');
backButton?.click();
assert('Previous button returns to the prior step', window.document.getElementById('ea-tour-title')?.textContent === 'مرحلهٔ اول');
tour.next();

window.document.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
assert('Escape pauses and closes without marking the tour complete', !window.document.getElementById('ea-tour-root') && !JSON.parse(window.localStorage.getItem('tour-completed') || '{}')['unit-tour']);
assert('the current step is persisted for later resume', JSON.parse(window.localStorage.getItem('tour-step') || '{}')['unit-tour'] === 1);

assert('the guide resumes at the saved step', tour.start({ force: true, resume: true }) && window.document.getElementById('ea-tour-title')?.textContent === 'مرحلهٔ دوم');
window.document.querySelector('[data-tour-progress-value]')?.closest('[role="progressbar"]');
window.document.querySelector('.ea-tour-tooltip__next')?.click();
assert('finishing stores completion and clears the saved step', JSON.parse(window.localStorage.getItem('tour-completed') || '{}')['unit-tour'] === true && !JSON.parse(window.localStorage.getItem('tour-step') || '{}')['unit-tour']);
assert('focus returns to the element that opened the tour', window.document.activeElement === launch);
assert('completed automatic tours do not start a second time', tour.start() === false);

dom.window.close();

const skipDom = setupDom();
const skipTour = new skipDom.window.SpotlightTour({ id: 'skip-tour', steps: [{ title: 'Skip', description: 'This can be skipped.' }] });
skipTour.start();
skipDom.window.document.querySelector('[data-tour-skip]')?.click();
assert('Skip persists the skipped state separately from completion', JSON.parse(skipDom.window.localStorage.getItem('tour-skipped') || '{}')['skip-tour'] === true && !JSON.parse(skipDom.window.localStorage.getItem('tour-completed') || '{}')['skip-tour']);
assert('skipped automatic tours remain quiet unless manually forced', skipTour.start() === false && skipTour.start({ force: true }) === true);
skipDom.window.close();

console.log(`\n${passed} passed, ${failed} failed`);
if (failed > 0) process.exitCode = 1;
