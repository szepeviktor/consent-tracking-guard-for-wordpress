<?php

/**
 * LiteSpeed Cache disclosure service for Viktor's Consent and Tracking Guard.
 *
 * Copy this file into a small custom plugin, a snippet plugin, or your child
 * theme's functions.php when the site uses LiteSpeed Cache Guest Mode.
 */

add_filter(
    'consent_tracking_guard_for_wordpress_disclosure_services',
    static function (array $services): array {
        $services[] = [
            'name' => 'litespeed-cache',
            'title' => __('LiteSpeed Cache', 'consent-tracking-guard-for-wordpress'),
            'purpose' => 'functional',
            'required' => true,
            'default' => true,
            'cookies' => [
                '_lscache_vary',
                // If LiteSpeed Cache > Cache > Advanced > Login Cookie is set,
                // add that custom cookie name here too.
            ],
            'description' => __(
                'Keeps LiteSpeed Cache page variants and guest-mode cache behavior working correctly.',
                'consent-tracking-guard-for-wordpress'
            ),
            'wp_consent_cookies' => [
                [
                    'name' => '_lscache_vary',
                    'expires' => __('2 days', 'consent-tracking-guard-for-wordpress'),
                    'function' => __(
                        'Stores a cache variation value so LiteSpeed Cache can serve the correct cached page version.',
                        'consent-tracking-guard-for-wordpress'
                    ),
                    'type' => 'HTTP',
                ],
            ],
        ];

        return $services;
    }
);
