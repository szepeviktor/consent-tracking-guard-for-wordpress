<?php

declare(strict_types=1);

namespace SzepeViktor\ConsentTrackingGuard\Frontend;

use SzepeViktor\ConsentTrackingGuard\Options;

/**
 * Site Kit prints these tags directly, bypassing script_loader_tag.
 */
final class GoogleSiteKitSignInBridge
{
    private Options $options;

    public function __construct(Options $options)
    {
        $this->options = $options;
    }

    public function register(): void
    {
        add_filter('wp_script_attributes', [$this, 'filterScriptAttributes'], 100);
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function filterScriptAttributes(array $attributes): array
    {
        if (! $this->options->enabled('enable_google_site_kit_sign_in')) {
            return $attributes;
        }

        $src = $attributes['src'] ?? '';

        if (
            ! is_string($src)
            || $src === ''
            || (
                $src !== 'https://accounts.google.com/gsi/client'
                && ! isset($attributes['data-siwg-config'])
            )
        ) {
            return $attributes;
        }

        // Keep the configuration, nonce and other vendor attributes intact.
        if (isset($attributes['data-siwg-config'])) {
            // Keep the initializer inert even when Klaro grants consent.
            // Our bootstrap loads it after Google Identity Services is ready.
            $attributes['data-cmp-src'] = $src;
            $attributes['data-cmp-type'] = $attributes['type'] ?? 'application/javascript';
            $attributes['data-type'] = 'text/plain';
        } else {
            $attributes['data-src'] = $src;
            $attributes['data-type'] = $attributes['type'] ?? 'application/javascript';
        }
        $attributes['type'] = 'text/plain';
        $attributes['data-name'] = 'google-site-kit-sign-in';
        unset($attributes['src']);

        return $attributes;
    }
}
