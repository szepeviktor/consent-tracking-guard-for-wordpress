<?php

declare(strict_types=1);

use SzepeViktor\ConsentTrackingGuard\Frontend\Assets;
use SzepeViktor\ConsentTrackingGuard\Frontend\ConsentApiBridge;
use SzepeViktor\ConsentTrackingGuard\Options;

$GLOBALS['assets_script_guard_options'] = [];

function get_option(string $name, $defaultValue = false) // phpcs:ignore NeutronStandard.Functions.TypeHint.NoReturnType -- WordPress stub returns the stored option type.
{
    return $GLOBALS['assets_script_guard_options'][$name] ?? $defaultValue;
}

function wp_parse_args($args, $defaults = ''): array
{
    return array_merge((array) $defaults, (array) $args);
}

function __($text): string
{
    return $text;
}

function esc_attr(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function esc_url(string $url): string
{
    return $url;
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

$assets = new Assets(
    new Options(),
    new ConsentApiBridge('consent-tracking-guard-for-wordpress/consent-tracking-guard-for-wordpress.php')
);

$GLOBALS['assets_script_guard_options'][Options::OPTION_NAME] = [
    'enable_klaviyo' => 1,
];

assert_same(
    '<script id="klaviyojs-js" type="text/plain" data-type="application/javascript" data-name="klaviyo" data-src="https://static.klaviyo.com/onsite/js/PUBLIC_API_KEY/klaviyo.js?ver=3.8.2" async></script>',
    $assets->filter_klaviyo_script_loader_tag(
        '<script id="klaviyojs-js" src="https://static.klaviyo.com/onsite/js/PUBLIC_API_KEY/klaviyo.js?ver=3.8.2" async></script>',
        'klaviyojs',
        'https://static.klaviyo.com/onsite/js/PUBLIC_API_KEY/klaviyo.js?ver=3.8.2'
    ),
    'The Klaviyo plugin handle must be converted to a Klaro-managed inert script.'
);

assert_same(
    '<script id="third-party-handle-js" type="text/plain" data-type="application/javascript" data-name="klaviyo" data-src="https://static.klaviyo.com/onsite/js/PUBLIC_API_KEY/klaviyo.js"></script>',
    $assets->filter_klaviyo_script_loader_tag(
        '<script id="third-party-handle-js" src="https://static.klaviyo.com/onsite/js/PUBLIC_API_KEY/klaviyo.js"></script>',
        'third-party-handle',
        'https://static.klaviyo.com/onsite/js/PUBLIC_API_KEY/klaviyo.js'
    ),
    'A Klaviyo CDN URL must be guarded even if another plugin changes the handle.'
);

$GLOBALS['assets_script_guard_options'][Options::OPTION_NAME] = [
    'enable_klaviyo' => 0,
];

assert_same(
    '<script id="klaviyojs-js" src="https://static.klaviyo.com/onsite/js/PUBLIC_API_KEY/klaviyo.js"></script>',
    $assets->filter_klaviyo_script_loader_tag(
        '<script id="klaviyojs-js" src="https://static.klaviyo.com/onsite/js/PUBLIC_API_KEY/klaviyo.js"></script>',
        'klaviyojs',
        'https://static.klaviyo.com/onsite/js/PUBLIC_API_KEY/klaviyo.js'
    ),
    'Disabled Klaviyo disclosure must not alter the script tag.'
);

fwrite(STDOUT, "Assets script guard PHP checks passed.\n"); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Test runner writes success output to STDOUT.
