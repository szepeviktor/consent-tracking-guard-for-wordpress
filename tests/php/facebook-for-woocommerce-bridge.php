<?php

declare(strict_types=1);

use SzepeViktor\ConsentTrackingGuard\Frontend\FacebookForWooCommerceBridge;
use SzepeViktor\ConsentTrackingGuard\Options;

$GLOBALS['facebook_for_woocommerce_bridge_options'] = [];
$GLOBALS['facebook_for_woocommerce_bridge_has_marketing_consent'] = false;
$GLOBALS['facebook_for_woocommerce_bridge_actions'] = [];

if (! defined('YEAR_IN_SECONDS')) {
    define('YEAR_IN_SECONDS', 31536000);
}

if (! defined('COOKIEPATH')) {
    define('COOKIEPATH', '/');
}

if (! defined('COOKIE_DOMAIN')) {
    define('COOKIE_DOMAIN', '');
}

function add_action(string $hook, $callback, int $priority = 10): void
{
    $GLOBALS['facebook_for_woocommerce_bridge_actions'][$hook][$priority] = $callback;
}

function add_filter(string $hook, $callback): void
{
    $GLOBALS['facebook_for_woocommerce_bridge_filters'][$hook] = $callback;
}

function get_option(string $name, $defaultValue = false) // phpcs:ignore NeutronStandard.Functions.TypeHint.NoReturnType -- WordPress stub returns the stored option type.
{
    return $GLOBALS['facebook_for_woocommerce_bridge_options'][$name] ?? $defaultValue;
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

function is_ssl(): bool
{
    return true;
}

function sanitize_text_field(string $textFieldValue): string
{
    return trim($textFieldValue);
}

function wp_unslash(string $slashedValue): string
{
    return stripslashes($slashedValue);
}

function wp_json_encode($valueToEncode) // phpcs:ignore NeutronStandard.Functions.TypeHint.NoArgumentType, NeutronStandard.Functions.TypeHint.NoReturnType -- WordPress stub mirrors core.
{
    return json_encode($valueToEncode); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- WordPress stub delegates to PHP in tests.
}

function __($text): string
{
    return $text;
}

require sprintf('%s/src/Options.php', dirname(__DIR__, 2));
require sprintf('%s/src/Frontend/FacebookForWooCommerceBridge.php', dirname(__DIR__, 2));

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

$bridge = new FacebookForWooCommerceBridge(new Options());
$bridge->register();

assert_same(
    [],
    $GLOBALS['facebook_for_woocommerce_bridge_actions'],
    'Bridge must not sync Meta for WooCommerce signals through cache-sensitive PHP headers.'
);

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

unset($_COOKIE['wp_consent_marketing']);
$_COOKIE['klaro'] = rawurlencode((string) wp_json_encode(['facebook-for-woocommerce' => false]));

assert_same(
    true,
    $bridge->filterSignalsHeld(false),
    'Denied Klaro service consent must keep Meta for WooCommerce signals held.'
);

$_COOKIE['klaro'] = rawurlencode((string) wp_json_encode(['facebook-for-woocommerce' => true]));

assert_same(
    false,
    $bridge->filterSignalsHeld(false),
    'Allowed Klaro service consent must release Meta for WooCommerce signals.'
);

unset($_COOKIE['klaro']);
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

fwrite(STDOUT, "Meta for WooCommerce bridge PHP checks passed.\n"); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Test runner writes success output to STDOUT.
