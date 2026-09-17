const {expect, test} = require('@playwright/test');

test('holds and releases Meta for WooCommerce signals with marketing consent', async ({page}) => {
    await page.goto('http://127.0.0.1:8765/harness.html');
    await page.setContent(`
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <title>CMP bootstrap fixture</title>
            <script>
                window.bootstrapFixture = {
                    signalCalls: []
                };
                window.fbwcsignal = {
                    hold: function () {
                        window.bootstrapFixture.signalCalls.push('hold');
                    },
                    release: function () {
                        window.bootstrapFixture.signalCalls.push('release');
                    }
                };
                window.bootstrapFixture.manager = {
                    confirmed: true,
                    consents: {
                        'facebook-for-woocommerce': false
                    },
                    config: {},
                    getService: function (serviceName) {
                        return {
                            name: serviceName,
                            purposes: ['marketing'],
                            required: false,
                            optOut: false
                        };
                    },
                    watch: function (watcher) {
                        this.watcher = watcher;
                    },
                    trigger: function (type) {
                        this.watcher.update(this, type);
                    }
                };
                window.klaro = {
                    getManager: function () {
                        return window.bootstrapFixture.manager;
                    }
                };
            </script>
        </head>
        <body>
            <script
                src="/assets/js/cmp-bootstrap.js"
                data-facebook-for-woocommerce-service="facebook-for-woocommerce"></script>
        </body>
        </html>
    `);

    await page.waitForFunction(() => window.bootstrapFixture.manager.watcher);
    await expect.poll(() => page.evaluate(() => window.bootstrapFixture.signalCalls))
        .toContain('hold');

    await page.evaluate(() => {
        window.bootstrapFixture.manager.consents['facebook-for-woocommerce'] = true;
        window.bootstrapFixture.manager.trigger('applyConsents');
    });

    await expect.poll(() => page.evaluate(() => window.bootstrapFixture.signalCalls))
        .toContain('release');

    await page.evaluate(() => {
        window.bootstrapFixture.manager.consents['facebook-for-woocommerce'] = false;
        window.bootstrapFixture.manager.trigger('applyConsents');
    });

    await expect.poll(() => page.evaluate(() => window.bootstrapFixture.signalCalls.slice(-1)[0]))
        .toBe('hold');
});
