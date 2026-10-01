import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { JSDOM } from 'jsdom';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const themeSource = fs.readFileSync(path.join(ROOT, 'assets/js/utils/theme.js'), 'utf8');
const carouselSource = fs.readFileSync(path.join(ROOT, 'assets/js/utils/luxury-carousel.js'), 'utf8');
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

function markDocumentReady(document) {
    Object.defineProperty(document, 'readyState', { configurable: true, value: 'complete' });
}

console.log('\nTheme and luxury carousel interactions');
const themeDom = new JSDOM('<!doctype html><html lang="fa" dir="rtl"><body><button data-ea-theme-toggle><i class="fa-moon"></i></button></body></html>', {
    runScripts: 'outside-only',
    url: 'https://example.test/booking',
});
const themeWindow = themeDom.window;
markDocumentReady(themeWindow.document);
themeWindow.lang = (key) => ({
    switch_to_light_mode: 'رفتن به حالت روشن',
    switch_to_dark_mode: 'رفتن به حالت تیره',
}[key] || key);
themeWindow.localStorage.setItem('ea-theme', 'dark');
themeWindow.eval(themeSource);
const toggle = themeWindow.document.querySelector('[data-ea-theme-toggle]');
assert('saved dark theme is restored on initialization', themeWindow.document.documentElement.dataset.bsTheme === 'dark');
assert('theme state updates the accessible Persian label and pressed state', toggle.getAttribute('aria-label') === 'رفتن به حالت روشن' && toggle.getAttribute('aria-pressed') === 'true');
assert('dark mode updates the theme icon', toggle.querySelector('i').classList.contains('fa-sun'));
toggle.click();
assert('theme toggle persists light mode and updates aria state', themeWindow.localStorage.getItem('ea-theme') === 'light' && toggle.getAttribute('aria-pressed') === 'false');
assert('light mode restores the moon icon and Persian action label', toggle.querySelector('i').classList.contains('fa-moon') && toggle.getAttribute('aria-label') === 'رفتن به حالت تیره');
themeDom.window.close();

const carouselDom = new JSDOM(`<!doctype html><html lang="fa" dir="rtl"><body>
    <section data-carousel data-carousel-item-label="رفتن به اسلاید" tabindex="0" role="region">
        <div data-carousel-track><article id="slide-one"></article><article id="slide-two"></article></div>
        <button data-carousel-prev aria-label="قبلی"></button>
        <div data-carousel-dots></div>
        <button data-carousel-next aria-label="بعدی"></button>
    </section>
</body></html>`, {
    runScripts: 'outside-only',
    pretendToBeVisual: true,
    url: 'https://example.test/booking',
});
const carouselWindow = carouselDom.window;
markDocumentReady(carouselWindow.document);
carouselWindow.matchMedia = () => ({ matches: true });
const carousel = carouselWindow.document.querySelector('[data-carousel]');
const track = carousel.querySelector('[data-carousel-track]');
const slides = Array.from(track.children);
let centeredSlide = 0;
const scrollOptions = [];
Object.defineProperty(track, 'clientWidth', { configurable: true, value: 300 });
track.getBoundingClientRect = () => ({ left: 0, right: 300, width: 300 });
slides.forEach((slide, index) => {
    slide.getBoundingClientRect = () => centeredSlide === index
        ? ({ left: 100, right: 200, width: 100 })
        : ({ left: 420 + index * 20, right: 520 + index * 20, width: 100 });
    slide.scrollIntoView = (options) => {
        centeredSlide = index;
        scrollOptions.push(options);
    };
});
carouselWindow.eval(carouselSource);
const dots = Array.from(carousel.querySelectorAll('.ea-carousel__dot'));
assert('carousel creates one accessible dot for each slide', dots.length === 2 && dots[0].getAttribute('aria-label') === 'رفتن به اسلاید 1 از 2');
assert('first slide is marked current on initialization', dots[0].getAttribute('aria-current') === 'true');
carousel.querySelector('[data-carousel-next]').click();
assert('next control scrolls to the next slide and updates current dot', centeredSlide === 1 && dots[1].getAttribute('aria-current') === 'true');
assert('carousel respects reduced-motion preference', scrollOptions.at(-1)?.behavior === 'auto');
carousel.dispatchEvent(new carouselWindow.KeyboardEvent('keydown', { key: 'ArrowLeft', bubbles: true }));
assert('RTL ArrowLeft advances to the next slide', centeredSlide === 0 && dots[0].getAttribute('aria-current') === 'true');
carousel.querySelector('[data-carousel-prev]').click();
assert('previous control wraps to the final slide', centeredSlide === 1 && dots[1].getAttribute('aria-current') === 'true');
carouselDom.window.close();

console.log(`\n${passed} passed, ${failed} failed`);
if (failed > 0) process.exitCode = 1;
