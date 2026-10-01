const {expect, test} = require('@playwright/test');

async function render(page, {savedConsent = false, lateInitializer = false} = {}) {
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    let releaseGoogle;
    const googleResponse = new Promise((resolve) => { releaseGoogle = resolve; });
    const requests = [];
    const initializer = `<script type="text/plain" data-type="text/plain"
        data-cmp-type="application/javascript" data-name="google-site-kit-sign-in"
        data-cmp-src="/site-kit-init.js" data-siwg-config='{"clientID":"test"}'></script>`;

    await page.route('https://accounts.google.com/gsi/client', async (route) => {
        requests.push('google');
        await googleResponse;
        await route.fulfill({contentType: 'application/javascript', body: 'window.google = {accounts: {id: {}}};'});
    });
    await page.route('**/site-kit-init.js', async (route) => {
        requests.push('site-kit');
        await route.fulfill({contentType: 'application/javascript', body: `
            window.siteKitConfig = JSON.parse(document.currentScript.dataset.siwgConfig);
            window.siteKitRuns = (window.siteKitRuns || 0) + 1;
        `});
    });
    await page.route('**/site-kit-lifecycle.html', (route) => route.fulfill({
        contentType: 'text/html',
        body: `<html><head><meta charset="utf-8"><script>window.klaroConfig = {
            version: 1, services: [{name: 'google-site-kit-sign-in', purposes: ['functional'],
                default: false, required: false, onlyOnce: true}]
        };</script><script src="/assets/js/cmp-bootstrap.js"
            data-google-site-kit-sign-in-service="google-site-kit-sign-in"></script>
        <script src="/assets/js/klaro.js" defer></script></head><body>
        <script type="text/plain" data-type="application/javascript"
            data-name="google-site-kit-sign-in" data-src="https://accounts.google.com/gsi/client"></script>
        ${lateInitializer ? '' : initializer}</body></html>`
    }));
    if (savedConsent) {
        await page.context().addCookies([{
            name: 'klaro', value: encodeURIComponent(JSON.stringify({'google-site-kit-sign-in': true})),
            url: 'http://127.0.0.1:8765'
        }]);
    }
    await page.goto('http://127.0.0.1:8765/site-kit-lifecycle.html', {waitUntil: 'domcontentloaded'});
    await expect.poll(async () => ({loaded: await page.evaluate(() => window.klaro !== undefined), errors})).toEqual({loaded: true, errors: []});
    return {requests, releaseGoogle, initializer};
}

async function consent(page, allowed) {
    await page.evaluate((value) => {
        const manager = window.klaro.getManager();
        manager.updateConsent('google-site-kit-sign-in', value);
        manager.saveAndApplyConsents();
    }, allowed);
}

test('withdrawing consent while Google loads holds initialization until a new grant', async ({page}) => {
    const fixture = await render(page);
    await consent(page, true);
    await expect.poll(() => fixture.requests).toEqual(['google']);
    await consent(page, false);
    fixture.releaseGoogle();
    await page.waitForFunction(() => !!window.google);
    expect(fixture.requests).toEqual(['google']);
    expect(await page.evaluate(() => window.siteKitRuns)).toBeUndefined();
    await consent(page, true);
    await expect.poll(() => page.evaluate(() => window.siteKitRuns)).toBe(1);
    expect(fixture.requests).toEqual(['google', 'site-kit']);
});

test('saved consent also handles an initializer arriving after Google loads', async ({page}) => {
    const fixture = await render(page, {savedConsent: true, lateInitializer: true});
    await expect.poll(() => fixture.requests).toEqual(['google']);
    fixture.releaseGoogle();
    await page.waitForFunction(() => !!window.google);
    await page.evaluate((html) => document.body.insertAdjacentHTML('beforeend', html), fixture.initializer);
    await expect.poll(() => page.evaluate(() => window.siteKitConfig)).toEqual({clientID: 'test'});
    expect(fixture.requests).toEqual(['google', 'site-kit']);
});

test('failed Google load leaves the Site Kit initializer inert', async ({page}) => {
    const fixture = await render(page);
    await page.route('https://accounts.google.com/gsi/client', (route) => route.abort());
    const failed = page.waitForEvent('requestfailed', (request) => request.url() === 'https://accounts.google.com/gsi/client');
    await consent(page, true);
    await failed;
    expect(fixture.requests).toEqual([]);
    expect(await page.evaluate(() => window.siteKitRuns)).toBeUndefined();
});
