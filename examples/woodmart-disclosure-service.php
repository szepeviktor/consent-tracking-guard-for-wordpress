<?php

/**
 * Example WoodMart disclosure service for Viktor's Consent and Tracking Guard.
 *
 * Copy this file into a small custom plugin, a snippet plugin, or your child
 * theme's functions.php when the site uses WoodMart cookies.
 */

add_filter(
    'consent_tracking_guard_for_wordpress_disclosure_services',
    static function (array $services): array {
        $services[] = [
            'name' => 'woodmart',
            'title' => __('WoodMart', 'consent-tracking-guard-for-wordpress'),
            'purpose' => 'functional',
            'required' => true,
            'default' => true,
            'cookies' => [
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
            ],
            'description' => __(
                'Keeps WoodMart shop preferences, wishlist, compare, product history, and popups working.',
                'consent-tracking-guard-for-wordpress'
            ),
            'wp_consent_cookies' => [
                [
                    'name' => 'woodmart_recently_viewed_products',
                    'expires' => __('7 days', 'consent-tracking-guard-for-wordpress'),
                    'function' => __(
                        'Stores products recently viewed by the visitor.',
                        'consent-tracking-guard-for-wordpress'
                    ),
                    'type' => 'HTTP',
                ],
                [
                    'name' => 'woodmart_wishlist_hash',
                    'expires' => __('Session', 'consent-tracking-guard-for-wordpress'),
                    'function' => __(
                        'Checks whether the visitor’s WoodMart wishlist has changed.',
                        'consent-tracking-guard-for-wordpress'
                    ),
                    'type' => 'HTTP',
                ],
                [
                    'name' => 'woodmart_wishlist_count',
                    'expires' => __('Session', 'consent-tracking-guard-for-wordpress'),
                    'function' => __(
                        'Stores the number of products in the visitor’s WoodMart wishlist.',
                        'consent-tracking-guard-for-wordpress'
                    ),
                    'type' => 'HTTP',
                ],
                [
                    'name' => 'woodmart_wishlist_products',
                    'expires' => __('Session', 'consent-tracking-guard-for-wordpress'),
                    'function' => __(
                        'Stores products added to the visitor’s WoodMart wishlist.',
                        'consent-tracking-guard-for-wordpress'
                    ),
                    'type' => 'HTTP',
                ],
                [
                    'name' => 'woodmart_compare_list',
                    'expires' => __('Session', 'consent-tracking-guard-for-wordpress'),
                    'function' => __(
                        'Stores products added to the visitor’s WoodMart compare list.',
                        'consent-tracking-guard-for-wordpress'
                    ),
                    'type' => 'HTTP',
                ],
                [
                    'name' => 'shop_view',
                    'expires' => __('Session', 'consent-tracking-guard-for-wordpress'),
                    'function' => __(
                        'Remembers the visitor’s selected shop list or grid view.',
                        'consent-tracking-guard-for-wordpress'
                    ),
                    'type' => 'HTTP',
                ],
                [
                    'name' => 'woodmart_age_verify',
                    'expires' => __('Session', 'consent-tracking-guard-for-wordpress'),
                    'function' => __(
                        'Remembers that the visitor passed the WoodMart age verification prompt.',
                        'consent-tracking-guard-for-wordpress'
                    ),
                    'type' => 'HTTP',
                ],
            ],
        ];

        return $services;
    }
);
