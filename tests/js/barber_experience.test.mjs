import assert from 'node:assert/strict';
import fs from 'node:fs';
import {JSDOM} from 'jsdom';

const source = (path) => fs.readFileSync(new URL('../../' + path, import.meta.url), 'utf8');
const dom = new JSDOM(
    '<!doctype html><body><div id="available-hours"></div><select id="select-service"><option value="1">Cut</option></select><select id="select-provider"><option value="2">Barber</option></select></body>',
    {runScripts: 'outside-only', url: 'https://example.test'},
);
const w = dom.window;
w.console.error = () => {};
w.App = {Utils: {Url: {siteUrl: (url) => url}}, Http: {}};
w.eval(source('assets/js/utils/http.js'));
for (const method of ['request', 'upload', 'download']) {
    let reads = 0;
    w.fetch = async () => ({
        ok: false,
        status: 409,
        text: async () => {
            reads++;
            return 'Slot already booked';
        },
        json: () => {
            throw Error('Body read twice');
        },
        arrayBuffer: () => {
            throw Error('Body read twice');
        },
    });
    await assert.rejects(
        w.App.Utils.Http[method]('POST', '/test', new w.File(['data'], 'file.txt')),
        (e) => e.status === 409 && e.message === 'Slot already booked',
    );
    assert.equal(reads, 1);
}
w.fetch = async () => ({ok: true, json: async () => ({ok: true})});
assert.equal((await w.App.Utils.Http.request('POST', '/test', {})).ok, true);
console.log('✓ HTTP request, upload and download preserve error status and read the body once');

w.eval(source('node_modules/jquery/dist/jquery.js'));
w.vars = (key) => ({available_services: [{id: 1, duration: 30}], manage_mode: false})[key];
w.lang = () => 'No hours';
const requests = [];
w.$.post = () => {
    const deferred = w.$.Deferred();
    requests.push(deferred);
    return deferred.promise();
};
w.eval(source('assets/js/http/booking_http_client.js'));
w.App.Http.Booking.getAvailableHours('2026-10-01');
w.App.Http.Booking.getAvailableHours('2026-10-02');
requests[1].resolve([]);
w.$('#available-hours').text('Newest date result');
requests[0].resolve([]);
assert.equal(w.$('#available-hours').text(), 'Newest date result');
console.log('✓ Older availability response cannot overwrite the latest date');
dom.window.close();

const motion = new JSDOM('<!doctype html><div id="ea-page-loader" hidden></div>', {
    runScripts: 'outside-only',
    pretendToBeVisual: true,
});
motion.window.matchMedia = () => ({matches: true});
motion.window.eval(source('assets/js/utils/barber-motion.js'));
assert.equal(motion.window.document.querySelector('#ea-page-loader').hidden, false);
motion.window.document.dispatchEvent(new motion.window.Event('DOMContentLoaded'));
await new Promise((resolve) => setTimeout(resolve, 40));
assert.equal(motion.window.document.querySelector('#ea-page-loader').hidden, true);
motion.window.close();
console.log('✓ Loader dismisses without jQuery or image load; reduced-motion fallback is safe');
