const {test, expect} = require('@playwright/test');

async function render(page, tagId = 'GTM-TEST123', lang = 'hu-HU') {
    await page.route('https://**/*', route => route.fulfill({status: 200, body: ''}));
    await page.goto('http://127.0.0.1:8765/');
    await page.setContent(`<!doctype html><html><head>
        <link rel="stylesheet" href="/assets/css/components.css">
        <style>
            body {margin:0} .slot {width:80vw}
            iframe.video {display:block;width:100%;height:240px;border:0}
            @media(max-width:700px) {iframe.video {height:180px}}
        </style>
        <script>
            window.klaroConfig = {
                lang:${JSON.stringify(lang)}, storageMethod:'cookie', storageName:'cmp-regression',
                default:false, acceptAll:true,
                translations:{hu:{youtubePlaceholder:{title:'YouTube-videó',description:'Csak engedéllyel.',button:'Betöltés'}}},
                services:[
                    {name:'google-tag-manager',purposes:['statistics'],cookies:[]},
                    {name:'google-advertising',purposes:['marketing'],cookies:[/^_gcl_.*/]},
                    {name:'youtube',purposes:['marketing'],cookies:[]}
                ]
            };
        </script></head><body>
        <div class="slot"><iframe id="intro-video" class="video" width="560" height="315"
            title="YouTube video player" data-cmp-src="https://www.youtube.com/embed/example"></iframe></div>
        <script src="/assets/js/cmp-bootstrap.js" data-gtm-id="${tagId}" data-youtube-service="youtube"></script>
        <script src="/assets/js/klaro.js"></script>
    </body></html>`);
    await expect(page.locator('#intro-video')).toHaveJSProperty('tagName', 'BUTTON');
    await expect.poll(() => page.evaluate(() => !!klaro.getManager().watchers.size)).toBe(true);
}

async function setConsent(page, analytics, advertising, youtube = false) {
    await page.evaluate(({analytics, advertising, youtube}) => {
        const m = klaro.getManager();
        m.updateConsent('google-tag-manager', analytics);
        m.updateConsent('google-advertising', advertising);
        m.updateConsent('youtube', youtube);
        m.saveAndApplyConsents();
    }, {analytics, advertising, youtube});
}

async function expectConsent(page, analytics, ads) {
    expect(await page.evaluate(() => Array.from(dataLayer.filter(x => x[0] === 'consent').slice(-1)[0]))).toEqual([
        'consent', 'update', {
            analytics_storage:analytics ? 'granted' : 'denied',
            ad_storage:ads ? 'granted' : 'denied',
            ad_user_data:ads ? 'granted' : 'denied',
            ad_personalization:ads ? 'granted' : 'denied'
        }
    ]);
}

for (const tagId of ['GTM-TEST123', 'G-TEST123']) {
    test(`${tagId}: unsaved choices stay blocked and analytics alone grants no advertising`, async ({page}) => {
        await render(page, tagId);
        await page.evaluate(() => {
            const manager = klaro.getManager();
            manager.updateConsent('google-tag-manager', true);
            manager.updateConsent('google-advertising', true);
        });
        await expect(page.locator('script[src*="googletagmanager.com"]')).toHaveCount(0);
        await setConsent(page, true, false);
        await expectConsent(page, true, false);
        await setConsent(page, false, false);
        await expectConsent(page, false, false);
    });

    test(`${tagId}: granular consent, withdrawal and re-grant without navigation`, async ({page}) => {
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await render(page, tagId);
        const origin = await page.evaluate(() => performance.timeOrigin);
        const script = page.locator('script[src*="googletagmanager.com"]');
        await expect(script).toHaveCount(0);
        await setConsent(page, false, false, true);
        await expect(script).toHaveCount(0);
        await setConsent(page, false, true);
        await expectConsent(page, false, true);
        await expect(script).toHaveAttribute('src', `https://www.googletagmanager.com/${tagId.startsWith('G-') ? 'gtag/js' : 'gtm.js'}?id=${tagId}`);
        await page.evaluate(() => document.cookie = '_gcl_au=test; path=/');
        await setConsent(page, true, false);
        await expectConsent(page, true, false);
        expect(await page.evaluate(() => document.cookie)).not.toContain('_gcl_au=');
        await setConsent(page, true, true);
        await expectConsent(page, true, true);
        await setConsent(page, false, false);
        await expectConsent(page, false, false);
        await setConsent(page, false, true);
        await expectConsent(page, false, true);
        await expect(script).toHaveCount(1);
        expect(await page.evaluate(() => performance.timeOrigin)).toBe(origin);
        if (tagId.startsWith('G-')) {
            const commands = await page.evaluate(() => dataLayer.map(x => Array.from(x)));
            expect(commands.filter(x => x[0] === 'config')).toEqual([['config', tagId]]);
            expect(commands.findIndex(x => x[0] === 'consent' && x[1] === 'update')).toBeLessThan(commands.findIndex(x => x[0] === 'config'));
        }
        expect(errors).toEqual([]);
        // Recreate the document with the saved cookie, as on a subsequent visit.
        await render(page, tagId);
        await expectConsent(page, false, true);
        await expect(page.locator('script[src*="googletagmanager.com"]')).toHaveCount(1);
    });
}

test('YouTube preserves identity, translations and responsive layout across consent cycles', async ({page}) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.setViewportSize({width:1200,height:900});
    await render(page);
    const video = page.locator('#intro-video');
    await expect(video).toContainText('YouTube-videó');
    await expect(video).toContainText('Betöltés');
    await expect(video).toHaveCSS('width', '960px');
    await expect(video).toHaveCSS('height', '240px');
    await expect(video).toHaveCSS('background-image', /i\.ytimg\.com/);
    await page.setViewportSize({width:600,height:800});
    await expect(video).toHaveCSS('width', '480px');
    await expect(video).toHaveCSS('height', '180px');
    const button = video.locator('.cmp-youtube-placeholder-button');
    await expect(button).toHaveCSS('box-sizing', 'border-box');
    const sizes = await button.evaluate(e => ({button:e.getBoundingClientRect().width,parent:e.parentElement.getBoundingClientRect().width}));
    expect(sizes.button).toBeLessThanOrEqual(sizes.parent);
    await video.click();
    await expect(video).toHaveJSProperty('tagName', 'IFRAME');
    await expect(video).toHaveAttribute('width', '560');
    await expect(video).toHaveCSS('width', '480px');
    await expect(page.locator('script[src*="googletagmanager.com"]')).toHaveCount(0);
    for (let i = 0; i < 2; i++) {
        await setConsent(page, false, false, false);
        await expect(video).toHaveJSProperty('tagName', 'BUTTON');
        await expect(video).toHaveCSS('height', '180px');
        await setConsent(page, false, false, true);
        await expect(video).toHaveJSProperty('tagName', 'IFRAME');
        await expect(video).toHaveCount(1);
    }
    await setConsent(page, false, false, false);
    await page.setViewportSize({width:1200,height:900});
    await expect(video).toHaveCSS('width', '960px');
    await expect(video).toHaveCSS('height', '240px');
    expect(errors).toEqual([]);
});

test('YouTube retains specific titles and falls back to English copy', async ({page}) => {
    await render(page, 'GTM-TEST123', 'de');
    await expect(page.locator('#intro-video')).toContainText('Load video');
    await page.evaluate(() => {
        const frame = document.createElement('iframe');
        frame.id = 'specific-title';
        frame.title = 'Clinic tour';
        frame.setAttribute('data-cmp-src', 'https://www.youtube.com/embed/tour');
        document.body.appendChild(frame);
    });
    await expect(page.locator('#specific-title strong')).toHaveText('Clinic tour');
    await setConsent(page, false, false, true);
    await expect(page.locator('#specific-title')).toHaveAttribute('title', 'Clinic tour');
});
