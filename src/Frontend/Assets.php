<?php

declare(strict_types=1);

namespace SzepeViktor\ConsentTrackingGuard\Frontend;

use SzepeViktor\ConsentTrackingGuard\Config;
use SzepeViktor\ConsentTrackingGuard\Options;

final class Assets
{
    private const KLAVIYO_SERVICE_NAME = 'klaviyo';
    private const PIXELYOURSITE_STATISTICS_SERVICE_NAME = 'pixelyoursite-statistics';
    private const PIXELYOURSITE_MARKETING_SERVICE_NAME = 'pixelyoursite-marketing';
    private const GOOGLE_SITE_KIT_SIGN_IN_SERVICE_NAME = 'google-site-kit-sign-in';
    private const TRIPLE_WHALE_SERVICE_NAME = 'triple-whale-pixel';
    private const KLAVIYO_SCRIPT_HANDLES = [
        'klaviyojs' => true,
        'kl-identify-browser' => true,
    ];

    private const MODAL_STYLE_STYLESHEETS = [
        Options::MODAL_STYLE_VIKTOR_DEFAULT => 'viktor-default.css',
        Options::MODAL_STYLE_LIGHT => 'light.css',
        Options::MODAL_STYLE_DARK => 'dark.css',
        Options::MODAL_STYLE_TWENTY_TWENTY_FIVE => 'twenty-twenty-five.css',
        Options::MODAL_STYLE_COOKIENO => 'cookieno.css',
    ];

    private Options $options;

    private ConsentApiBridge $consent_api_bridge;

    public function __construct(Options $options, ConsentApiBridge $consent_api_bridge)
    {
        $this->options = $options;
        $this->consent_api_bridge = $consent_api_bridge;
    }

    public function register(): void
    {
        $lang = strtolower(substr(determine_locale(), 0, 2));
        $this->consent_api_bridge->register_services($this->build_services($lang));

        add_action('wp_enqueue_scripts', [$this, 'enqueue'], 100);
        add_action('wp_enqueue_scripts', [$this, 'add_triple_whale_tracking_consent_handoff'], 101);
        add_filter('script_loader_tag', [$this, 'filter_bootstrap_tag'], 10, 2);
        add_filter('script_loader_tag', [$this, 'filter_klaviyo_script_loader_tag'], 100, 3);
    }

    public function enqueue(): void
    {
        $modalStyle = (string) $this->options->get('modal_style');
        $klaroConfig = $this->build_klaro_config();

        $this->enqueueStyles($modalStyle);
        $this->enqueueScripts($klaroConfig);
    }

    private function enqueueStyles(string $modalStyle): void
    {
        wp_enqueue_style(
            'viktors-consent-tracking-guard-klaro',
            plugins_url('assets/css/klaro.css', Config::get('filePath')),
            [],
            Config::get('version')
        );

        wp_enqueue_style(
            'viktors-consent-tracking-guard-components',
            plugins_url('assets/css/components.css', Config::get('filePath')),
            ['viktors-consent-tracking-guard-klaro'],
            Config::get('version')
        );

        if (isset(self::MODAL_STYLE_STYLESHEETS[$modalStyle])) { // phpcs:ignore SlevomatCodingStandard.ControlStructures.EarlyExit.EarlyExitNotUsed -- Conditional enqueue reads clearer here.
            wp_enqueue_style(
                'viktors-consent-tracking-guard-modal-style',
                plugins_url(
                    sprintf(
                        'assets/css/modal-styles/%s',
                        self::MODAL_STYLE_STYLESHEETS[$modalStyle]
                    ),
                    Config::get('filePath')
                ),
                ['viktors-consent-tracking-guard-components'],
                Config::get('version')
            );
        }
    }

    /**
     * @param array<string, mixed> $klaroConfig
     */
    private function enqueueScripts(array $klaroConfig): void
    {
        wp_enqueue_script(
            'viktors-consent-tracking-guard-bootstrap',
            plugins_url('assets/js/cmp-bootstrap.js', Config::get('filePath')),
            [],
            Config::get('version'),
            false
        );

        wp_enqueue_script(
            'viktors-consent-tracking-guard-klaro',
            plugins_url('assets/js/klaro.js', Config::get('filePath')),
            [],
            Config::get('version'),
            false
        );

        wp_script_add_data('viktors-consent-tracking-guard-klaro', 'defer', true);

        wp_add_inline_script(
            'viktors-consent-tracking-guard-klaro',
            sprintf('window.klaroConfig = %s;', wp_json_encode($klaroConfig)),
            'before'
        );

        if (! $this->consent_api_bridge->is_api_available()) {
            return;
        }

        wp_enqueue_script(
            'viktors-consent-tracking-guard-consent-api-bridge',
            plugins_url('assets/js/wp-consent-api-bridge.js', Config::get('filePath')),
            ['viktors-consent-tracking-guard-klaro', 'wp-consent-api'],
            Config::get('version'),
            true
        );
    }

    public function add_triple_whale_tracking_consent_handoff(): void
    {
        if (! $this->options->enabled('enable_triple_whale')) {
            return;
        }

        /*
         * Triple Whale has two different consent surfaces, and their timing is
         * not equivalent. The public command
         * `TriplePixel('trackingConsent', false)` is handled by the
         * `TriplePixel` dispatcher only after the official snippet has loaded
         * and installed that dispatcher. The vendor script's initial module
         * body already starts its page-load flow before queued public commands
         * can be replayed, so using the public command alone is too late for
         * first-load blocking.
         *
         * The vendor script also checks
         * `window.TriplePixelData.trackingConsent` inside its own tracking
         * eligibility logic. When that property is exactly `false`, tracking
         * is treated as disabled before the initial page-load work proceeds.
         * Publishing this data object immediately before the official
         * `triplewhale-pixel-snippet` handle runs gives the vendor script the
         * earliest consent state it understands, without replacing
         * `window.TriplePixel`, replaying a custom queue, or racing the
         * official loader.
         */
        wp_add_inline_script(
            'triplewhale-pixel-snippet',
            <<<'JS'
(function () {
    window.TriplePixelData = window.TriplePixelData || {};
    window.TriplePixelData.trackingConsent = false;
}());
JS,
            'before'
        );
    }

    public function filter_klaviyo_script_loader_tag(string $tag, string $handle, string $src): string
    {
        if (! $this->options->enabled('enable_klaviyo')) {
            return $tag;
        }

        if (
            ! isset(self::KLAVIYO_SCRIPT_HANDLES[$handle])
            && strpos($src, 'static.klaviyo.com/onsite/js/') === false
            && strpos($src, 'static-tracking.klaviyo.com/onsite/js/') === false
        ) {
            return $tag;
        }

        $booleanAttributes = [];

        foreach (['async', 'defer'] as $attribute) {
            if (preg_match(sprintf('/\s%s(?:[\s=>]|$)/', preg_quote($attribute, '/')), $tag) !== 1) {
                continue;
            }

            $booleanAttributes[] = sprintf(' %s', $attribute);
        }

        return sprintf(
            '<script id="%s-js" type="text/plain" data-type="application/javascript" data-name="%s" data-src="%s"%s></script>', // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Rewrites an already enqueued script tag for consent gating.
            esc_attr($handle),
            esc_attr(self::KLAVIYO_SERVICE_NAME),
            esc_url($src),
            implode('', $booleanAttributes)
        );
    }

    public function filter_bootstrap_tag(string $tag, string $handle): string
    {
        $attributes = [];

        if ($handle !== 'viktors-consent-tracking-guard-bootstrap') {
            return $tag;
        }

        $gtmId = (string) $this->options->get('gtm_id');
        $clarityProjectId = (string) $this->options->get('clarity_project_id');
        $hotjarId = (string) $this->options->get('hotjar_id');
        $metaPixelId = (string) $this->options->get('meta_pixel_id');
        $linkedinPartnerId = (string) $this->options->get('linkedin_partner_id');
        $facebookForWooCommerceEnabled = $this->options->enabled('enable_facebook_for_woocommerce');

        if ($gtmId !== '') {
            $attributes['data-gtm-id'] = $gtmId;
        }

        if ($clarityProjectId !== '') {
            $attributes['data-clarity-project-id'] = $clarityProjectId;
        }

        if ($hotjarId !== '') {
            $attributes['data-hotjar-id'] = $hotjarId;
            $attributes['data-hotjar-version'] = (string) $this->options->get('hotjar_version');
        }

        if ($metaPixelId !== '' && ! $facebookForWooCommerceEnabled) {
            $attributes['data-meta-pixel-id'] = $metaPixelId;
        }

        if ($linkedinPartnerId !== '') {
            $attributes['data-linkedin-partner-id'] = $linkedinPartnerId;
        }

        if ($this->options->enabled('enable_triple_whale')) {
            $attributes['data-triple-whale-service'] = self::TRIPLE_WHALE_SERVICE_NAME;
        }

        if ($this->options->enabled('enable_klaviyo')) {
            $attributes['data-klaviyo'] = 'true';
        }

        if ($this->options->enabled('enable_pixelyoursite')) {
            $attributes['data-pixelyoursite-statistics-service'] = self::PIXELYOURSITE_STATISTICS_SERVICE_NAME;
            $attributes['data-pixelyoursite-marketing-service'] = self::PIXELYOURSITE_MARKETING_SERVICE_NAME;
        }

        if ($facebookForWooCommerceEnabled) {
            $attributes['data-facebook-for-woocommerce-service'] = 'facebook-for-woocommerce';
        }

        if ($this->options->enabled('enable_youtube')) {
            $attributes['data-youtube-service'] = 'youtube';
        }

        if ($this->options->enabled('enable_floating')) {
            $attributes['data-floating'] = 'true';
        }

        if ($attributes === []) {
            return $tag;
        }

        $htmlAttributes = [];

        foreach ($attributes as $name => $attributeValue) {
            $htmlAttributes[] = sprintf(' %s="%s"', esc_attr($name), esc_attr($attributeValue));
        }

        return str_replace(
            '<script ',
            sprintf('<script%s ', implode('', $htmlAttributes)),
            $tag
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function build_klaro_config(): array
    {
        $lang = strtolower(substr(determine_locale(), 0, 2));
        $privacyPolicyUrl = esc_url_raw(get_privacy_policy_url());

        $config = [
            'version' => 1,
            'elementID' => 'klaro',
            'storageMethod' => 'cookie',
            'storageName' => 'klaro',
            'cookieExpiresAfterDays' => 365,
            'default' => false,
            'mustConsent' => false,
            'acceptAll' => true,
            'hideDeclineAll' => false,
            'hideLearnMore' => false,
            'noticeAsModal' => false,
            'showNoticeTitle' => true,
            'htmlTexts' => true,
            'embedded' => false,
            'groupByPurpose' => true,
            'lang' => $lang,
            'translations' => [
                $lang => $this->buildTranslations(),
            ],
            'services' => $this->build_services($lang),
        ];

        if ($privacyPolicyUrl !== '') {
            $config['privacyPolicyUrl'] = $privacyPolicyUrl;
        }

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTranslations(): array
    {
        return [
            'consentNotice' => [
                'title' => (string) $this->options->get('notice_title'),
                'description' => $this->replacePrivacyPolicyShortcode(
                    (string) $this->options->get('notice_description')
                ),
                'changeDescription' => __(
                    'There were changes since your last visit, please renew your consent.',
                    'viktors-consent-tracking-guard'
                ),
                'learnMore' => __('Learn more', 'viktors-consent-tracking-guard'),
                'testing' => __('Testing mode!', 'viktors-consent-tracking-guard'),
            ],
            'consentModal' => [
                'title' => (string) $this->options->get('modal_title'),
                'description' => $this->replacePrivacyPolicyShortcode(
                    (string) $this->options->get('modal_description')
                ),
            ],
            'contextualConsent' => [
                'acceptAlways' => __('Always', 'viktors-consent-tracking-guard'),
                'acceptOnce' => __('Yes', 'viktors-consent-tracking-guard'),
                'description' => __(
                    'Do you want to load external content supplied by {title}?',
                    'viktors-consent-tracking-guard'
                ),
                'descriptionEmptyStore' => __(
                    'To agree to this service permanently, you must accept {title} in the {link}.',
                    'viktors-consent-tracking-guard'
                ),
                'modalLinkText' => __('Consent Manager', 'viktors-consent-tracking-guard'),
            ],
            'purposes' => $this->buildPurposeTranslations(),
            'purposeItem' => [
                'service' => __('service', 'viktors-consent-tracking-guard'),
                'services' => __('services', 'viktors-consent-tracking-guard'),
            ],
            'ok' => __('OK', 'viktors-consent-tracking-guard'),
            'save' => __('Save', 'viktors-consent-tracking-guard'),
            'acceptAll' => __('Accept all', 'viktors-consent-tracking-guard'),
            'acceptSelected' => __('Accept selected', 'viktors-consent-tracking-guard'),
            'declineAll' => __('Decline all', 'viktors-consent-tracking-guard'),
            'decline' => __('Decline', 'viktors-consent-tracking-guard'),
            'close' => __('Close', 'viktors-consent-tracking-guard'),
            'poweredBy' => __('Realized with Klaro!', 'viktors-consent-tracking-guard'),
            'service' => $this->buildServiceTranslations(),
        ];
    }

    private function replacePrivacyPolicyShortcode(string $text): string
    {
        return str_replace('[privacy-policy]', esc_url(get_privacy_policy_url()), $text);
    }

    /**
     * @return array<string, string>
     */
    private function buildPurposeTranslations(): array
    {
        return [
            'functional' => __('Functional', 'viktors-consent-tracking-guard'),
            'preferences' => __('Preferences', 'viktors-consent-tracking-guard'),
            'statistics-anonymous' => __('Anonymous statistics', 'viktors-consent-tracking-guard'),
            'statistics' => __('Statistics', 'viktors-consent-tracking-guard'),
            'marketing' => __('Marketing', 'viktors-consent-tracking-guard'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildServiceTranslations(): array
    {
        return [
            'disableAll' => [
                'title' => __('Enable or disable all services', 'viktors-consent-tracking-guard'),
                'description' => __(
                    'Use this switch to change all optional services at once.',
                    'viktors-consent-tracking-guard'
                ),
            ],
            'optOut' => [
                'title' => __('(opt-out)', 'viktors-consent-tracking-guard'),
                'description' => __(
                    'This service loads by default, but can be disabled later.',
                    'viktors-consent-tracking-guard'
                ),
            ],
            'required' => [
                'title' => __('(required)', 'viktors-consent-tracking-guard'),
                'description' => __(
                    'This service is required for the site to function.',
                    'viktors-consent-tracking-guard'
                ),
            ],
            'purposes' => __('Purposes', 'viktors-consent-tracking-guard'),
            'purpose' => __('Purpose', 'viktors-consent-tracking-guard'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function build_services(string $lang): array
    {
        $services = $this->buildCoreServices($lang);
        $gtmId = (string) $this->options->get('gtm_id');
        $clarityProjectId = (string) $this->options->get('clarity_project_id');
        $hotjarId = (string) $this->options->get('hotjar_id');
        $metaPixelId = (string) $this->options->get('meta_pixel_id');
        $linkedinPartnerId = (string) $this->options->get('linkedin_partner_id');
        $facebookForWooCommerceEnabled = $this->options->enabled('enable_facebook_for_woocommerce');

        if ($gtmId !== '') {
            $services[] = $this->buildGoogleTagManagerService($lang);
        }

        if ($clarityProjectId !== '') {
            $services[] = $this->buildMicrosoftClarityService($lang);
        }

        if ($hotjarId !== '') {
            $services[] = $this->buildHotjarService($lang);
        }

        if ($metaPixelId !== '' && ! $facebookForWooCommerceEnabled) {
            $services[] = $this->buildMetaPixelService($lang);
        }

        if ($linkedinPartnerId !== '') {
            $services[] = $this->buildLinkedInService($lang);
        }

        if ($this->options->enabled('enable_triple_whale')) {
            $services[] = $this->buildTripleWhaleService($lang);
        }

        if ($this->options->enabled('enable_polylang')) {
            $polylangService = $this->buildPolylangService($lang);

            if ($polylangService !== null) {
                $services[] = $polylangService;
            }
        }

        if ($this->options->enabled('enable_woocommerce')) {
            $services[] = $this->buildWooCommerceFunctionalService($lang);
            $services[] = $this->buildWooCommerceAttributionService($lang);
        }

        if ($facebookForWooCommerceEnabled) {
            $services[] = $this->buildFacebookForWooCommerceService($lang);
        }

        if ($this->options->enabled('enable_google_site_kit_sign_in')) {
            $services[] = $this->buildGoogleSiteKitSignInService($lang);
        }

        if ($this->options->enabled('enable_klaviyo')) {
            $services[] = $this->buildKlaviyoService($lang);
        }

        if ($this->options->enabled('enable_pixelyoursite')) {
            $services[] = $this->buildPixelYourSiteStatisticsService($lang);
            $services[] = $this->buildPixelYourSiteMarketingService($lang);
        }

        if ($this->options->enabled('enable_woodmart')) {
            $services[] = $this->buildWoodMartService($lang);
        }

        if ($this->options->enabled('enable_wordfence')) {
            $services[] = $this->buildWordfenceService($lang);
        }

        if ($this->options->enabled('enable_youtube')) {
            $services[] = $this->buildYouTubeService($lang);
        }

        return $services;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildCoreServices(string $lang): array
    {
        return [
            $this->buildKlaroService($lang),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildKlaroService(string $lang): array
    {
        return [
            'name' => 'klaro',
            'title' => __('Cookie consent settings', 'viktors-consent-tracking-guard'),
            'purposes' => ['functional'],
            'default' => true,
            'required' => true,
            'optOut' => false,
            'onlyOnce' => true,
            'cookies' => ['klaro'],
            'wpConsentCategory' => 'functional',
            'wpConsentCookies' => [
                $this->buildCookieInfo(
                    'klaro',
                    __('365 days', 'viktors-consent-tracking-guard'),
                    __('Stores the visitor’s consent choices.', 'viktors-consent-tracking-guard')
                ),
            ],
            'translations' => [
                $lang => [
                    'title' => __('Cookie consent settings', 'viktors-consent-tracking-guard'),
                    'description' => __('Stores the visitor’s consent choice.', 'viktors-consent-tracking-guard'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGoogleTagManagerService(string $lang): array
    {
        return $this->buildOptionalService(
            'google-tag-manager',
            __('Google Tag Manager', 'viktors-consent-tracking-guard'),
            'statistics',
            ['_ga', '^_ga_.*', '_gid', '^_gat.*'],
            [
                $this->buildCookieInfo(
                    '_ga',
                    __('2 years', 'viktors-consent-tracking-guard'),
                    __('Distinguishes visitors for analytics reporting.', 'viktors-consent-tracking-guard')
                ),
                $this->buildCookieInfo(
                    '_gid',
                    __('24 hours', 'viktors-consent-tracking-guard'),
                    __('Distinguishes visitors for daily analytics reporting.', 'viktors-consent-tracking-guard')
                ),
            ],
            __('Loads analytics tags managed through Google Tag Manager.', 'viktors-consent-tracking-guard'),
            $lang
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMicrosoftClarityService(string $lang): array
    {
        return $this->buildOptionalService(
            'microsoft-clarity',
            __('Microsoft Clarity', 'viktors-consent-tracking-guard'),
            'statistics',
            ['_clck', '_clsk'],
            [
                $this->buildCookieInfo(
                    '_clck',
                    __('1 year', 'viktors-consent-tracking-guard'),
                    __('Persists the Clarity visitor identifier and preferences.', 'viktors-consent-tracking-guard')
                ),
                $this->buildCookieInfo(
                    '_clsk',
                    __('1 day', 'viktors-consent-tracking-guard'),
                    __('Groups Clarity page views into a recording session.', 'viktors-consent-tracking-guard')
                ),
            ],
            __('Measures how visitors use the site through session analytics.', 'viktors-consent-tracking-guard'),
            $lang
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildHotjarService(string $lang): array
    {
        return $this->buildOptionalService(
            'hotjar',
            __('Hotjar', 'viktors-consent-tracking-guard'),
            'statistics',
            $this->buildHotjarCookies(),
            $this->buildHotjarCookieInfo(),
            __('Measures visitor behavior and collects usability feedback.', 'viktors-consent-tracking-guard'),
            $lang
        );
    }

    /**
     * @return array<int, string>
     */
    private function buildHotjarCookies(): array
    {
        return [
            '_hjCookieTest',
            '_hjLocalStorageTest',
            '_hjSessionStorageTest',
            '_hjTLDTest',
            '^_hjSessionUser_.*',
            '^_hjSession_.*',
            '_hjClosedSurveyInvites',
            '_hjDonePolls',
            '_hjMinimizedPolls',
            '_hjShownFeedbackMessage',
            '_hjSessionTooLarge',
            '_hjSessionRejected',
            '_hjHasCachedUserAttributes',
            '_hjUserAttributesHash',
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildHotjarCookieInfo(): array
    {
        return [
            $this->buildCookieInfo(
                '_hjClosedSurveyInvites',
                __('1 year', 'viktors-consent-tracking-guard'),
                __('Prevents a dismissed Hotjar survey invitation from reappearing.', 'viktors-consent-tracking-guard')
            ),
            $this->buildCookieInfo(
                '_hjDonePolls',
                __('1 year', 'viktors-consent-tracking-guard'),
                __('Prevents a completed Hotjar poll from reappearing.', 'viktors-consent-tracking-guard')
            ),
            $this->buildCookieInfo(
                '_hjMinimizedPolls',
                __('1 year', 'viktors-consent-tracking-guard'),
                __('Keeps a minimized Hotjar poll minimized.', 'viktors-consent-tracking-guard')
            ),
            $this->buildCookieInfo(
                '_hjShownFeedbackMessage',
                __('1 day', 'viktors-consent-tracking-guard'),
                __('Prevents repeated display of Hotjar feedback messaging.', 'viktors-consent-tracking-guard')
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMetaPixelService(string $lang): array
    {
        return $this->buildOptionalService(
            'meta-pixel',
            __('Meta Pixel', 'viktors-consent-tracking-guard'),
            'marketing',
            ['_fbp', '_fbc'],
            [
                $this->buildCookieInfo(
                    '_fbp',
                    __('90 days', 'viktors-consent-tracking-guard'),
                    __(
                        'Identifies browsers for Meta advertising measurement.',
                        'viktors-consent-tracking-guard'
                    )
                ),
                $this->buildCookieInfo(
                    '_fbc',
                    __('90 days', 'viktors-consent-tracking-guard'),
                    __(
                        'Stores the Meta advertising click identifier.',
                        'viktors-consent-tracking-guard'
                    )
                ),
            ],
            __('Measures advertising performance and visitor actions for Meta.', 'viktors-consent-tracking-guard'),
            $lang
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFacebookForWooCommerceService(string $lang): array
    {
        return $this->buildOptionalService(
            'facebook-for-woocommerce',
            __('Meta for WooCommerce', 'viktors-consent-tracking-guard'),
            'marketing',
            ['_fbp', '_fbc', 'wc_facebook_signals_state'],
            [
                $this->buildCookieInfo(
                    '_fbp',
                    __('90 days', 'viktors-consent-tracking-guard'),
                    __(
                        'Identifies browsers for Meta advertising measurement.',
                        'viktors-consent-tracking-guard'
                    )
                ),
                $this->buildCookieInfo(
                    '_fbc',
                    __('90 days', 'viktors-consent-tracking-guard'),
                    __(
                        'Stores the Meta advertising click identifier.',
                        'viktors-consent-tracking-guard'
                    )
                ),
                $this->buildCookieInfo(
                    'wc_facebook_signals_state',
                    __('1 year', 'viktors-consent-tracking-guard'),
                    __(
                        'Stores whether Meta for WooCommerce browser signals are held or released.',
                        'viktors-consent-tracking-guard'
                    )
                ),
            ],
            __(
                'Measures WooCommerce product views, cart actions, and purchases for Meta advertising.',
                'viktors-consent-tracking-guard'
            ),
            $lang
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPixelYourSiteStatisticsService(string $lang): array
    {
        return $this->buildOptionalService(
            self::PIXELYOURSITE_STATISTICS_SERVICE_NAME,
            __('PixelYourSite analytics', 'viktors-consent-tracking-guard'),
            'statistics',
            [
                'pys_first_visit',
                'pys_landing_page',
                'last_pys_landing_page',
                'pysTrafficSource',
                'last_pysTrafficSource',
                'pys_start_session',
                'pys_session_limit',
                '^pys_utm_.*',
                '^last_pys_utm_.*',
            ],
            [
                $this->buildCookieInfo(
                    'pys_first_visit',
                    __('Configured in PixelYourSite', 'viktors-consent-tracking-guard'),
                    __('Stores whether this is the visitor’s first tracked visit.', 'viktors-consent-tracking-guard')
                ),
                $this->buildCookieInfo(
                    'pys_landing_page',
                    __('Configured in PixelYourSite', 'viktors-consent-tracking-guard'),
                    __('Stores the landing page for analytics attribution.', 'viktors-consent-tracking-guard')
                ),
                $this->buildCookieInfo(
                    'pysTrafficSource',
                    __('Configured in PixelYourSite', 'viktors-consent-tracking-guard'),
                    __('Stores the traffic source for analytics attribution.', 'viktors-consent-tracking-guard')
                ),
            ],
            __('Allows PixelYourSite analytics tags and attribution cookies.', 'viktors-consent-tracking-guard'),
            $lang
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPixelYourSiteMarketingService(string $lang): array
    {
        return $this->buildOptionalService(
            self::PIXELYOURSITE_MARKETING_SERVICE_NAME,
            __('PixelYourSite advertising', 'viktors-consent-tracking-guard'),
            'marketing',
            [
                '_fbp',
                '_fbc',
                'pbid',
                'pys_advanced_form_data',
                '^pys_.*id$',
                '^last_pys_.*id$',
            ],
            [
                $this->buildCookieInfo(
                    '_fbp',
                    __('90 days', 'viktors-consent-tracking-guard'),
                    __('Identifies browsers for Meta advertising measurement.', 'viktors-consent-tracking-guard')
                ),
                $this->buildCookieInfo(
                    'pbid',
                    __('Configured in PixelYourSite', 'viktors-consent-tracking-guard'),
                    __('Stores a PixelYourSite external identifier for advertising events.', 'viktors-consent-tracking-guard')
                ),
                $this->buildCookieInfo(
                    'pys_advanced_form_data',
                    __('Configured in PixelYourSite', 'viktors-consent-tracking-guard'),
                    __('Stores form-derived data used to improve advertising event matching.', 'viktors-consent-tracking-guard')
                ),
            ],
            __('Allows PixelYourSite advertising pixels, server events, and matching cookies.', 'viktors-consent-tracking-guard'),
            $lang
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGoogleSiteKitSignInService(string $lang): array
    {
        return [
            'name' => self::GOOGLE_SITE_KIT_SIGN_IN_SERVICE_NAME,
            'title' => __('Site Kit Sign in with Google', 'viktors-consent-tracking-guard'),
            'purposes' => ['functional'],
            'default' => true,
            'required' => true,
            'optOut' => false,
            'onlyOnce' => true,
            'cookies' => ['g_state', 'googlesitekit_auth_nonce', 'googlesitekit_auth_redirect_to'],
            'wpConsentCategory' => 'functional',
            'wpConsentCookies' => [
                $this->buildCookieInfo(
                    'g_state',
                    __('Up to 180 days', 'viktors-consent-tracking-guard'),
                    __('Stores Google Identity Services prompt and sign-in state.', 'viktors-consent-tracking-guard')
                ),
                $this->buildCookieInfo(
                    'googlesitekit_auth_nonce',
                    __('15 minutes', 'viktors-consent-tracking-guard'),
                    __('Secures the Site Kit Sign in with Google authentication request.', 'viktors-consent-tracking-guard')
                ),
                $this->buildCookieInfo(
                    'googlesitekit_auth_redirect_to',
                    __('5 minutes', 'viktors-consent-tracking-guard'),
                    __('Remembers where Site Kit should return the visitor after Google sign-in.', 'viktors-consent-tracking-guard')
                ),
            ],
            'translations' => [
                $lang => [
                    'title' => __('Site Kit Sign in with Google', 'viktors-consent-tracking-guard'),
                    'description' => __(
                        'Supports Site Kit’s Google login button and One Tap sign-in.',
                        'viktors-consent-tracking-guard'
                    ),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLinkedInService(string $lang): array
    {
        return $this->buildOptionalService(
            'linkedin-insight-tag',
            __('LinkedIn Insight Tag', 'viktors-consent-tracking-guard'),
            'marketing',
            ['li_fat_id', 'li_giant'],
            [
                $this->buildCookieInfo(
                    'li_fat_id',
                    __('30 days', 'viktors-consent-tracking-guard'),
                    __('Stores the LinkedIn advertising click identifier.', 'viktors-consent-tracking-guard')
                ),
                $this->buildCookieInfo(
                    'li_giant',
                    __('7 days', 'viktors-consent-tracking-guard'),
                    __('Supports LinkedIn conversion attribution.', 'viktors-consent-tracking-guard')
                ),
            ],
            __('Measures LinkedIn campaign performance and website conversions.', 'viktors-consent-tracking-guard'),
            $lang
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTripleWhaleService(string $lang): array
    {
        return $this->buildOptionalService(
            'triple-whale-pixel',
            __('Triple Whale Pixel', 'viktors-consent-tracking-guard'),
            'marketing',
            ['TriplePixel', 'TriplePixelU', 'di_pmt_wt', 'configSecurityConfModel'],
            [
                $this->buildCookieInfo(
                    'TriplePixel',
                    __('Persistent', 'viktors-consent-tracking-guard'),
                    __('Stores Triple Whale pixel runtime and visit count data.', 'viktors-consent-tracking-guard')
                ),
                $this->buildCookieInfo(
                    'TriplePixelU',
                    __('Persistent', 'viktors-consent-tracking-guard'),
                    __('Stores recent page visit details for Triple Whale attribution.', 'viktors-consent-tracking-guard')
                ),
                $this->buildCookieInfo(
                    'di_pmt_wt',
                    __('Persistent', 'viktors-consent-tracking-guard'),
                    __('Stores the Triple Whale browser profile identifier.', 'viktors-consent-tracking-guard')
                ),
            ],
            __('Measures visitor journeys, advertising attribution, and ecommerce events for Triple Whale.', 'viktors-consent-tracking-guard'),
            $lang
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildPolylangService(string $lang): ?array
    {
        $cookieName = defined('PLL_COOKIE') ? constant('PLL_COOKIE') : 'pll_language';

        if (! is_string($cookieName) || $cookieName === '') {
            return null;
        }

        return [
            'name' => 'polylang',
            'title' => __('Polylang', 'viktors-consent-tracking-guard'),
            'purposes' => ['functional'],
            'default' => true,
            'required' => true,
            'optOut' => false,
            'onlyOnce' => true,
            'cookies' => [$cookieName],
            'wpConsentCategory' => 'preferences',
            'wpConsentCookies' => [
                $this->buildCookieInfo(
                    $cookieName,
                    __('1 year', 'viktors-consent-tracking-guard'),
                    __(
                        'Stores the visitor’s last browsed language for Polylang and Polylang for WooCommerce.',
                        'viktors-consent-tracking-guard'
                    )
                ),
            ],
            'translations' => [
                $lang => [
                    'title' => __('Polylang', 'viktors-consent-tracking-guard'),
                    'description' => __(
                        'Remembers the selected language for multilingual content and translated WooCommerce flows.',
                        'viktors-consent-tracking-guard'
                    ),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildWooCommerceFunctionalService(string $lang): array
    {
        return [
            'name' => 'woocommerce',
            'title' => __('WooCommerce', 'viktors-consent-tracking-guard'),
            'purposes' => ['functional'],
            'default' => true,
            'required' => true,
            'optOut' => false,
            'onlyOnce' => true,
            'cookies' => $this->buildWooCommerceFunctionalCookies(),
            'wpConsentCategory' => 'functional',
            'wpConsentCookies' => $this->buildWooCommerceFunctionalCookieInfo(),
            'translations' => [
                $lang => [
                    'title' => __('WooCommerce', 'viktors-consent-tracking-guard'),
                    'description' => __(
                        'Keeps the shopping cart, checkout, customer session, and store notices working.',
                        'viktors-consent-tracking-guard'
                    ),
                ],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function buildWooCommerceFunctionalCookies(): array
    {
        return [
            'woocommerce_cart_hash',
            'woocommerce_items_in_cart',
            '^wp_woocommerce_session_.*',
            '^wc_cart_hash_.*',
            '^wc_fragments_.*',
            'wc_cart_created',
            'woocommerce_recently_viewed',
            '^store_notice.*',
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildWooCommerceFunctionalCookieInfo(): array
    {
        return [
            $this->buildCookieInfo(
                'woocommerce_cart_hash',
                __('Session', 'viktors-consent-tracking-guard'),
                __('Helps WooCommerce detect cart changes.', 'viktors-consent-tracking-guard')
            ),
            $this->buildCookieInfo(
                'woocommerce_items_in_cart',
                __('Session', 'viktors-consent-tracking-guard'),
                __('Helps WooCommerce keep cart data synchronized.', 'viktors-consent-tracking-guard')
            ),
            $this->buildCookieInfo(
                'wp_woocommerce_session_*',
                __('2 days', 'viktors-consent-tracking-guard'),
                __('Stores a unique customer session identifier for cart and checkout data.', 'viktors-consent-tracking-guard')
            ),
            $this->buildCookieInfo(
                'woocommerce_recently_viewed',
                __('Session', 'viktors-consent-tracking-guard'),
                __('Stores products viewed by the visitor.', 'viktors-consent-tracking-guard')
            ),
            $this->buildCookieInfo(
                'store_notice*',
                __('Session', 'viktors-consent-tracking-guard'),
                __('Remembers dismissed WooCommerce store notices.', 'viktors-consent-tracking-guard')
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildWooCommerceAttributionService(string $lang): array
    {
        return $this->buildOptionalService(
            'woocommerce-attribution',
            __('WooCommerce source attribution', 'viktors-consent-tracking-guard'),
            'statistics',
            $this->buildWooCommerceAttributionCookies(),
            $this->buildWooCommerceAttributionCookieInfo(),
            __(
                'Stores first-party source attribution data for WooCommerce order reporting.',
                'viktors-consent-tracking-guard'
            ),
            $lang
        );
    }

    /**
     * @return array<int, string>
     */
    private function buildWooCommerceAttributionCookies(): array
    {
        return [
            'sbjs_current',
            'sbjs_current_add',
            'sbjs_first',
            'sbjs_first_add',
            'sbjs_migrations',
            'sbjs_session',
            'sbjs_udata',
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildWooCommerceAttributionCookieInfo(): array
    {
        return [
            $this->buildCookieInfo(
                'sbjs_current',
                __('6 months', 'viktors-consent-tracking-guard'),
                __(
                    'Stores the visitor’s current traffic source for WooCommerce order attribution.',
                    'viktors-consent-tracking-guard'
                )
            ),
            $this->buildCookieInfo(
                'sbjs_current_add',
                __('6 months', 'viktors-consent-tracking-guard'),
                __(
                    'Stores additional current traffic source details for WooCommerce order attribution.',
                    'viktors-consent-tracking-guard'
                )
            ),
            $this->buildCookieInfo(
                'sbjs_first',
                __('6 months', 'viktors-consent-tracking-guard'),
                __(
                    'Stores the visitor’s first traffic source for WooCommerce order attribution.',
                    'viktors-consent-tracking-guard'
                )
            ),
            $this->buildCookieInfo(
                'sbjs_first_add',
                __('6 months', 'viktors-consent-tracking-guard'),
                __(
                    'Stores additional first traffic source details for WooCommerce order attribution.',
                    'viktors-consent-tracking-guard'
                )
            ),
            $this->buildCookieInfo(
                'sbjs_migrations',
                __('6 months', 'viktors-consent-tracking-guard'),
                __('Tracks Sourcebuster cookie format migrations.', 'viktors-consent-tracking-guard')
            ),
            $this->buildCookieInfo(
                'sbjs_session',
                __('30 minutes', 'viktors-consent-tracking-guard'),
                __('Stores the visitor’s current source attribution session.', 'viktors-consent-tracking-guard')
            ),
            $this->buildCookieInfo(
                'sbjs_udata',
                __('6 months', 'viktors-consent-tracking-guard'),
                __(
                    'Stores visitor user-agent and page attribution details for WooCommerce order reporting.',
                    'viktors-consent-tracking-guard'
                )
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildKlaviyoService(string $lang): array
    {
        return [
            'name' => 'klaviyo',
            'title' => __('Klaviyo', 'viktors-consent-tracking-guard'),
            'purposes' => ['marketing'],
            'default' => false,
            'required' => false,
            'optOut' => false,
            'onlyOnce' => true,
            'cookies' => ['__kla_id', '__kla_off'],
            'wpConsentCookies' => [
                $this->buildCookieInfo(
                    '__kla_id',
                    __('2 years', 'viktors-consent-tracking-guard'),
                    __(
                        'Stores Klaviyo visitor identity for email marketing, attribution, and WooCommerce tracking.',
                        'viktors-consent-tracking-guard'
                    )
                ),
                $this->buildCookieInfo(
                    '__kla_off',
                    __('Session', 'viktors-consent-tracking-guard'),
                    __(
                        'Disables Klaviyo tracking until marketing consent is granted.',
                        'viktors-consent-tracking-guard'
                    )
                ),
            ],
            'translations' => [
                $lang => [
                    'title' => __('Klaviyo', 'viktors-consent-tracking-guard'),
                    'description' => __(
                        'Supports Klaviyo email marketing attribution, forms, and WooCommerce activity tracking.',
                        'viktors-consent-tracking-guard'
                    ),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildWoodMartService(string $lang): array
    {
        return [
            'name' => 'woodmart',
            'title' => __('WoodMart', 'viktors-consent-tracking-guard'),
            'purposes' => ['functional'],
            'default' => true,
            'required' => true,
            'optOut' => false,
            'onlyOnce' => true,
            'cookies' => $this->buildWoodMartCookies(),
            'wpConsentCategory' => 'functional',
            'wpConsentCookies' => $this->buildWoodMartCookieInfo(),
            'translations' => [
                $lang => [
                    'title' => __('WoodMart', 'viktors-consent-tracking-guard'),
                    'description' => __(
                        'Keeps WoodMart shop preferences, wishlist, compare, product history, and popups working.',
                        'viktors-consent-tracking-guard'
                    ),
                ],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function buildWoodMartCookies(): array
    {
        return [
            'woodmart_recently_viewed_products',
            'woodmart_wishlist_hash',
            'woodmart_wishlist_count',
            'woodmart_wishlist_products',
            'wishlist_cleared_time',
            'woodmart_compare_list',
            'shop_per_page',
            'shop_per_row',
            'shop_view',
            'woodmart_age_verify',
            'woodmart_shown_pages',
            '^woodmart_cookies_.*',
            '^woodmart_tb_banner_.*',
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildWoodMartCookieInfo(): array
    {
        return [
            $this->buildCookieInfo(
                'woodmart_recently_viewed_products',
                __('7 days', 'viktors-consent-tracking-guard'),
                __('Stores products recently viewed by the visitor.', 'viktors-consent-tracking-guard')
            ),
            $this->buildCookieInfo(
                'woodmart_wishlist_hash',
                __('Session', 'viktors-consent-tracking-guard'),
                __('Checks whether the visitor’s WoodMart wishlist has changed.', 'viktors-consent-tracking-guard')
            ),
            $this->buildCookieInfo(
                'woodmart_wishlist_count',
                __('Session', 'viktors-consent-tracking-guard'),
                __('Stores the number of products in the visitor’s WoodMart wishlist.', 'viktors-consent-tracking-guard')
            ),
            $this->buildCookieInfo(
                'woodmart_wishlist_products',
                __('Session', 'viktors-consent-tracking-guard'),
                __('Stores products added to the visitor’s WoodMart wishlist.', 'viktors-consent-tracking-guard')
            ),
            $this->buildCookieInfo(
                'woodmart_compare_list',
                __('Session', 'viktors-consent-tracking-guard'),
                __('Stores products added to the visitor’s WoodMart compare list.', 'viktors-consent-tracking-guard')
            ),
            $this->buildCookieInfo(
                'shop_view',
                __('Session', 'viktors-consent-tracking-guard'),
                __('Remembers the visitor’s selected shop list or grid view.', 'viktors-consent-tracking-guard')
            ),
            $this->buildCookieInfo(
                'woodmart_age_verify',
                __('Session', 'viktors-consent-tracking-guard'),
                __('Remembers that the visitor passed the WoodMart age verification prompt.', 'viktors-consent-tracking-guard')
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildWordfenceService(string $lang): array
    {
        return [
            'name' => 'wordfence',
            'title' => __('Wordfence', 'viktors-consent-tracking-guard'),
            'purposes' => ['functional'],
            'default' => true,
            'required' => true,
            'optOut' => false,
            'onlyOnce' => true,
            'cookies' => $this->buildWordfenceCookies(),
            'wpConsentCategory' => 'functional',
            'wpConsentCookies' => $this->buildWordfenceCookieInfo(),
            'translations' => [
                $lang => [
                    'title' => __('Wordfence', 'viktors-consent-tracking-guard'),
                    'description' => __(
                        'Supports the Wordfence firewall, country blocking bypasses, login alerts, and plugin linking.',
                        'viktors-consent-tracking-guard'
                    ),
                ],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function buildWordfenceCookies(): array
    {
        return [
            '^wfwaf-authcookie-.*',
            'wfCBLBypass',
            '^wf_loginalerted_.*',
            'wf-plugin-link-token',
            'wordfence_verifiedHuman',
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildWordfenceCookieInfo(): array
    {
        return [
            $this->buildCookieInfo(
                'wfwaf-authcookie-*',
                __('12 hours', 'viktors-consent-tracking-guard'),
                __('Allows the Wordfence firewall to identify logged-in users and their roles.', 'viktors-consent-tracking-guard')
            ),
            $this->buildCookieInfo(
                'wfCBLBypass',
                __('1 year', 'viktors-consent-tracking-guard'),
                __('Stores a country blocking bypass granted by a hidden access URL.', 'viktors-consent-tracking-guard')
            ),
            $this->buildCookieInfo(
                'wf_loginalerted_*',
                __('1 year', 'viktors-consent-tracking-guard'),
                __('Remembers that a Wordfence new-device login alert has already been sent.', 'viktors-consent-tracking-guard')
            ),
            $this->buildCookieInfo(
                'wf-plugin-link-token',
                __('24 hours', 'viktors-consent-tracking-guard'),
                __('Tracks a Wordfence plugin license or account linking action.', 'viktors-consent-tracking-guard')
            ),
            $this->buildCookieInfo(
                'wordfence_verifiedHuman',
                __('24 hours', 'viktors-consent-tracking-guard'),
                __('Remembers that Wordfence has verified the visitor as human.', 'viktors-consent-tracking-guard')
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildYouTubeService(string $lang): array
    {
        return $this->buildOptionalService(
            'youtube',
            __('YouTube', 'viktors-consent-tracking-guard'),
            'marketing',
            ['VISITOR_INFO1_LIVE', 'VISITOR_PRIVACY_METADATA', 'YSC', 'PREF'],
            $this->buildYouTubeCookieInfo(),
            __('Loads embedded videos provided by YouTube.', 'viktors-consent-tracking-guard'),
            $lang
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildYouTubeCookieInfo(): array
    {
        $domain = 'https://www.youtube.com';

        return [
            $this->buildCookieInfo(
                'VISITOR_INFO1_LIVE',
                __('180 days', 'viktors-consent-tracking-guard'),
                __('Measures bandwidth and player interface selection.', 'viktors-consent-tracking-guard'),
                $domain
            ),
            $this->buildCookieInfo(
                'VISITOR_PRIVACY_METADATA',
                __('180 days', 'viktors-consent-tracking-guard'),
                __('Stores the visitor’s YouTube privacy state.', 'viktors-consent-tracking-guard'),
                $domain
            ),
            $this->buildCookieInfo(
                'YSC',
                __('Session', 'viktors-consent-tracking-guard'),
                __('Maintains YouTube video-view session data.', 'viktors-consent-tracking-guard'),
                $domain
            ),
            $this->buildCookieInfo(
                'PREF',
                __('8 months', 'viktors-consent-tracking-guard'),
                __('Stores YouTube playback and display preferences.', 'viktors-consent-tracking-guard'),
                $domain
            ),
        ];
    }

    /**
     * @param array<int, string> $cookies
     * @param array<int, array<string, string>> $cookieInfo
     * @return array<string, mixed>
     */
    private function buildOptionalService(
        string $name,
        string $title,
        string $purpose,
        array $cookies,
        array $cookieInfo,
        string $description,
        string $lang
    ): array {
        return [
            'name' => $name,
            'title' => $title,
            'purposes' => [$purpose],
            'default' => false,
            'required' => false,
            'optOut' => false,
            'onlyOnce' => true,
            'cookies' => $cookies,
            'wpConsentCookies' => $cookieInfo,
            'translations' => [
                $lang => [
                    'title' => $title,
                    'description' => $description,
                ],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildCookieInfo(
        string $name,
        string $expires,
        string $cookieFunction,
        string $domain = ''
    ): array {
        $cookieInfo = [
            'name' => $name,
            'expires' => $expires,
            'function' => $cookieFunction,
            'type' => 'HTTP',
        ];

        if ($domain !== '') {
            $cookieInfo['domain'] = $domain;
        }

        return $cookieInfo;
    }
}
