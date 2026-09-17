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
    private Options $options;

    public function __construct(Options $options)
    {
        $this->options = $options;
    }

    public function register(): void
    {
        add_filter('facebook_signals_held', [$this, 'filterSignalsHeld']);
    }

    /**
     * @param mixed $held
     */
    public function filterSignalsHeld($held): bool
    {
        $options = $this->options->all();

        if (! (bool) $options['enable_facebook_for_woocommerce']) {
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

        return isset($_COOKIE['wp_consent_marketing'])
            && is_string($_COOKIE['wp_consent_marketing'])
            && strtolower(sanitize_text_field(wp_unslash($_COOKIE['wp_consent_marketing']))) === 'allow';
    }
}
