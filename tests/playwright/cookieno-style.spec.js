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
