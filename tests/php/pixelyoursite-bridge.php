<?php

declare(strict_types=1);

use SzepeViktor\ConsentTrackingGuard\Frontend\PixelYourSiteBridge;
use SzepeViktor\ConsentTrackingGuard\Options;

$GLOBALS['pixelyoursite_bridge_options'] = [];
$GLOBALS['pixelyoursite_bridge_has_consent'] = [];
$GLOBALS['pixelyoursite_bridge_filters'] = [];

function add_filter(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): void
{
    $GLOBALS['pixelyoursite_bridge_filters'][$hook] = [$callback, $priority, $acceptedArgs];
}

function get_option(string $name, $defaultValue = false) // phpcs:ignore NeutronStandard.Functions.TypeHint.NoReturnType -- WordPress stub returns the stored option type.
{
    return $GLOBALS['pixelyoursite_bridge_options'][$name] ?? $defaultValue;
}

function wp_parse_args($args, $defaults = ''): array
{
    return array_merge((array) $defaults, (array) $args);
}

function wp_has_consent(string $category): bool
{
    return (bool) ($GLOBALS['pixelyoursite_bridge_has_consent'][$category] ?? false);
}

function sanitize_text_field(string $textFieldValue): string
{
    return trim($textFieldValue);
}

function wp_unslash(string $slashedValue): string
{
    return stripslashes($slashedValue);
}

function __($text): string
{
    return $text;
}

require sprintf('%s/src/Options.php', dirname(__DIR__, 2));
require sprintf('%s/src/Frontend/PixelYourSiteBridge.php', dirname(__DIR__, 2));

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

$bridge = new PixelYourSiteBridge(new Options());
$bridge->register();

assert_same(
    [$bridge, 'filterDisableMarketing'],
    $GLOBALS['pixelyoursite_bridge_filters']['pys_disable_facebook_by_gdpr'][0],
    'Bridge must register PixelYourSite marketing filters.'
);

assert_same(
    false,
    $bridge->filterDisableMarketing(false),
    'Disabled bridge must preserve PixelYourSite marketing state.'
);

$GLOBALS['pixelyoursite_bridge_options'][Options::OPTION_NAME] = [
    'enable_pixelyoursite' => 1,
];

assert_same(
    true,
    $bridge->filterDisableAll(false),
    'Enabled bridge must block PixelYourSite before tracking consent.'
);

$_COOKIE['wp_consent_statistics'] = 'allow';

assert_same(
    false,
    $bridge->filterDisableAnalytics(false),
    'Statistics consent must release PixelYourSite analytics.'
);

assert_same(
    true,
    $bridge->filterDisableMarketing(false),
    'Statistics consent must not release PixelYourSite marketing pixels.'
);

assert_same(
    false,
    $bridge->filterServerEventDisabled(false, 'init_event', 'ga4', null),
    'Statistics consent must release GA4 server events.'
);

assert_same(
    true,
    $bridge->filterServerEventDisabled(false, 'init_event', 'facebook', null),
    'Statistics consent must keep Meta server events blocked.'
);

$_COOKIE['wp_consent_marketing'] = 'allow';

assert_same(
    false,
    $bridge->filterDisableMarketing(false),
    'Marketing consent must release PixelYourSite marketing pixels.'
);

assert_same(
    false,
    $bridge->filterDisableAllCookies(false),
    'Tracking consent must release shared PixelYourSite tracking cookies.'
);

assert_same(
    true,
    $bridge->filterMarketingConsentMode(false),
    'Marketing consent must grant PixelYourSite ad consent mode filters.'
);

assert_same(
    true,
    $bridge->filterDisableMarketing(true),
    'Bridge must preserve an upstream PixelYourSite block decision.'
);

fwrite(STDOUT, "PixelYourSite bridge PHP checks passed.\n"); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Test runner writes success output to STDOUT.
