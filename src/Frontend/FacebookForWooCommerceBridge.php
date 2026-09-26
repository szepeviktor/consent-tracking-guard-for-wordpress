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

        // This filter may run while full-page cache HTML is generated, so it must not release signals
        // from visitor-specific consent cookies. The browser bridge releases them after consent.
        return true;
    }
}
