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
    await expect.poll(() => page.evaluate(() => document.cookie))
        .toContain('wc_facebook_signals_state=held');

    await page.evaluate(() => {
        window.bootstrapFixture.manager.consents['facebook-for-woocommerce'] = true;
        window.bootstrapFixture.manager.trigger('applyConsents');
    });

    await expect.poll(() => page.evaluate(() => window.bootstrapFixture.signalCalls))
        .toContain('release');
    await expect.poll(() => page.evaluate(() => document.cookie))
        .toContain('wc_facebook_signals_state=active');

    await page.evaluate(() => {
        window.bootstrapFixture.manager.consents['facebook-for-woocommerce'] = false;
        window.bootstrapFixture.manager.trigger('applyConsents');
    });

    await expect.poll(() => page.evaluate(() => window.bootstrapFixture.signalCalls.slice(-1)[0]))
        .toBe('hold');
    await expect.poll(() => page.evaluate(() => document.cookie))
        .toContain('wc_facebook_signals_state=held');
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
    await expect.poll(() => page.evaluate(() => document.cookie))
        .toContain('wc_facebook_signals_state=active');
});

test('refreshes PixelYourSite through its AJAX-aware runtime after consent changes', async ({page}) => {
    await page.goto('http://127.0.0.1:8765/harness.html');
    await page.setContent(`
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <title>CMP bootstrap fixture</title>
            <script>
                window.bootstrapFixture = {
                    calls: []
                };
                window.pysOptions = {
                    gdpr: {
                        all_disabled_by_api: true,
                        facebook_disabled_by_api: true,
                        analytics_disabled_by_api: true,
                        google_ads_disabled_by_api: true,
                        pinterest_disabled_by_api: true,
                        bing_disabled_by_api: true,
                        reddit_disabled_by_api: true,
                        externalID_disabled_by_api: true,
                        analytics_storage: {value: 'denied'},
                        ad_storage: {value: 'denied'},
                        ad_user_data: {value: 'denied'},
                        ad_personalization: {value: 'denied'}
                    },
                    cookie: {
                        disabled_all_cookie: true,
                        disabled_start_session_cookie: true,
                        disabled_advanced_form_data_cookie: true,
                        disabled_landing_page_cookie: true,
                        disabled_first_visit_cookie: true,
                        disabled_trafficsource_cookie: true,
                        disabled_utmTerms_cookie: true,
                        disabled_utmId_cookie: true,
                        externalID_disabled_by_api: true
                    }
                };
                window.pys = {
                    Utils: {
                        manageCookies: function () {
                            window.bootstrapFixture.calls.push('manageCookies');
                        },
                        loadPixels: function () {
                            window.bootstrapFixture.calls.push('loadPixels');
                        },
                        pushConsent: function () {
                            window.bootstrapFixture.calls.push('pushConsent');
                        }
                    },
                    Facebook: {
                        disable: function () {
                            window.bootstrapFixture.calls.push('Facebook.disable');
                        }
                    },
                    Analytics: {
                        disable: function () {
                            window.bootstrapFixture.calls.push('Analytics.disable');
                        }
                    },
                    GTM: {
                        disable: function () {
                            window.bootstrapFixture.calls.push('GTM.disable');
                        }
                    }
                };
                window.bootstrapFixture.manager = {
                    confirmed: true,
                    consents: {
                        'pixelyoursite-statistics': false,
                        'pixelyoursite-marketing': false
                    },
                    config: {},
                    getService: function (serviceName) {
                        return {
                            name: serviceName,
                            purposes: [
                                serviceName === 'pixelyoursite-statistics' ? 'statistics' : 'marketing'
                            ],
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
                data-pixelyoursite-statistics-service="pixelyoursite-statistics"
                data-pixelyoursite-marketing-service="pixelyoursite-marketing"></script>
        </body>
        </html>
    `);

    await page.waitForFunction(() => window.bootstrapFixture.manager.watcher);
    await expect.poll(() => page.evaluate(() => window.bootstrapFixture.calls))
        .toContain('Facebook.disable');

    await page.evaluate(() => {
        window.bootstrapFixture.calls = [];
        window.bootstrapFixture.manager.consents['pixelyoursite-statistics'] = true;
        window.bootstrapFixture.manager.trigger('applyConsents');
    });

    await expect.poll(() => page.evaluate(() => window.bootstrapFixture.calls))
        .toEqual(['manageCookies', 'loadPixels']);

    await page.evaluate(() => {
        window.bootstrapFixture.calls = [];
        window.bootstrapFixture.manager.consents['pixelyoursite-statistics'] = false;
        Object.keys(window.pysOptions.gdpr).forEach((key) => {
            if (typeof window.pysOptions.gdpr[key] === 'boolean') {
                window.pysOptions.gdpr[key] = false;
            }
        });
        Object.keys(window.pysOptions.cookie).forEach((key) => {
            window.pysOptions.cookie[key] = false;
        });
        window.pysOptions.gdpr.analytics_storage.value = 'granted';
        window.pysOptions.gdpr.ad_storage.value = 'granted';
        window.bootstrapFixture.manager.trigger('applyConsents');
    });

    await expect.poll(() => page.evaluate(() => window.bootstrapFixture.calls))
        .toEqual(['pushConsent', 'Facebook.disable', 'Analytics.disable', 'GTM.disable', 'manageCookies']);
    await expect.poll(() => page.evaluate(() => ({
        allDisabled: window.pysOptions.gdpr.all_disabled_by_api,
        analyticsDisabled: window.pysOptions.gdpr.analytics_disabled_by_api,
        adStorage: window.pysOptions.gdpr.ad_storage.value,
        allCookiesDisabled: window.pysOptions.cookie.disabled_all_cookie,
        landingPageCookieDisabled: window.pysOptions.cookie.disabled_landing_page_cookie
    }))).toEqual({
        allDisabled: true,
        analyticsDisabled: true,
        adStorage: 'denied',
        allCookiesDisabled: true,
        landingPageCookieDisabled: true
    });
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

test('gates the real Triple Whale loader and changes consent without navigation', async ({page}) => {
    const fs = require('node:fs');
    const path = require('node:path');
    const snippet = fs.readFileSync(path.join(__dirname, 'triple-whale-snippet.fixture.js'), 'utf8');
    const traffic = [];
    let loads = 0;
    const html = `<!doctype html><html><head><script>
        window.bootstrapFixture = {manager: {
            confirmed: true,
            consents: {'triple-whale-pixel': localStorage.getItem('twConsent') === 'true'},
            config: {},
            getService: function () { return {required: false, optOut: false}; },
            watch: function (watcher) { this.watcher = watcher; },
            choose: function (consent) {
                this.consents['triple-whale-pixel'] = consent;
                localStorage.setItem('twConsent', String(consent));
                document.cookie = 'klaro=' + encodeURIComponent(JSON.stringify(this.consents)) + '; path=/';
                this.watcher.update(this, 'applyConsents');
            }
        }};
        setTimeout(function () { window.klaro = {getManager: function () {return window.bootstrapFixture.manager;}}; }, 50);
        </script><script src="/assets/js/cmp-bootstrap.js" data-triple-whale-service="triple-whale-pixel"></script>
        </head><body>
        <script>window.TriplePixelData = {TripleName: 'example.test', plat: 'woocommerce', ver: '2.17'};</script>
        <script type="text/plain" data-ctg-triple-whale-src="/tw-snippet.js"></script>
        <script>window.ctgTripleWhaleEvent && window.ctgTripleWhaleEvent('purchase', {order_id: 'denied'});</script>
        </body></html>`;
    await page.route('**/tw-consent-fixture', route => route.fulfill({contentType: 'text/html', body: html}));
    await page.route('**/tw-snippet.js', async route => {
        loads += 1;
        await route.fulfill({contentType: 'application/javascript', body: snippet});
    });
    await page.route('https://*.config-security.com/**', async route => {
        traffic.push(route.request().url());
        await route.fulfill({status: 200, contentType: 'text/plain', body: ''});
    });
    await page.goto('http://127.0.0.1:8765/tw-consent-fixture');
    await page.waitForFunction(() => window.bootstrapFixture.manager.watcher);
    await page.waitForTimeout(350);
    expect(loads).toBe(0);
    expect(traffic).toEqual([]);
    expect(await page.evaluate(() => localStorage.getItem('TriplePixelU'))).toBeNull();
    await page.evaluate(() => window.bootstrapFixture.manager.choose(true));
    await expect.poll(() => traffic.length).toBe(3);
    expect(loads).toBe(1);
    expect(await page.evaluate(() => window.TriplePixelData.trackingConsent)).toBe(true);
    expect(await page.evaluate(() => window.TriplePixel._q.some(entry => entry[1] === 'purchase'))).toBe(false);
    await page.evaluate(() => {
        window.ctgTripleWhaleEvent('addtocart', {item: 123});
        window.bootstrapFixture.manager.choose(true);
    });
    expect(loads).toBe(1);
    expect(await page.evaluate(() => window.TriplePixel._q.some(entry => entry[1] === 'addtocart'))).toBe(true);
    const navigations = [];
    page.on('framenavigated', frame => {
        if (frame === page.mainFrame()) navigations.push(frame.url());
    });
    await page.evaluate(() => {
        window.documentMarker = 'same-document';
        sessionStorage.setItem('dielahws', 'tw-session-id');
        sessionStorage.setItem('unrelated-session', 'keep');
        window.bootstrapFixture.manager.choose(false);
        window.ctgTripleWhaleEvent('purchase', {order_id: 'withdrawn'});
    });
    await page.waitForTimeout(350);
    expect(loads).toBe(1);
    expect(traffic.length).toBe(3);
    expect(await page.evaluate(() => localStorage.getItem('TriplePixelU'))).toBeNull();
    expect(await page.evaluate(() => sessionStorage.getItem('dielahws'))).toBeNull();
    expect(await page.evaluate(() => sessionStorage.getItem('unrelated-session'))).toBe('keep');
    expect(await page.evaluate(() => window.TriplePixel._q.map(entry => entry.slice(1)))).toEqual([
        ['trackingConsent', false]
    ]);
    await page.evaluate(() => {
        window.bootstrapFixture.manager.choose(true);
        window.ctgTripleWhaleEvent('addtocart', {item: 456});
    });
    expect(loads).toBe(1);
    expect(traffic.length).toBe(3);
    expect(navigations).toEqual([]);
    expect(await page.evaluate(() => window.documentMarker)).toBe('same-document');
    expect(await page.evaluate(() => window.TriplePixelData.trackingConsent)).toBe(true);
    expect(await page.evaluate(() => window.TriplePixel._q.map(entry => entry.slice(1)))).toEqual([
        ['trackingConsent', false], ['trackingConsent', true], ['addtocart', {item: 456}]
    ]);
});


test('serves identical Triple Whale HTML across cached consent states', async ({browser}) => {
    const fs = require('node:fs');
    const path = require('node:path');
    const snippet = fs.readFileSync(path.join(__dirname, 'triple-whale-snippet.fixture.js'), 'utf8');
    const cachedHtml = `<!doctype html><html><head><meta charset="utf-8">
        <script>window.klaroConfig = {
            storageMethod: 'cookie', storageName: 'klaro', default: false,
            services: [
                {name: 'triple-whale-pixel', purposes: ['marketing']},
                {name: 'test-statistics', purposes: ['statistics']}
            ]
        };</script>
        <script src="/assets/js/cmp-bootstrap.js" data-triple-whale-service="triple-whale-pixel"></script>
        <script src="/assets/js/klaro.js" defer></script>
        <script>window.ctgTripleWhaleEvent && window.ctgTripleWhaleEvent('addtocart', {item: 123});</script>
        </head><body>
        <script>window.TriplePixelData = {TripleName: 'example.test', plat: 'woocommerce', ver: '2.17'};</script>
        <script type="text/plain" data-ctg-triple-whale-src="/cached-tw-snippet.js"></script>
        <script>window.ctgTripleWhaleEvent && window.ctgTripleWhaleEvent('purchase', {order_id: 456});</script>
        </body></html>`;
    for (const savedConsent of [null, false, true]) {
        const context = await browser.newContext();
        try {
            if (savedConsent !== null) {
                await context.addCookies([{
                    name: 'klaro',
                    value: encodeURIComponent(JSON.stringify({
                        'triple-whale-pixel': savedConsent,
                        'test-statistics': true
                    })),
                    url: 'http://127.0.0.1:8765'
                }]);
            }
            let loads = 0;
            const requests = [];
            await context.route('**/cached-tw-page', route => route.fulfill({contentType: 'text/html', body: cachedHtml}));
            await context.route('**/cached-tw-snippet.js', route => {
                loads += 1;
                return route.fulfill({contentType: 'application/javascript', body: snippet});
            });
            await context.route('https://*.config-security.com/**', route => {
                requests.push(route.request().url());
                return route.fulfill({status: 200, body: ''});
            });
            const page = await context.newPage();
            await page.goto('http://127.0.0.1:8765/cached-tw-page');
            await page.waitForTimeout(600);
            if (savedConsent === true) {
                await expect.poll(() => requests.length).toBe(3);
                expect(loads).toBe(1);
                await expect.poll(() => page.evaluate(() => window.TriplePixel._q.map(entry => entry[1]))).toEqual(
                    expect.arrayContaining(['addtocart', 'purchase'])
                );
            } else {
                expect(loads).toBe(0);
                expect(requests).toEqual([]);
                expect(await page.evaluate(() => localStorage.getItem('TriplePixelU'))).toBeNull();
                await page.evaluate(() => {
                    const manager = window.klaro.getManager();
                    manager.updateConsent('triple-whale-pixel', true);
                    manager.saveAndApplyConsents();
                });
                await expect.poll(() => requests.length).toBe(3);
                expect(await page.evaluate(() => window.TriplePixel._q.some(entry => (
                    entry[1] === 'addtocart' || entry[1] === 'purchase'
                )))).toBe(false);
            }
        } finally {
            await context.close();
        }
    }
});


test('applies withdrawal when the Triple Whale loader finishes late', async ({page}) => {
    const fs = require('node:fs');
    const path = require('node:path');
    let releaseLoader;
    const blocked = new Promise(resolve => { releaseLoader = resolve; });
    let requested = false;
    await page.route('**/late-tw.js', async route => {
        requested = true;
        await blocked;
        await route.fulfill({contentType: 'application/javascript', body: fs.readFileSync(
            path.join(__dirname, 'triple-whale-snippet.fixture.js'), 'utf8'
        )});
    });
    await page.route('https://*.config-security.com/**', route => route.fulfill({status: 200, body: ''}));
    await page.goto('http://127.0.0.1:8765/harness.html');
    await page.setContent(`<!doctype html><html><head><meta charset="utf-8"><script>
        window.manager = {
            confirmed: true, consents: {'triple-whale-pixel': false}, config: {},
            getService: function () {return {required:false, optOut:false};},
            watch: function (watcher) {this.watcher = watcher;},
            choose: function (consent) {this.consents['triple-whale-pixel']=consent;this.watcher.update(this,'applyConsents');}
        };
        window.klaro = {getManager:function(){return window.manager;}};
        </script><script src="/assets/js/cmp-bootstrap.js" data-triple-whale-service="triple-whale-pixel"></script>
        </head><body><script>window.TriplePixelData={TripleName:'example.test',plat:'woocommerce'};</script>
        <script type="text/plain" data-ctg-triple-whale-src="/late-tw.js"></script></body></html>`);
    await page.waitForFunction(() => window.manager.watcher);
    await page.evaluate(() => window.manager.choose(true));
    await expect.poll(() => requested).toBe(true);
    await page.evaluate(() => {
        window.manager.choose(false);
        window.ctgTripleWhaleEvent('purchase', {order_id: 'denied'});
    });
    releaseLoader();
    await expect.poll(() => page.evaluate(() => window.TriplePixel && window.TriplePixel._q.map(entry => entry.slice(1)))).toEqual([
        ['trackingConsent', false]
    ]);
    expect(await page.evaluate(() => window.TriplePixelData.trackingConsent)).toBe(false);
    expect(await page.evaluate(() => localStorage.getItem('di_pmt_wt'))).toBeNull();
});
