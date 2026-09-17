<?php

declare(strict_types=1);

use SzepeViktor\ConsentTrackingGuard\Frontend\FacebookForWooCommerceBridge;
use SzepeViktor\ConsentTrackingGuard\Options;

$GLOBALS['facebook_for_woocommerce_bridge_options'] = [];
$GLOBALS['facebook_for_woocommerce_bridge_has_marketing_consent'] = false;

function add_filter(string $hook, $callback): void
{
    $GLOBALS['facebook_for_woocommerce_bridge_filters'][$hook] = $callback;
}

function get_option(string $name, $default = false)
{
    return $GLOBALS['facebook_for_woocommerce_bridge_options'][$name] ?? $default;
}

function wp_parse_args($args, $defaults = ''): array
{
    return array_merge((array) $defaults, (array) $args);
}

function wp_has_consent(string $category): bool
{
    return $category === 'marketing'
        && (bool) $GLOBALS['facebook_for_woocommerce_bridge_has_marketing_consent'];
}

function sanitize_text_field(string $value): string
{
    return trim($value);
}

function wp_unslash(string $value): string
{
    return stripslashes($value);
}

function __($text): string
{
    return $text;
}

require dirname(__DIR__, 2) . '/src/Options.php';
require dirname(__DIR__, 2) . '/src/Frontend/FacebookForWooCommerceBridge.php';

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(
            STDERR,
            sprintf(
                "%s\nExpected: %s\nActual: %s\n",
                $message,
                var_export($expected, true),
                var_export($actual, true)
            )
        );
        exit(1);
    }
}

$bridge = new FacebookForWooCommerceBridge(new Options());
$bridge->register();

assert_same(
    false,
    $bridge->filterSignalsHeld(false),
    'Disabled bridge must preserve released signals.'
);

$GLOBALS['facebook_for_woocommerce_bridge_options'][Options::OPTION_NAME] = [
    'enable_facebook_for_woocommerce' => 1,
];

assert_same(
    true,
    $bridge->filterSignalsHeld(false),
    'Enabled bridge must hold signals without marketing consent.'
);

$_COOKIE['wp_consent_marketing'] = 'allow';

assert_same(
    false,
    $bridge->filterSignalsHeld(false),
    'Explicit WP Consent API marketing cookie must release signals.'
);

$_COOKIE['wp_consent_marketing'] = 'deny';
$GLOBALS['facebook_for_woocommerce_bridge_has_marketing_consent'] = true;

assert_same(
    false,
    $bridge->filterSignalsHeld(false),
    'Runtime WP Consent API marketing consent must release signals.'
);

assert_same(
    true,
    $bridge->filterSignalsHeld(true),
    'The bridge must not override an upstream hold decision.'
);

fwrite(STDOUT, "Meta for WooCommerce bridge PHP checks passed.\n");
