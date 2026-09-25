<?php

declare(strict_types=1);

namespace SzepeViktor\ConsentTrackingGuard\Frontend;

use SzepeViktor\ConsentTrackingGuard\Config;
use SzepeViktor\ConsentTrackingGuard\Options;

final class Assets
{
    private const KLAVIYO_SERVICE_NAME = 'klaviyo';
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
        add_action('wp_enqueue_scripts', [$this, 'add_triple_whale_tracking_consent_stub'], 101);
        add_filter('script_loader_tag', [$this, 'filter_bootstrap_tag'], 10, 2);
        add_filter('script_loader_tag', [$this, 'filter_klaviyo_script_loader_tag'], 100, 3);
    }

    public function enqueue(): void
    {
        $options = $this->options->all();
        $modalStyle = (string) $options['modal_style'];
        $klaroConfig = $this->build_klaro_config();

        $this->enqueueStyles($modalStyle);
        $this->enqueueScripts($klaroConfig);
    }

    private function enqueueStyles(string $modalStyle): void
    {
        wp_enqueue_style(
            'consent-tracking-guard-for-wordpress-klaro',
            plugins_url('assets/css/klaro.css', Config::get('filePath')),
            [],
            Config::get('version')
        );

        wp_enqueue_style(
            'consent-tracking-guard-for-wordpress-components',
            plugins_url('assets/css/components.css', Config::get('filePath')),
            ['consent-tracking-guard-for-wordpress-klaro'],
            Config::get('version')
        );

        if (isset(self::MODAL_STYLE_STYLESHEETS[$modalStyle])) { // phpcs:ignore SlevomatCodingStandard.ControlStructures.EarlyExit.EarlyExitNotUsed -- Conditional enqueue reads clearer here.
            wp_enqueue_style(
                'consent-tracking-guard-for-wordpress-modal-style',
                plugins_url(
                    sprintf(
                        'assets/css/modal-styles/%s',
                        self::MODAL_STYLE_STYLESHEETS[$modalStyle]
                    ),
                    Config::get('filePath')
                ),
                ['consent-tracking-guard-for-wordpress-components'],
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
            'consent-tracking-guard-for-wordpress-bootstrap',
            plugins_url('assets/js/cmp-bootstrap.js', Config::get('filePath')),
            [],
            Config::get('version'),
            false
        );

        wp_enqueue_script(
            'consent-tracking-guard-for-wordpress-klaro',
            plugins_url('assets/js/klaro.js', Config::get('filePath')),
            [],
            Config::get('version'),
            false
        );

        wp_script_add_data('consent-tracking-guard-for-wordpress-klaro', 'defer', true);

        wp_add_inline_script(
            'consent-tracking-guard-for-wordpress-klaro',
            sprintf('window.klaroConfig = %s;', wp_json_encode($klaroConfig)),
            'before'
        );

        if (! $this->consent_api_bridge->is_api_available()) {
            return;
        }

        wp_enqueue_script(
            'consent-tracking-guard-for-wordpress-consent-api-bridge',
            plugins_url('assets/js/wp-consent-api-bridge.js', Config::get('filePath')),
            ['consent-tracking-guard-for-wordpress-klaro', 'wp-consent-api'],
            Config::get('version'),
            true
        );
    }

    public function add_triple_whale_tracking_consent_stub(): void
    {
        $options = $this->options->all();

        if (! (bool) $options['enable_triple_whale']) {
            return;
        }

        wp_add_inline_script(
            'triplewhale-pixel-snippet',
            <<<'JS'
(function () {
    if (typeof window.TriplePixel !== 'function') {
        window.TriplePixel = function () {
            (window.TriplePixel.q = window.TriplePixel.q || []).push(arguments);
        };
    }

    window.TriplePixel('trackingConsent', false);
}());
JS,
            'before'
        );
    }

    public function filter_klaviyo_script_loader_tag(string $tag, string $handle, string $src): string
    {
        $options = $this->options->all();

        if (! (bool) $options['enable_klaviyo']) {
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
        $options = $this->options->all();

        if ($handle !== 'consent-tracking-guard-for-wordpress-bootstrap') {
            return $tag;
        }

        if ($options['gtm_id'] !== '') {
            $attributes['data-gtm-id'] = (string) $options['gtm_id'];
        }

        if ($options['clarity_project_id'] !== '') {
            $attributes['data-clarity-project-id'] = (string) $options['clarity_project_id'];
        }

        if ($options['hotjar_id'] !== '') {
            $attributes['data-hotjar-id'] = (string) $options['hotjar_id'];
            $attributes['data-hotjar-version'] = (string) $options['hotjar_version'];
        }

        if ($options['meta_pixel_id'] !== '' && ! (bool) $options['enable_facebook_for_woocommerce']) {
            $attributes['data-meta-pixel-id'] = (string) $options['meta_pixel_id'];
        }

        if ($options['linkedin_partner_id'] !== '') {
            $attributes['data-linkedin-partner-id'] = (string) $options['linkedin_partner_id'];
        }

        if ((bool) $options['enable_triple_whale']) {
            $attributes['data-triple-whale-service'] = self::TRIPLE_WHALE_SERVICE_NAME;
        }

        if ((bool) $options['enable_klaviyo']) {
            $attributes['data-klaviyo'] = 'true';
        }

        if ((bool) $options['enable_facebook_for_woocommerce']) {
            $attributes['data-facebook-for-woocommerce-service'] = 'facebook-for-woocommerce';
        }

        if ((bool) $options['enable_youtube']) {
            $attributes['data-youtube-service'] = 'youtube';
        }

        if ((bool) $options['enable_floating']) {
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
        $options = $this->options->all();
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
                $lang => $this->buildTranslations($options),
            ],
            'services' => $this->build_services($lang),
        ];

        if ($privacyPolicyUrl !== '') {
            $config['privacyPolicyUrl'] = $privacyPolicyUrl;
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function buildTranslations(array $options): array
    {
        return [
            'consentNotice' => [
                'title' => (string) $options['notice_title'],
                'description' => $this->replacePrivacyPolicyShortcode((string) $options['notice_description']),
                'changeDescription' => __(
                    'There were changes since your last visit, please renew your consent.',
                    'consent-tracking-guard-for-wordpress'
                ),
                'learnMore' => __('Learn more', 'consent-tracking-guard-for-wordpress'),
                'testing' => __('Testing mode!', 'consent-tracking-guard-for-wordpress'),
            ],
            'consentModal' => [
                'title' => (string) $options['modal_title'],
                'description' => $this->replacePrivacyPolicyShortcode((string) $options['modal_description']),
            ],
            'contextualConsent' => [
                'acceptAlways' => __('Always', 'consent-tracking-guard-for-wordpress'),
                'acceptOnce' => __('Yes', 'consent-tracking-guard-for-wordpress'),
                'description' => __(
                    'Do you want to load external content supplied by {title}?',
                    'consent-tracking-guard-for-wordpress'
                ),
                'descriptionEmptyStore' => __(
                    'To agree to this service permanently, you must accept {title} in the {link}.',
                    'consent-tracking-guard-for-wordpress'
                ),
                'modalLinkText' => __('Consent Manager', 'consent-tracking-guard-for-wordpress'),
            ],
            'purposes' => $this->buildPurposeTranslations(),
            'purposeItem' => [
                'service' => __('service', 'consent-tracking-guard-for-wordpress'),
                'services' => __('services', 'consent-tracking-guard-for-wordpress'),
            ],
            'ok' => __('OK', 'consent-tracking-guard-for-wordpress'),
            'save' => __('Save', 'consent-tracking-guard-for-wordpress'),
            'acceptAll' => __('Accept all', 'consent-tracking-guard-for-wordpress'),
            'acceptSelected' => __('Accept selected', 'consent-tracking-guard-for-wordpress'),
            'declineAll' => __('Decline all', 'consent-tracking-guard-for-wordpress'),
            'decline' => __('Decline', 'consent-tracking-guard-for-wordpress'),
            'close' => __('Close', 'consent-tracking-guard-for-wordpress'),
            'poweredBy' => __('Realized with Klaro!', 'consent-tracking-guard-for-wordpress'),
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
            'functional' => __('Functional', 'consent-tracking-guard-for-wordpress'),
            'preferences' => __('Preferences', 'consent-tracking-guard-for-wordpress'),
            'statistics-anonymous' => __('Anonymous statistics', 'consent-tracking-guard-for-wordpress'),
            'statistics' => __('Statistics', 'consent-tracking-guard-for-wordpress'),
            'marketing' => __('Marketing', 'consent-tracking-guard-for-wordpress'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildServiceTranslations(): array
    {
        return [
            'disableAll' => [
                'title' => __('Enable or disable all services', 'consent-tracking-guard-for-wordpress'),
                'description' => __(
                    'Use this switch to change all optional services at once.',
                    'consent-tracking-guard-for-wordpress'
                ),
            ],
            'optOut' => [
                'title' => __('(opt-out)', 'consent-tracking-guard-for-wordpress'),
                'description' => __(
                    'This service loads by default, but can be disabled later.',
                    'consent-tracking-guard-for-wordpress'
                ),
            ],
            'required' => [
                'title' => __('(required)', 'consent-tracking-guard-for-wordpress'),
                'description' => __(
                    'This service is required for the site to function.',
                    'consent-tracking-guard-for-wordpress'
                ),
            ],
            'purposes' => __('Purposes', 'consent-tracking-guard-for-wordpress'),
            'purpose' => __('Purpose', 'consent-tracking-guard-for-wordpress'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function build_services(string $lang): array
    {
        $options = $this->options->all();
        $services = $this->buildCoreServices($lang);

        if ($options['gtm_id'] !== '') {
            $services[] = $this->buildGoogleTagManagerService($lang);
        }

        if ($options['clarity_project_id'] !== '') {
            $services[] = $this->buildMicrosoftClarityService($lang);
        }

        if ($options['hotjar_id'] !== '') {
            $services[] = $this->buildHotjarService($lang);
        }

        if ($options['meta_pixel_id'] !== '' && ! (bool) $options['enable_facebook_for_woocommerce']) {
            $services[] = $this->buildMetaPixelService($lang);
        }

        if ($options['linkedin_partner_id'] !== '') {
            $services[] = $this->buildLinkedInService($lang);
        }

        if ((bool) $options['enable_triple_whale']) {
            $services[] = $this->buildTripleWhaleService($lang);
        }

        if ((bool) $options['enable_polylang']) {
            $polylangService = $this->buildPolylangService($lang);

            if ($polylangService !== null) {
                $services[] = $polylangService;
            }
        }

        if ((bool) $options['enable_woocommerce']) {
            $services[] = $this->buildWooCommerceFunctionalService($lang);
            $services[] = $this->buildWooCommerceAttributionService($lang);
        }

        if ((bool) $options['enable_facebook_for_woocommerce']) {
            $services[] = $this->buildFacebookForWooCommerceService($lang);
        }

        if ((bool) $options['enable_klaviyo']) {
            $services[] = $this->buildKlaviyoService($lang);
        }

        if ((bool) $options['enable_woodmart']) {
            $services[] = $this->buildWoodMartService($lang);
        }

        if ((bool) $options['enable_wordfence']) {
            $services[] = $this->buildWordfenceService($lang);
        }

        if ((bool) $options['enable_youtube']) {
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
            'title' => __('Cookie consent settings', 'consent-tracking-guard-for-wordpress'),
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
                    __('365 days', 'consent-tracking-guard-for-wordpress'),
                    __('Stores the visitor’s consent choices.', 'consent-tracking-guard-for-wordpress')
                ),
            ],
            'translations' => [
                $lang => [
                    'title' => __('Cookie consent settings', 'consent-tracking-guard-for-wordpress'),
                    'description' => __('Stores the visitor’s consent choice.', 'consent-tracking-guard-for-wordpress'),
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
            __('Google Tag Manager', 'consent-tracking-guard-for-wordpress'),
            'statistics',
            ['_ga', '^_ga_.*', '_gid', '^_gat.*'],
            [
                $this->buildCookieInfo(
                    '_ga',
                    __('2 years', 'consent-tracking-guard-for-wordpress'),
                    __('Distinguishes visitors for analytics reporting.', 'consent-tracking-guard-for-wordpress')
                ),
                $this->buildCookieInfo(
                    '_gid',
                    __('24 hours', 'consent-tracking-guard-for-wordpress'),
                    __('Distinguishes visitors for daily analytics reporting.', 'consent-tracking-guard-for-wordpress')
                ),
            ],
            __('Loads analytics tags managed through Google Tag Manager.', 'consent-tracking-guard-for-wordpress'),
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
            __('Microsoft Clarity', 'consent-tracking-guard-for-wordpress'),
            'statistics',
            ['_clck', '_clsk'],
            [
                $this->buildCookieInfo(
                    '_clck',
                    __('1 year', 'consent-tracking-guard-for-wordpress'),
                    __('Persists the Clarity visitor identifier and preferences.', 'consent-tracking-guard-for-wordpress')
                ),
                $this->buildCookieInfo(
                    '_clsk',
                    __('1 day', 'consent-tracking-guard-for-wordpress'),
                    __('Groups Clarity page views into a recording session.', 'consent-tracking-guard-for-wordpress')
                ),
            ],
            __('Measures how visitors use the site through session analytics.', 'consent-tracking-guard-for-wordpress'),
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
            __('Hotjar', 'consent-tracking-guard-for-wordpress'),
            'statistics',
            $this->buildHotjarCookies(),
            $this->buildHotjarCookieInfo(),
            __('Measures visitor behavior and collects usability feedback.', 'consent-tracking-guard-for-wordpress'),
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
                __('1 year', 'consent-tracking-guard-for-wordpress'),
                __('Prevents a dismissed Hotjar survey invitation from reappearing.', 'consent-tracking-guard-for-wordpress')
            ),
            $this->buildCookieInfo(
                '_hjDonePolls',
                __('1 year', 'consent-tracking-guard-for-wordpress'),
                __('Prevents a completed Hotjar poll from reappearing.', 'consent-tracking-guard-for-wordpress')
            ),
            $this->buildCookieInfo(
                '_hjMinimizedPolls',
                __('1 year', 'consent-tracking-guard-for-wordpress'),
                __('Keeps a minimized Hotjar poll minimized.', 'consent-tracking-guard-for-wordpress')
            ),
            $this->buildCookieInfo(
                '_hjShownFeedbackMessage',
                __('1 day', 'consent-tracking-guard-for-wordpress'),
                __('Prevents repeated display of Hotjar feedback messaging.', 'consent-tracking-guard-for-wordpress')
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
            __('Meta Pixel', 'consent-tracking-guard-for-wordpress'),
            'marketing',
            ['_fbp', '_fbc'],
            [
                $this->buildCookieInfo(
                    '_fbp',
                    __('90 days', 'consent-tracking-guard-for-wordpress'),
                    __(
                        'Identifies browsers for Meta advertising measurement.',
                        'consent-tracking-guard-for-wordpress'
                    )
                ),
                $this->buildCookieInfo(
                    '_fbc',
                    __('90 days', 'consent-tracking-guard-for-wordpress'),
                    __(
                        'Stores the Meta advertising click identifier.',
                        'consent-tracking-guard-for-wordpress'
                    )
                ),
            ],
            __('Measures advertising performance and visitor actions for Meta.', 'consent-tracking-guard-for-wordpress'),
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
            __('Meta for WooCommerce', 'consent-tracking-guard-for-wordpress'),
            'marketing',
            ['_fbp', '_fbc', 'wc_facebook_signals_state'],
            [
                $this->buildCookieInfo(
                    '_fbp',
                    __('90 days', 'consent-tracking-guard-for-wordpress'),
                    __(
                        'Identifies browsers for Meta advertising measurement.',
                        'consent-tracking-guard-for-wordpress'
                    )
                ),
                $this->buildCookieInfo(
                    '_fbc',
                    __('90 days', 'consent-tracking-guard-for-wordpress'),
                    __(
                        'Stores the Meta advertising click identifier.',
                        'consent-tracking-guard-for-wordpress'
                    )
                ),
                $this->buildCookieInfo(
                    'wc_facebook_signals_state',
                    __('Session', 'consent-tracking-guard-for-wordpress'),
                    __(
                        'Stores whether Meta for WooCommerce browser signals are held or released.',
                        'consent-tracking-guard-for-wordpress'
                    )
                ),
            ],
            __(
                'Measures WooCommerce product views, cart actions, and purchases for Meta advertising.',
                'consent-tracking-guard-for-wordpress'
            ),
            $lang
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLinkedInService(string $lang): array
    {
        return $this->buildOptionalService(
            'linkedin-insight-tag',
            __('LinkedIn Insight Tag', 'consent-tracking-guard-for-wordpress'),
            'marketing',
            ['li_fat_id', 'li_giant'],
            [
                $this->buildCookieInfo(
                    'li_fat_id',
                    __('30 days', 'consent-tracking-guard-for-wordpress'),
                    __('Stores the LinkedIn advertising click identifier.', 'consent-tracking-guard-for-wordpress')
                ),
                $this->buildCookieInfo(
                    'li_giant',
                    __('7 days', 'consent-tracking-guard-for-wordpress'),
                    __('Supports LinkedIn conversion attribution.', 'consent-tracking-guard-for-wordpress')
                ),
            ],
            __('Measures LinkedIn campaign performance and website conversions.', 'consent-tracking-guard-for-wordpress'),
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
            __('Triple Whale Pixel', 'consent-tracking-guard-for-wordpress'),
            'marketing',
            ['TriplePixel', 'TriplePixelU', 'di_pmt_wt', 'configSecurityConfModel'],
            [
                $this->buildCookieInfo(
                    'TriplePixel',
                    __('Persistent', 'consent-tracking-guard-for-wordpress'),
                    __('Stores Triple Whale pixel runtime and visit count data.', 'consent-tracking-guard-for-wordpress')
                ),
                $this->buildCookieInfo(
                    'TriplePixelU',
                    __('Persistent', 'consent-tracking-guard-for-wordpress'),
                    __('Stores recent page visit details for Triple Whale attribution.', 'consent-tracking-guard-for-wordpress')
                ),
                $this->buildCookieInfo(
                    'di_pmt_wt',
                    __('Persistent', 'consent-tracking-guard-for-wordpress'),
                    __('Stores the Triple Whale browser profile identifier.', 'consent-tracking-guard-for-wordpress')
                ),
            ],
            __('Measures visitor journeys, advertising attribution, and ecommerce events for Triple Whale.', 'consent-tracking-guard-for-wordpress'),
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
            'title' => __('Polylang', 'consent-tracking-guard-for-wordpress'),
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
                    __('1 year', 'consent-tracking-guard-for-wordpress'),
                    __(
                        'Stores the visitor’s last browsed language for Polylang and Polylang for WooCommerce.',
                        'consent-tracking-guard-for-wordpress'
                    )
                ),
            ],
            'translations' => [
                $lang => [
                    'title' => __('Polylang', 'consent-tracking-guard-for-wordpress'),
                    'description' => __(
                        'Remembers the selected language for multilingual content and translated WooCommerce flows.',
                        'consent-tracking-guard-for-wordpress'
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
            'title' => __('WooCommerce', 'consent-tracking-guard-for-wordpress'),
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
                    'title' => __('WooCommerce', 'consent-tracking-guard-for-wordpress'),
                    'description' => __(
                        'Keeps the shopping cart, checkout, customer session, and store notices working.',
                        'consent-tracking-guard-for-wordpress'
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
                __('Session', 'consent-tracking-guard-for-wordpress'),
                __('Helps WooCommerce detect cart changes.', 'consent-tracking-guard-for-wordpress')
            ),
            $this->buildCookieInfo(
                'woocommerce_items_in_cart',
                __('Session', 'consent-tracking-guard-for-wordpress'),
                __('Helps WooCommerce keep cart data synchronized.', 'consent-tracking-guard-for-wordpress')
            ),
            $this->buildCookieInfo(
                'wp_woocommerce_session_*',
                __('2 days', 'consent-tracking-guard-for-wordpress'),
                __('Stores a unique customer session identifier for cart and checkout data.', 'consent-tracking-guard-for-wordpress')
            ),
            $this->buildCookieInfo(
                'woocommerce_recently_viewed',
                __('Session', 'consent-tracking-guard-for-wordpress'),
                __('Stores products viewed by the visitor.', 'consent-tracking-guard-for-wordpress')
            ),
            $this->buildCookieInfo(
                'store_notice*',
                __('Session', 'consent-tracking-guard-for-wordpress'),
                __('Remembers dismissed WooCommerce store notices.', 'consent-tracking-guard-for-wordpress')
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
            __('WooCommerce source attribution', 'consent-tracking-guard-for-wordpress'),
            'statistics',
            $this->buildWooCommerceAttributionCookies(),
            $this->buildWooCommerceAttributionCookieInfo(),
            __(
                'Stores first-party source attribution data for WooCommerce order reporting.',
                'consent-tracking-guard-for-wordpress'
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
                __('6 months', 'consent-tracking-guard-for-wordpress'),
                __(
                    'Stores the visitor’s current traffic source for WooCommerce order attribution.',
                    'consent-tracking-guard-for-wordpress'
                )
            ),
            $this->buildCookieInfo(
                'sbjs_current_add',
                __('6 months', 'consent-tracking-guard-for-wordpress'),
                __(
                    'Stores additional current traffic source details for WooCommerce order attribution.',
                    'consent-tracking-guard-for-wordpress'
                )
            ),
            $this->buildCookieInfo(
                'sbjs_first',
                __('6 months', 'consent-tracking-guard-for-wordpress'),
                __(
                    'Stores the visitor’s first traffic source for WooCommerce order attribution.',
                    'consent-tracking-guard-for-wordpress'
                )
            ),
            $this->buildCookieInfo(
                'sbjs_first_add',
                __('6 months', 'consent-tracking-guard-for-wordpress'),
                __(
                    'Stores additional first traffic source details for WooCommerce order attribution.',
                    'consent-tracking-guard-for-wordpress'
                )
            ),
            $this->buildCookieInfo(
                'sbjs_migrations',
                __('6 months', 'consent-tracking-guard-for-wordpress'),
                __('Tracks Sourcebuster cookie format migrations.', 'consent-tracking-guard-for-wordpress')
            ),
            $this->buildCookieInfo(
                'sbjs_session',
                __('30 minutes', 'consent-tracking-guard-for-wordpress'),
                __('Stores the visitor’s current source attribution session.', 'consent-tracking-guard-for-wordpress')
            ),
            $this->buildCookieInfo(
                'sbjs_udata',
                __('6 months', 'consent-tracking-guard-for-wordpress'),
                __(
                    'Stores visitor user-agent and page attribution details for WooCommerce order reporting.',
                    'consent-tracking-guard-for-wordpress'
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
            'title' => __('Klaviyo', 'consent-tracking-guard-for-wordpress'),
            'purposes' => ['marketing'],
            'default' => false,
            'required' => false,
            'optOut' => false,
            'onlyOnce' => true,
            'cookies' => ['__kla_id', '__kla_off'],
            'wpConsentCookies' => [
                $this->buildCookieInfo(
                    '__kla_id',
                    __('2 years', 'consent-tracking-guard-for-wordpress'),
                    __(
                        'Stores Klaviyo visitor identity for email marketing, attribution, and WooCommerce tracking.',
                        'consent-tracking-guard-for-wordpress'
                    )
                ),
                $this->buildCookieInfo(
                    '__kla_off',
                    __('Session', 'consent-tracking-guard-for-wordpress'),
                    __(
                        'Disables Klaviyo tracking until marketing consent is granted.',
                        'consent-tracking-guard-for-wordpress'
                    )
                ),
            ],
            'translations' => [
                $lang => [
                    'title' => __('Klaviyo', 'consent-tracking-guard-for-wordpress'),
                    'description' => __(
                        'Supports Klaviyo email marketing attribution, forms, and WooCommerce activity tracking.',
                        'consent-tracking-guard-for-wordpress'
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
            'title' => __('WoodMart', 'consent-tracking-guard-for-wordpress'),
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
                    'title' => __('WoodMart', 'consent-tracking-guard-for-wordpress'),
                    'description' => __(
                        'Keeps WoodMart shop preferences, wishlist, compare, product history, and popups working.',
                        'consent-tracking-guard-for-wordpress'
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
                __('7 days', 'consent-tracking-guard-for-wordpress'),
                __('Stores products recently viewed by the visitor.', 'consent-tracking-guard-for-wordpress')
            ),
            $this->buildCookieInfo(
                'woodmart_wishlist_hash',
                __('Session', 'consent-tracking-guard-for-wordpress'),
                __('Checks whether the visitor’s WoodMart wishlist has changed.', 'consent-tracking-guard-for-wordpress')
            ),
            $this->buildCookieInfo(
                'woodmart_wishlist_count',
                __('Session', 'consent-tracking-guard-for-wordpress'),
                __('Stores the number of products in the visitor’s WoodMart wishlist.', 'consent-tracking-guard-for-wordpress')
            ),
            $this->buildCookieInfo(
                'woodmart_wishlist_products',
                __('Session', 'consent-tracking-guard-for-wordpress'),
                __('Stores products added to the visitor’s WoodMart wishlist.', 'consent-tracking-guard-for-wordpress')
            ),
            $this->buildCookieInfo(
                'woodmart_compare_list',
                __('Session', 'consent-tracking-guard-for-wordpress'),
                __('Stores products added to the visitor’s WoodMart compare list.', 'consent-tracking-guard-for-wordpress')
            ),
            $this->buildCookieInfo(
                'shop_view',
                __('Session', 'consent-tracking-guard-for-wordpress'),
                __('Remembers the visitor’s selected shop list or grid view.', 'consent-tracking-guard-for-wordpress')
            ),
            $this->buildCookieInfo(
                'woodmart_age_verify',
                __('Session', 'consent-tracking-guard-for-wordpress'),
                __('Remembers that the visitor passed the WoodMart age verification prompt.', 'consent-tracking-guard-for-wordpress')
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
            'title' => __('Wordfence', 'consent-tracking-guard-for-wordpress'),
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
                    'title' => __('Wordfence', 'consent-tracking-guard-for-wordpress'),
                    'description' => __(
                        'Supports the Wordfence firewall, country blocking bypasses, login alerts, and plugin linking.',
                        'consent-tracking-guard-for-wordpress'
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
                __('12 hours', 'consent-tracking-guard-for-wordpress'),
                __('Allows the Wordfence firewall to identify logged-in users and their roles.', 'consent-tracking-guard-for-wordpress')
            ),
            $this->buildCookieInfo(
                'wfCBLBypass',
                __('1 year', 'consent-tracking-guard-for-wordpress'),
                __('Stores a country blocking bypass granted by a hidden access URL.', 'consent-tracking-guard-for-wordpress')
            ),
            $this->buildCookieInfo(
                'wf_loginalerted_*',
                __('1 year', 'consent-tracking-guard-for-wordpress'),
                __('Remembers that a Wordfence new-device login alert has already been sent.', 'consent-tracking-guard-for-wordpress')
            ),
            $this->buildCookieInfo(
                'wf-plugin-link-token',
                __('24 hours', 'consent-tracking-guard-for-wordpress'),
                __('Tracks a Wordfence plugin license or account linking action.', 'consent-tracking-guard-for-wordpress')
            ),
            $this->buildCookieInfo(
                'wordfence_verifiedHuman',
                __('24 hours', 'consent-tracking-guard-for-wordpress'),
                __('Remembers that Wordfence has verified the visitor as human.', 'consent-tracking-guard-for-wordpress')
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
            __('YouTube', 'consent-tracking-guard-for-wordpress'),
            'marketing',
            ['VISITOR_INFO1_LIVE', 'VISITOR_PRIVACY_METADATA', 'YSC', 'PREF'],
            $this->buildYouTubeCookieInfo(),
            __('Loads embedded videos provided by YouTube.', 'consent-tracking-guard-for-wordpress'),
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
                __('180 days', 'consent-tracking-guard-for-wordpress'),
                __('Measures bandwidth and player interface selection.', 'consent-tracking-guard-for-wordpress'),
                $domain
            ),
            $this->buildCookieInfo(
                'VISITOR_PRIVACY_METADATA',
                __('180 days', 'consent-tracking-guard-for-wordpress'),
                __('Stores the visitor’s YouTube privacy state.', 'consent-tracking-guard-for-wordpress'),
                $domain
            ),
            $this->buildCookieInfo(
                'YSC',
                __('Session', 'consent-tracking-guard-for-wordpress'),
                __('Maintains YouTube video-view session data.', 'consent-tracking-guard-for-wordpress'),
                $domain
            ),
            $this->buildCookieInfo(
                'PREF',
                __('8 months', 'consent-tracking-guard-for-wordpress'),
                __('Stores YouTube playback and display preferences.', 'consent-tracking-guard-for-wordpress'),
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
