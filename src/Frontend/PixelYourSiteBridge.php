<?php

/**
 * PixelYourSiteBridge.php
 *
 * @author Viktor Szépe <viktor@szepe.net>
 * @license GNU General Public License v2 or later
 * @link https://github.com/szepeviktor/consent-tracking-guard-for-wordpress
 */

declare(strict_types=1);

namespace SzepeViktor\ConsentTrackingGuard\Frontend;

use SzepeViktor\ConsentTrackingGuard\Options;

/**
 * Consent bridge for PixelYourSite's documented GDPR filters.
 */
final class PixelYourSiteBridge
{
    private const CATEGORY_STATISTICS = 'statistics';

    private const CATEGORY_MARKETING = 'marketing';

    private const MARKETING_PLATFORM_SLUGS = [
        'facebook' => true,
        'google_ads' => true,
        'tiktok' => true,
        'pinterest' => true,
        'bing' => true,
        'reddit' => true,
    ];

    private Options $options;

    public function __construct(Options $options)
    {
        $this->options = $options;
    }

    public function register(): void
    {
        add_filter('pys_disable_by_gdpr', [$this, 'filterDisableAll']);
        add_filter('pys_disable_facebook_by_gdpr', [$this, 'filterDisableMarketing']);
        add_filter('pys_disable_tiktok_by_gdpr', [$this, 'filterDisableMarketing']);
        add_filter('pys_disable_google_ads_by_gdpr', [$this, 'filterDisableMarketing']);
        add_filter('pys_disable_pinterest_by_gdpr', [$this, 'filterDisableMarketing']);
        add_filter('pys_disable_bing_by_gdpr', [$this, 'filterDisableMarketing']);
        add_filter('pys_disable_reddit_by_gdpr', [$this, 'filterDisableMarketing']);
        add_filter('pys_disable_analytics_by_gdpr', [$this, 'filterDisableAnalytics']);
        add_filter('pys_check_consent_by_gdpr', [$this, 'filterPixelConsent'], 10, 2);
        add_filter('pys_disable_server_event_filter', [$this, 'filterServerEventDisabled'], 10, 4);

        add_filter('pys_disable_all_cookie', [$this, 'filterDisableAllCookies']);
        add_filter('pys_disabled_start_session_cookie', [$this, 'filterDisableAnalyticsOrMarketingCookie']);
        add_filter('pys_disable_first_visit_cookie', [$this, 'filterDisableAnalyticsOrMarketingCookie']);
        add_filter('pys_disable_landing_page_cookie', [$this, 'filterDisableAnalyticsOrMarketingCookie']);
        add_filter('pys_disable_trafficsource_cookie', [$this, 'filterDisableAnalyticsOrMarketingCookie']);
        add_filter('pys_disable_utmTerms_cookie', [$this, 'filterDisableAnalyticsOrMarketingCookie']);
        add_filter('pys_disable_utmId_cookie', [$this, 'filterDisableMarketing']);
        add_filter('pys_disable_advance_data_cookie', [$this, 'filterDisableMarketing']);
        add_filter('pys_disable_advanced_form_data_cookie', [$this, 'filterDisableMarketing']);
        add_filter('pys_disable_externalID_by_gdpr', [$this, 'filterDisableMarketing']);
        add_filter('pys_disable_google_alternative_id', [$this, 'filterDisableMarketing']);

        add_filter('pys_analytics_storage_mode', [$this, 'filterAnalyticsConsentMode']);
        add_filter('pys_ad_storage_mode', [$this, 'filterMarketingConsentMode']);
        add_filter('pys_ad_user_data_mode', [$this, 'filterMarketingConsentMode']);
        add_filter('pys_ad_personalization_mode', [$this, 'filterMarketingConsentMode']);
        add_filter('pys_bing_ad_storage_mode', [$this, 'filterMarketingConsentMode']);
    }

    /**
     * @param mixed $disabled
     */
    public function filterDisableAll($disabled): bool
    {
        if (! $this->isEnabled()) {
            return (bool) $disabled;
        }

        if ($disabled) {
            return true;
        }

        return ! $this->hasAnyTrackingConsent();
    }

    /**
     * @param mixed $disabled
     */
    public function filterDisableMarketing($disabled): bool
    {
        return $this->filterDisabledByConsent($disabled, self::CATEGORY_MARKETING);
    }

    /**
     * @param mixed $disabled
     */
    public function filterDisableAnalytics($disabled): bool
    {
        return $this->filterDisabledByConsent($disabled, self::CATEGORY_STATISTICS);
    }

    /**
     * @param mixed $status
     * @param mixed $pixel
     */
    public function filterPixelConsent($status, $pixel = ''): bool
    {
        if (! $this->isEnabled()) {
            return (bool) $status;
        }

        if (! $status) {
            return false;
        }

        return $this->hasConsent($this->categoryForPixel((string) $pixel));
    }

    /**
     * @param mixed $disabled
     * @param mixed $eventName
     * @param mixed $tagSlug
     * @param mixed $orderId
     */
    public function filterServerEventDisabled($disabled, $eventName = '', $tagSlug = '', $orderId = null): bool
    {
        unset($eventName, $orderId);

        if (! $this->isEnabled()) {
            return (bool) $disabled;
        }

        if ($disabled) {
            return true;
        }

        return ! $this->hasConsent($this->categoryForPixel((string) $tagSlug));
    }

    /**
     * @param mixed $disabled
     */
    public function filterDisableAllCookies($disabled): bool
    {
        if (! $this->isEnabled()) {
            return (bool) $disabled;
        }

        return (bool) $disabled || ! $this->hasAnyTrackingConsent();
    }

    /**
     * @param mixed $disabled
     */
    public function filterDisableAnalyticsOrMarketingCookie($disabled): bool
    {
        if (! $this->isEnabled()) {
            return (bool) $disabled;
        }

        return (bool) $disabled || ! $this->hasAnyTrackingConsent();
    }

    /**
     * @param mixed $mode
     */
    public function filterAnalyticsConsentMode($mode): bool
    {
        return $this->filterConsentMode($mode, self::CATEGORY_STATISTICS);
    }

    /**
     * @param mixed $mode
     */
    public function filterMarketingConsentMode($mode): bool
    {
        return $this->filterConsentMode($mode, self::CATEGORY_MARKETING);
    }

    /**
     * @param mixed $disabled
     */
    private function filterDisabledByConsent($disabled, string $category): bool
    {
        if (! $this->isEnabled()) {
            return (bool) $disabled;
        }

        return (bool) $disabled || ! $this->hasConsent($category);
    }

    /**
     * @param mixed $mode
     */
    private function filterConsentMode($mode, string $category): bool
    {
        if (! $this->isEnabled()) {
            return (bool) $mode;
        }

        return $this->hasConsent($category);
    }

    private function hasAnyTrackingConsent(): bool
    {
        return $this->hasConsent(self::CATEGORY_STATISTICS)
            || $this->hasConsent(self::CATEGORY_MARKETING);
    }

    private function hasConsent(string $category): bool
    {
        if (function_exists('wp_has_consent') && wp_has_consent($category)) {
            return true;
        }

        return $this->hasConsentCookie($category);
    }

    private function hasConsentCookie(string $category): bool
    {
        $cookieName = sprintf('wp_consent_%s', $category);

        return isset($_COOKIE[$cookieName])
            && is_string($_COOKIE[$cookieName])
            && strtolower(sanitize_text_field(wp_unslash($_COOKIE[$cookieName]))) === 'allow';
    }

    private function categoryForPixel(string $pixel): string
    {
        if ($pixel === 'ga' || $pixel === 'ga4' || $pixel === 'gtm' || $pixel === 'analytics') {
            return self::CATEGORY_STATISTICS;
        }

        if (isset(self::MARKETING_PLATFORM_SLUGS[$pixel])) {
            return self::CATEGORY_MARKETING;
        }

        return self::CATEGORY_MARKETING;
    }

    private function isEnabled(): bool
    {
        $options = $this->options->all();

        return (bool) $options['enable_pixelyoursite'];
    }
}
