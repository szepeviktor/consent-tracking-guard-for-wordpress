<?php

/**
 * General disclosure service example for Viktor's Consent and Tracking Guard.
 *
 * Copy this into a small custom plugin, a snippet plugin, or your child theme's
 * functions.php, then replace the service and cookie details with your own.
 */

add_filter(
    'consent_tracking_guard_for_wordpress_disclosure_services',
    static function (array $services, string $lang): array {
        $services[] = [
            'name' => 'example-service',
            'title' => __('Example Service', 'your-text-domain'),
            'purpose' => 'functional',
            'required' => true,
            'default' => true,
            'cookies' => [
                'example_cookie',
                '^example_cookie_prefix_.*',
            ],
            'description' => __('Keeps the example service working.', 'your-text-domain'),
            'wp_consent_cookies' => [
                [
                    'name' => 'example_cookie',
                    // Optional: cookie expiration time, defaults to "Varies".
                    'expires' => __('Session', 'your-text-domain'),
                    // Optional: defaults to the service description.
                    'function' => __('Stores the visitor preference.', 'your-text-domain'),
                    // Optional: defaults to "HTTP".
                    'type' => 'HTTP',
                    // Optional: omit or leave empty for first-party cookies.
                    'domain' => '',
                ],
            ],
        ];

        return $services;
    },
    10,
    2
);
