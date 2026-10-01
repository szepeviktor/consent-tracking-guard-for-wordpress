const {expect, test} = require('@playwright/test');

test('Klaro blocks Site Kit until consent and restores config after Google loads', async ({page}) => {
    const requests = [];
    await page.route('https://accounts.google.com/gsi/client', async (route) => {
        requests.push('google');
        await new Promise((resolve) => setTimeout(resolve, 150));
        await route.fulfill({contentType: 'application/javascript', body: 'window.googleIdentityReady = true; window.google = {accounts: {id: {}}};'});
    });
    await page.route('**/sign-in-with-google-test.js', async (route) => {
        requests.push('site-kit');
        await route.fulfill({contentType: 'application/javascript', body: `
            window.siteKitResult = {
                ready: window.googleIdentityReady === true,
                config: JSON.parse(document.currentScript.dataset.siwgConfig)
            };
            window.siteKitRuns = (window.siteKitRuns || 0) + 1;
        `});
    });
    await page.goto('http://127.0.0.1:8765/harness.html');
    await page.setContent(`
        <script>window.klaroConfig = {
            version: 1,
            services: [{name: 'google-site-kit-sign-in', purposes: ['functional'],
                default: false, required: false, onlyOnce: true}]
        };</script>
        <script src="/assets/js/cmp-bootstrap.js"
            data-google-site-kit-sign-in-service="google-site-kit-sign-in"></script>
        <script type="text/plain" data-type="application/javascript"
            data-name="google-site-kit-sign-in" data-src="https://accounts.google.com/gsi/client"></script>
        <script type="text/plain" data-type="text/plain" data-cmp-type="application/javascript"
            data-name="google-site-kit-sign-in" data-cmp-src="/sign-in-with-google-test.js"
            data-siwg-config='{"clientID":"test-client"}'></script>
        <script src="/assets/js/klaro.js"></script>
    `);
    await page.waitForFunction(() => window.klaro !== undefined);
    expect(requests).toEqual([]);
    await page.evaluate(() => {
        const manager = window.klaro.getManager();
        manager.updateConsent('google-site-kit-sign-in', false);
        manager.saveAndApplyConsents();
    });
    expect(requests).toEqual([]);
    await page.evaluate(() => {
        const manager = window.klaro.getManager();
        manager.updateConsent('google-site-kit-sign-in', true);
        manager.saveAndApplyConsents();
    });
    await expect.poll(() => page.evaluate(() => window.siteKitResult)).toEqual({
        ready: true, config: {clientID: 'test-client'}
    });
    expect(requests).toEqual(['google', 'site-kit']);
    await page.evaluate(() => {
        const manager = window.klaro.getManager();
        manager.updateConsent('google-site-kit-sign-in', false);
        manager.saveAndApplyConsents();
        manager.updateConsent('google-site-kit-sign-in', true);
        manager.saveAndApplyConsents();
    });
    expect(await page.evaluate(() => window.siteKitRuns)).toBe(1);
});
