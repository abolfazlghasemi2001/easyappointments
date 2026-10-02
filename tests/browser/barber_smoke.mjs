/** Run against an installed LOCAL demo with a service/provider and free slots; never submits a booking or SMS. */
import {chromium} from 'playwright';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';

const base = (process.env.EA_TEST_URL || 'http://localhost:8080').replace(/\/$/, '');
const output = 'storage/review';
await fs.mkdir(output, {recursive: true});
const browser = await chromium.launch({
    executablePath: process.env.CHROMIUM_PATH || undefined,
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
});
const errors = [];
try {
    const context = await browser.newContext({
        viewport: {width: 1440, height: 1000},
        locale: process.env.EA_TEST_LOCALE || 'fa-IR',
        serviceWorkers: 'block',
    });
    const page = await context.newPage();
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('response', (response) => {
        if (response.status() >= 400) errors.push(`${response.status()} ${response.url()}`);
    });
    await page.goto(base, {waitUntil: 'networkidle'});
    await page.waitForTimeout(700);
    assert.equal(await page.locator('#ea-tour-root').count(), 0, 'Guidance must remain opt-in');
    assert.equal(await page.locator('#ea-page-loader').isVisible(), false);
    assert.equal(await page.evaluate(() => scrollY), 0, 'Automatic step selection must not scroll past the hero');
    await page.screenshot({path: `${output}/after-desktop.png`, fullPage: true});
    await page.locator('[data-booking-start]').first().click();
    if (await page.locator('#wizard-frame-1').isVisible()) {
        await page.locator('#button-next-1').click();
    }
    await page.locator('.available-hour').first().waitFor();
    await page.waitForTimeout(700);
    await page.screenshot({path: `${output}/calendar-desktop.png`});
    await page.locator('#button-next-2').click();
    await page.waitForFunction(() => document.activeElement?.matches('#wizard-frame-3 h2'));
    await page.locator('#button-next-3').click();
    assert((await page.locator('#wizard-frame-3 .is-invalid').count()) > 0);
    for (const [selector, value] of Object.entries({
        '#first-name': 'آزمایش',
        '#last-name': 'رزرو',
        '#email': 'review@example.test',
        '#phone-number': '09123456789',
    })) {
        if (await page.locator(selector).isVisible()) await page.fill(selector, value);
    }
    await page.locator('#button-next-3').click();
    await page.waitForFunction(() => document.activeElement?.matches('#wizard-frame-4 h2'));
    assert((await page.locator('#customer-details').innerText()).includes('review@example.test'));
    await page.screenshot({path: `${output}/confirmation-desktop.png`});
    // Stop at review: do not create appointments, send notifications or use any production account.
    await page.locator('[data-ea-theme-toggle]').click();
    await page.screenshot({path: `${output}/dark-desktop.png`});
    await page.setViewportSize({width: 390, height: 844});
    await page.goto(base, {waitUntil: 'networkidle'});
    assert.equal(await page.locator('html').getAttribute('data-bs-theme'), 'dark');
    await page.locator('[data-ea-theme-toggle]').click();
    await page.screenshot({path: `${output}/after-mobile.png`, fullPage: true});
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'No mobile overflow');
    await page.locator('[data-booking-start]').first().click();
    await page.waitForTimeout(800);
    await page.screenshot({path: `${output}/calendar-mobile.png`});
    for (const route of ['login', 'customer/portal']) {
        await page.goto(`${base}/index.php/${route}`, {waitUntil: 'networkidle'});
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
        await page.screenshot({path: `${output}/${route.replaceAll('/', '-')}-mobile.png`, fullPage: true});
    }
    await page.emulateMedia({reducedMotion: 'reduce'});
    await page.goto(base, {waitUntil: 'networkidle'});
    assert.equal(await page.evaluate(() => jQuery.fx.off), true);
    assert.equal(
        await page
            .locator('.ea-barber-pole')
            .first()
            .evaluate((el) => getComputedStyle(el).animationName),
        'none',
    );
    assert.deepEqual(errors, [], 'No JavaScript errors or broken assets');

    const loading = await context.newPage();
    await loading.route('**/assets/vendor/jquery/jquery.min.js*', async (route) => {
        await new Promise((resolve) => setTimeout(resolve, 2000));
        await route.continue();
    });
    await loading.goto(base, {waitUntil: 'commit'});
    await loading.locator('#ea-page-loader:not([hidden])').waitFor();
    await loading.screenshot({path: `${output}/loading-desktop.png`});
    await loading.waitForLoadState('networkidle');
    assert.equal(await loading.locator('#ea-page-loader').isVisible(), false);
    const noJs = await browser.newContext({javaScriptEnabled: false});
    const fallback = await noJs.newPage();
    await fallback.goto(base);
    assert.equal(
        await fallback.locator('#ea-page-loader').isVisible(),
        false,
        'Loader must never block without JavaScript',
    );
    await noJs.close();
    console.log(
        'PASS: Chromium desktop/mobile, booking review, validation, theme persistence, portal assets, reduced motion, loading and no-JS fallback.',
    );
} finally {
    await browser.close();
}
