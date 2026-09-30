<?php

declare(strict_types=1);

use SzepeViktor\ConsentTrackingGuard\Frontend\Assets;
use SzepeViktor\ConsentTrackingGuard\Frontend\ConsentApiBridge;
use SzepeViktor\ConsentTrackingGuard\Options;

$GLOBALS['disclosure_services_filter_options'] = [];
$GLOBALS['disclosure_services_filter_callbacks'] = [];
$GLOBALS['disclosure_services_filter_added_cookies'] = [];

function get_option(string $name, $defaultValue = false) // phpcs:ignore NeutronStandard.Functions.TypeHint.NoReturnType -- WordPress stub returns the stored option type.
{
    return $GLOBALS['disclosure_services_filter_options'][$name] ?? $defaultValue;
}

function wp_parse_args($args, $defaults = ''): array
{
    return array_merge((array) $defaults, (array) $args);
}

function __($text, string $domain = ''): string
{
    return $text;
}

function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void
{
    $GLOBALS['disclosure_services_filter_callbacks'][$hook][] = [$callback, $accepted_args];
}

function apply_filters(string $hook, $value, ...$args)
{
    foreach ($GLOBALS['disclosure_services_filter_callbacks'][$hook] ?? [] as $filter) {
        [$callback, $acceptedArgs] = $filter;
        $value = $callback(...array_slice([$value, ...$args], 0, $acceptedArgs));
    }

    return $value;
}

function wp_add_cookie_info(...$arguments): void
{
    $GLOBALS['disclosure_services_filter_added_cookies'][] = $arguments;
}

require sprintf('%s/src/Options.php', dirname(__DIR__, 2));
require sprintf('%s/src/Frontend/ConsentApiBridge.php', dirname(__DIR__, 2));
require sprintf('%s/src/Frontend/Assets.php', dirname(__DIR__, 2));

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Test runner writes assertion failures to STDERR.
            STDERR,
            sprintf(
                "%s\nExpected: %s\nActual: %s\n",
                $message,
                var_export($expected, true),
                var_export($actual, true)
            )
        );
        exit(1); // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- Test script failure.
    }
}

function build_services_for_disclosure_test(): array
{
    $assets = new Assets(
        new Options(),
        new ConsentApiBridge('consent-tracking-guard-for-wordpress/consent-tracking-guard-for-wordpress.php')
    );
    $reflection = new ReflectionMethod($assets, 'build_services');
    $reflection->setAccessible(true);
    $services = $reflection->invoke($assets, 'en');

    assert_same(true, is_array($services), 'Private service builder must return an array.');

    return $services;
}

function find_service(array $services, string $name): ?array
{
    foreach ($services as $service) {
        if (isset($service['name']) && $service['name'] === $name) {
            return $service;
        }
    }

    return null;
}

function find_registered_cookie(string $name): ?array
{
    foreach ($GLOBALS['disclosure_services_filter_added_cookies'] as $cookie) {
        if (isset($cookie[0]) && $cookie[0] === $name) {
            return $cookie;
        }
    }

    return null;
}

$GLOBALS['disclosure_services_filter_options'][Options::OPTION_NAME] = [
    'enable_wordfence' => 1,
];

$builtInServices = build_services_for_disclosure_test();
$wordfenceService = find_service($builtInServices, 'wordfence');

assert_same(null, find_service($builtInServices, 'woodmart'), 'WoodMart disclosure must not be built in.');
assert_same(true, is_array($wordfenceService), 'Wordfence disclosure must be present when enabled.');

require sprintf('%s/examples/woodmart-disclosure-service.php', dirname(__DIR__, 2));

add_filter(
    'consent_tracking_guard_for_wordpress_disclosure_services',
    static function (array $services, string $lang): array {
        assert_same('en', $lang, 'Disclosure service filter must receive the active Klaro language.');

        $services[] = [
            'name' => 'some-plugin',
            'title' => 'Some Plugin',
            'purpose' => 'functional',
            'required' => true,
            'default' => true,
            'cookies' => ['some_plugin_cookie'],
            'description' => 'Keeps Some Plugin preferences working.',
            'wp_consent_cookies' => [
                [
                    'name' => 'some_plugin_cookie',
                    'expires' => 'Session',
                    'function' => 'Stores the visitor preference.',
                    'type' => 'HTTP',
                ],
            ],
        ];

        return $services;
    },
    10,
    2
);

$filteredServices = build_services_for_disclosure_test();
$filteredWoodMartService = find_service($filteredServices, 'woodmart');
$filteredWordfenceService = find_service($filteredServices, 'wordfence');
$customService = find_service($filteredServices, 'some-plugin');

assert_same(true, is_array($filteredWoodMartService), 'WoodMart example must register a disclosure service.');
assert_same('functional', $filteredWoodMartService['wpConsentCategory'], 'WoodMart example must use functional consent.');
assert_same(
    [
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
    $filteredWoodMartService['cookies'],
    'WoodMart example must keep the previous disclosure cookie patterns.'
);
assert_same($wordfenceService, $filteredWordfenceService, 'Wordfence disclosure output must remain unchanged.');
assert_same(
    [
        'name' => 'some-plugin',
        'title' => 'Some Plugin',
        'purposes' => ['functional'],
        'default' => true,
        'required' => true,
        'optOut' => false,
        'onlyOnce' => true,
        'cookies' => ['some_plugin_cookie'],
        'wpConsentCategory' => 'functional',
        'wpConsentCookies' => [
            [
                'name' => 'some_plugin_cookie',
                'expires' => 'Session',
                'function' => 'Stores the visitor preference.',
                'type' => 'HTTP',
            ],
        ],
        'translations' => [
            'en' => [
                'title' => 'Some Plugin',
                'description' => 'Keeps Some Plugin preferences working.',
            ],
        ],
    ],
    $customService,
    'Filtered disclosure service must be normalized to Klaro service shape.'
);

$bridge = new ConsentApiBridge('consent-tracking-guard-for-wordpress/consent-tracking-guard-for-wordpress.php');
$bridge->register_services($filteredServices);
$registeredCookie = find_registered_cookie('some_plugin_cookie');

assert_same(
    true,
    is_array($registeredCookie),
    'Filtered disclosure cookies must be registered with WP Consent API.'
);
assert_same(
    'some-plugin',
    $registeredCookie[1],
    'Filtered disclosure service name must be registered with WP Consent API.'
);

fwrite(STDOUT, "Disclosure service filter PHP checks passed.\n"); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Test runner writes success output to STDOUT.
