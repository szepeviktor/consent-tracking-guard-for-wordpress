const {expect, test} = require('@playwright/test');

test('keeps Cookieno change notice below the main description', async ({page}) => {
    await page.setViewportSize({width: 1280, height: 720});
    await page.setContent(`
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <link rel="stylesheet" href="http://127.0.0.1:8765/assets/css/klaro.css">
            <link rel="stylesheet" href="http://127.0.0.1:8765/assets/css/modal-styles/cookieno.css">
        </head>
        <body>
            <div class="klaro">
                <div class="cookie-notice" id="klaro-cookie-notice">
                    <div class="cn-body">
                        <h2 id="klaro-cookie-title">Privacy settings</h2>
                        <p id="klaro-cookie-notice-description">
                            Choose whether statistics and marketing services may load on this site.
                            This text intentionally wraps so the description occupies real space.
                        </p>
                        <p class="cn-changes">
                            There were changes since your last visit. Please renew your consent.
                        </p>
                        <div class="cn-ok">
                            <a class="cm-link cn-learn-more" href="#">Let me choose</a>
                            <div class="cn-buttons">
                                <button class="cm-btn cm-btn-danger cn-decline" type="button">Decline</button>
                                <button class="cm-btn cm-btn-success" type="button">OK</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
    `);

    const boxes = await page.evaluate(() => {
        const description = document
            .querySelector('#klaro-cookie-notice-description')
            .getBoundingClientRect();
        const changes = document.querySelector('.cn-changes').getBoundingClientRect();

        return {
            descriptionBottom: description.bottom,
            changesTop: changes.top
        };
    });

    expect(boxes.changesTop).toBeGreaterThanOrEqual(boxes.descriptionBottom);
});

test('uses the CookieYes light palette as the default Cookieno palette', async ({page}) => {
    await page.setViewportSize({width: 1280, height: 720});
    await page.setContent(`
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <link rel="stylesheet" href="http://127.0.0.1:8765/assets/css/klaro.css">
            <link rel="stylesheet" href="http://127.0.0.1:8765/assets/css/modal-styles/cookieno.css">
        </head>
        <body>
            <div class="klaro">
                <div class="cookie-notice" id="klaro-cookie-notice">
                    <div class="cn-body">
                        <p id="klaro-cookie-notice-description">
                            Choose whether statistics and marketing services may load on this site.
                        </p>
                        <div class="cn-ok">
                            <div class="cn-buttons">
                                <button class="cm-btn cm-btn-success" type="button">OK</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
    `);

    await expect(page.locator('#klaro-cookie-notice')).toHaveCSS('border-top-color', 'rgb(244, 244, 244)');
    await expect(page.locator('#klaro-cookie-notice-description')).toHaveCSS('color', 'rgb(33, 33, 33)');
    await expect(page.locator('.cm-btn-success')).toHaveCSS('background-color', 'rgb(24, 99, 220)');
});

test('lets sites recolor the Cookieno accent with one CSS custom property', async ({page}) => {
    await page.setViewportSize({width: 1280, height: 720});
    await page.setContent(`
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <link rel="stylesheet" href="http://127.0.0.1:8765/assets/css/klaro.css">
            <link rel="stylesheet" href="http://127.0.0.1:8765/assets/css/modal-styles/cookieno.css">
            <style>:root { --cookieno-accent-color: #0057ff; }</style>
        </head>
        <body>
            <div class="klaro">
                <div class="cookie-notice" id="klaro-cookie-notice">
                    <div class="cn-body">
                        <p id="klaro-cookie-notice-description">
                            Choose whether statistics and marketing services may load on this site.
                        </p>
                        <div class="cn-ok">
                            <div class="cn-buttons">
                                <button class="cm-btn cm-btn-success" type="button">OK</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="klaro-consent-container">
                <div class="klaro-consent-bar">
                    <div class="klaro-notice">
                        <div class="klaro-notice-group">
                            <div class="klaro-notice-des">
                                <p><a href="#">Cookie settings</a></p>
                            </div>
                            <div class="klaro-notice-btn-wrapper">
                                <button class="klaro-btn klaro-btn-accept" type="button">Accept</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
    `);

    const styles = await page.evaluate(() => {
        const klaroButton = getComputedStyle(document.querySelector('.cm-btn-success'));
        const consentLink = getComputedStyle(document.querySelector('.klaro-notice-des a'));
        const consentButton = getComputedStyle(document.querySelector('.klaro-btn-accept'));

        return {
            klaroButtonBackground: klaroButton.backgroundColor,
            consentLinkColor: consentLink.color,
            consentButtonBackground: consentButton.backgroundColor,
            consentButtonBorderColor: consentButton.borderTopColor
        };
    });

    expect(styles).toEqual({
        klaroButtonBackground: 'rgb(0, 87, 255)',
        consentLinkColor: 'rgb(0, 87, 255)',
        consentButtonBackground: 'rgb(0, 87, 255)',
        consentButtonBorderColor: 'rgb(0, 87, 255)'
    });
});
