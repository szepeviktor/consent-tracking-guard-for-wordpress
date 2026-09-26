<?php

/**
 * FacebookForWooCommerceBridge.php
 *
 * @author Viktor Szépe <viktor@szepe.net>
 * @license GNU General Public License v2 or later
 * @link https://github.com/szepeviktor/consent-tracking-guard-for-wordpress
 */

declare(strict_types=1);

namespace SzepeViktor\ConsentTrackingGuard\Frontend;

use SzepeViktor\ConsentTrackingGuard\Options;

/**
 * Consent bridge for Meta for WooCommerce browser signals.
 */
final class FacebookForWooCommerceBridge
{
    private const KLARO_SERVICE_NAME = 'facebook-for-woocommerce';

    private Options $options;

    public function __construct(Options $options)
    {
        $this->options = $options;
    }

    public function register(): void
    {
        // Meta for WooCommerce is not natively WP Consent API-compatible.
        add_filter('facebook_signals_held', [$this, 'filterSignalsHeld']);
    }

    /**
     * @param mixed $held
     */
    public function filterSignalsHeld($held): bool
    {
        if (! $this->options->enabled('enable_facebook_for_woocommerce')) {
            return (bool) $held;
        }

        if ($this->hasMarketingConsent()) {
            return (bool) $held;
        }

        return true;
    }

    private function hasMarketingConsent(): bool
    {
        if (function_exists('wp_has_consent') && wp_has_consent('marketing')) {
            return true;
        }

        if ($this->hasKlaroServiceConsent(self::KLARO_SERVICE_NAME)) {
            return true;
        }

        return isset($_COOKIE['wp_consent_marketing'])
            && is_string($_COOKIE['wp_consent_marketing'])
            && strtolower(sanitize_text_field(wp_unslash($_COOKIE['wp_consent_marketing']))) === 'allow';
    }

    private function hasKlaroServiceConsent(string $serviceName): bool
    {
        if (! isset($_COOKIE['klaro']) || ! is_string($_COOKIE['klaro'])) {
            return false;
        }

        // The Klaro cookie stores a JSON object of service decisions, often URL-encoded by the browser.
        $cookieValue = rawurldecode(wp_unslash($_COOKIE['klaro'])); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Decoded as JSON and checked strictly, never output.
        $consents = json_decode($cookieValue, true);

        return is_array($consents)
            && array_key_exists($serviceName, $consents)
            && $consents[$serviceName] === true;
    }
}
