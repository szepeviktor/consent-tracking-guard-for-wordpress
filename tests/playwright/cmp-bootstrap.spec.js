const {expect, test} = require('@playwright/test');

async function renderBootstrapWithGtmId(page, gtmId) {
    await page.route('https://www.googletagmanager.com/**', (route) => {
        route.fulfill({
            status: 204,
            body: ''
        });
    });

    await page.goto('http://127.0.0.1:8765/harness.html');
    await page.setContent(`
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <title>CMP bootstrap fixture</title>
            <script>
                window.bootstrapFixture = {
                    manager: {
                        confirmed: true,
                        consents: {
                            'google-tag-manager': true
                        },
                        config: {},
                        getService: function (serviceName) {
                            return {
                                name: serviceName,
                                purposes: ['analytics'],
                                required: false,
                                optOut: false
                            };
                        },
                        watch: function (watcher) {
                            this.watcher = watcher;
                        }
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
                data-gtm-id="${gtmId}"></script>
        </body>
        </html>
    `);
}

async function injectedGoogleScriptSrc(page) {
    return page.waitForFunction(() => {
        const scripts = Array.from(document.scripts);
        const googleScript = scripts.find((script) => (
            script.src.indexOf('https://www.googletagmanager.com/') === 0
        ));

        return googleScript ? googleScript.src : false;
    });
}

test('loads Google Tag Manager containers with gtm.js', async ({page}) => {
    await renderBootstrapWithGtmId(page, 'GTM-MGK8MGT');

    const scriptSrc = await injectedGoogleScriptSrc(page);

    expect(await scriptSrc.jsonValue()).toBe('https://www.googletagmanager.com/gtm.js?id=GTM-MGK8MGT');
});

test('loads Google Analytics measurement IDs with gtag.js', async ({page}) => {
    await renderBootstrapWithGtmId(page, 'G-XXXXXXXXXX');

    const scriptSrc = await injectedGoogleScriptSrc(page);

    expect(await scriptSrc.jsonValue()).toBe('https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX');
    await expect.poll(() => page.evaluate(() => window.dataLayer.map((entry) => (
        Array.from(entry)
    )))).toContainEqual(['config', 'G-XXXXXXXXXX']);
});

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

test('does not hold Meta for WooCommerce signals when consent already exists', async ({page}) => {
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
                        'facebook-for-woocommerce': true
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
        .toEqual(['release']);
});

test('removes Klaviyo browser storage when consent is denied', async ({page}) => {
    await page.goto('http://127.0.0.1:8765/harness.html');
    await page.setContent(`
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <title>CMP bootstrap fixture</title>
            <script>
                [
                    '__kl_key',
                    '$referrer',
                    '$last_referrer',
                    'klaviyoOnsite',
                    'kl-post-identification-sync',
                    'lastExternalReferrer',
                    'lastExternalReferrerTime',
                    '__kla_viewed',
                    '__kla_viewed_reviewed_items'
                ].forEach(function (key) {
                    window.localStorage.setItem(key, 'klaviyo-test-value');
                });
                window.sessionStorage.setItem('_kx', 'klaviyo-test-value');
                window.sessionStorage.setItem('klaviyoPagesVisitCountV2', '["https://example.test/"]');
                document.cookie = '__kla_id=abc123; path=/';

                window.bootstrapFixture = {
                    tracker: {
                        account_id: 'PUBLIC_KEY',
                        is_tracking_on: true,
                        clearedIdentity: false,
                        clearIdentity: function () {
                            this.clearedIdentity = true;
                        }
                    },
                    manager: {
                        confirmed: true,
                        consents: {
                            klaviyo: false
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
                        }
                    }
                };
                window._learnq = {
                    push: function (callback) {
                        callback(window.bootstrapFixture.tracker);
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
            <script src="/assets/js/cmp-bootstrap.js" data-klaviyo="true"></script>
        </body>
        </html>
    `);

    await page.waitForFunction(() => window.bootstrapFixture.manager.watcher);

    await expect.poll(() => page.evaluate(() => ({
        cookies: document.cookie,
        localStorage: {
            klaviyoOnsite: window.localStorage.getItem('klaviyoOnsite'),
            postIdentificationSync: window.localStorage.getItem('kl-post-identification-sync'),
            lastExternalReferrer: window.localStorage.getItem('lastExternalReferrer'),
            lastExternalReferrerTime: window.localStorage.getItem('lastExternalReferrerTime')
        },
        sessionStorage: {
            klaviyoPagesVisitCountV2: window.sessionStorage.getItem('klaviyoPagesVisitCountV2')
        },
        tracker: {
            accountId: window.bootstrapFixture.tracker.account_id,
            isTrackingOn: window.bootstrapFixture.tracker.is_tracking_on,
            clearedIdentity: window.bootstrapFixture.tracker.clearedIdentity
        }
    }))).toEqual({
        cookies: '__kla_off=true',
        localStorage: {
            klaviyoOnsite: null,
            postIdentificationSync: null,
            lastExternalReferrer: null,
            lastExternalReferrerTime: null
        },
        sessionStorage: {
            klaviyoPagesVisitCountV2: null
        },
        tracker: {
            accountId: null,
            isTrackingOn: false,
            clearedIdentity: true
        }
    });
});

test('syncs Triple Whale plugin tracking consent', async ({page}) => {
    await page.goto('http://127.0.0.1:8765/harness.html');
    await page.setContent(`
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <title>CMP bootstrap fixture</title>
            <script>
                window.bootstrapFixture = {
                    manager: {
                        confirmed: true,
                        consents: {
                            'triple-whale-pixel': false
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
                data-triple-whale-service="triple-whale-pixel"></script>
        </body>
        </html>
    `);

    await page.waitForFunction(() => window.bootstrapFixture.manager.watcher);
    await page.waitForTimeout(300);
    expect(await page.evaluate(() => window.TriplePixelData.trackingConsent)).toBe(false);

    await page.evaluate(() => {
        window.TriplePixel = function () {
            window.TriplePixel._q.push(arguments);
        };
        window.TriplePixel._q = [];
    });

    await expect.poll(() => page.evaluate(() => (
        window.TriplePixel._q[0] ? Array.from(window.TriplePixel._q[0]) : null
    )))
        .toEqual(['trackingConsent', false]);

    await page.evaluate(() => {
        window.bootstrapFixture.manager.consents['triple-whale-pixel'] = true;
        window.bootstrapFixture.manager.trigger('applyConsents');
    });

    expect(await page.evaluate(() => window.TriplePixelData.trackingConsent)).toBe(true);
    await expect.poll(() => page.evaluate(() => (
        window.TriplePixel._q.map(function (entry) {
            return Array.from(entry);
        })
    ))).toEqual([
        ['trackingConsent', false],
        ['trackingConsent', true]
    ]);

    await page.evaluate(() => {
        ['TriplePixel', 'TriplePixelU', 'di_pmt_wt', 'configSecurityConfModel', 'no_track_triple'].forEach((key) => {
            document.cookie = `${encodeURIComponent(key)}=value; path=/; SameSite=Lax`;
            localStorage.setItem(key, 'value');
            sessionStorage.setItem(key, 'value');
        });

        window.bootstrapFixture.manager.consents['triple-whale-pixel'] = false;
        window.bootstrapFixture.manager.trigger('applyConsents');
    });

    expect(await page.evaluate(() => window.TriplePixelData.trackingConsent)).toBe(false);
    await expect.poll(() => page.evaluate(() => (
        window.TriplePixel._q.map(function (entry) {
            return Array.from(entry);
        })
    ))).toEqual([
        ['trackingConsent', false],
        ['trackingConsent', true],
        ['trackingConsent', false]
    ]);

    expect(await page.evaluate(() => (
        ['TriplePixel', 'TriplePixelU', 'di_pmt_wt', 'configSecurityConfModel', 'no_track_triple'].filter((key) => (
            document.cookie.indexOf(encodeURIComponent(key) + '=') !== -1
                || localStorage.getItem(key) !== null
                || sessionStorage.getItem(key) !== null
        ))
    ))).toEqual([]);
});
