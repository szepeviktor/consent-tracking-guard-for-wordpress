<?php

declare(strict_types=1);

/**
 * Already-normalized Klaro disclosure service example.
 *
 * Copy this into a small custom plugin, a snippet plugin, or your child theme's
 * functions.php when you want full control over the Klaro service structure.
 */

add_filter(
    'consent_tracking_guard_for_wordpress_disclosure_services',
    static function (array $services, string $lang): array {
        $services[] = [
            'name' => 'example-normalized-service',
            'title' => __('Example Normalized Service', 'your-text-domain'),
            'purposes' => ['functional'],
            'default' => true,
            'required' => true,
            'optOut' => false,
            'onlyOnce' => true,
            'cookies' => [
                'example_normalized_cookie',
                '^example_normalized_cookie_prefix_.*',
            ],
            'wpConsentCategory' => 'functional',
            'wpConsentCookies' => [
                [
                    'name' => 'example_normalized_cookie',
                    'expires' => __('Session', 'your-text-domain'),
                    'function' => __('Stores the visitor preference.', 'your-text-domain'),
                    'type' => 'HTTP',
                ],
            ],
            'translations' => [
                $lang => [
                    'title' => __('Example Normalized Service', 'your-text-domain'),
                    'description' => __('Keeps the example normalized service working.', 'your-text-domain'),
                ],
            ],
        ];

        return $services;
    },
    10,
    2
);
