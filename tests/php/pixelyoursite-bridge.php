<?php

declare(strict_types=1);

use SzepeViktor\ConsentTrackingGuard\Frontend\PixelYourSiteBridge;
use SzepeViktor\ConsentTrackingGuard\Options;

$GLOBALS['pixelyoursite_bridge_options'] = [];
$GLOBALS['pixelyoursite_bridge_has_consent'] = [];
$GLOBALS['pixelyoursite_bridge_filters'] = [];
$GLOBALS['pixelyoursite_bridge_doing_ajax'] = false;

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

function wp_doing_ajax(): bool
{
    return (bool) $GLOBALS['pixelyoursite_bridge_doing_ajax'];
}

function sanitize_text_field(string $textFieldValue): string
{
    return trim($textFieldValue);
}

function sanitize_key(string $requestAction): string
{
    return strtolower(preg_replace('/[^a-z0-9_\-]/', '', $requestAction) ?? '');
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
    [$bridge, 'filterGdprAjaxEnabled'],
    $GLOBALS['pixelyoursite_bridge_filters']['pys_gdpr_ajax_enabled'][0],
    'Bridge must register the PixelYourSite GDPR AJAX filter.'
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
    $bridge->filterGdprAjaxEnabled(false),
    'Enabled bridge must force PixelYourSite GDPR AJAX refresh on cached pages.'
);

assert_same(
    true,
    $bridge->filterDisableAll(false),
    'Enabled bridge must block PixelYourSite before tracking consent.'
);

$_COOKIE['wp_consent_statistics'] = 'allow';

assert_same(
    true,
    $bridge->filterDisableAnalytics(false),
    'Statistics cookie must not release PixelYourSite analytics while cacheable PHP output is generated.'
);

assert_same(
    true,
    $bridge->filterDisableMarketing(false),
    'Statistics consent must not release PixelYourSite marketing pixels.'
);

assert_same(
    true,
    $bridge->filterServerEventDisabled(false, 'init_event', 'ga4', null),
    'Statistics cookie must not release GA4 server events while cacheable PHP output is generated.'
);

assert_same(
    true,
    $bridge->filterServerEventDisabled(false, 'init_event', 'facebook', null),
    'Statistics consent must keep Meta server events blocked.'
);

$_COOKIE['wp_consent_marketing'] = 'allow';

assert_same(
    true,
    $bridge->filterDisableMarketing(false),
    'Marketing cookie must not release PixelYourSite marketing pixels while cacheable PHP output is generated.'
);

assert_same(
    true,
    $bridge->filterDisableAllCookies(false),
    'Tracking consent cookies must not release shared PixelYourSite cookies while cacheable PHP output is generated.'
);

assert_same(
    false,
    $bridge->filterMarketingConsentMode(false),
    'Marketing cookie must not grant PixelYourSite ad consent mode while cacheable PHP output is generated.'
);

assert_same(
    true,
    $bridge->filterDisableMarketing(true),
    'Bridge must preserve an upstream PixelYourSite block decision.'
);

$_REQUEST['action'] = 'pys_get_gdpr_filters_values';
$GLOBALS['pixelyoursite_bridge_doing_ajax'] = true;
unset($_COOKIE['wp_consent_marketing']);

assert_same(
    false,
    $bridge->filterDisableAnalytics(false),
    'Statistics consent must release PixelYourSite analytics during the uncached PYS AJAX refresh.'
);

assert_same(
    false,
    $bridge->filterServerEventDisabled(false, 'init_event', 'ga4', null),
    'Statistics consent must release GA4 server events during the uncached PYS AJAX refresh.'
);

assert_same(
    true,
    $bridge->filterServerEventDisabled(false, 'init_event', 'facebook', null),
    'Statistics consent must keep Meta server events blocked during the uncached PYS AJAX refresh.'
);

$_COOKIE['wp_consent_marketing'] = 'allow';

assert_same(
    false,
    $bridge->filterDisableMarketing(false),
    'Marketing consent must release PixelYourSite marketing pixels during the uncached PYS AJAX refresh.'
);

assert_same(
    false,
    $bridge->filterDisableAllCookies(false),
    'Tracking consent must release shared PixelYourSite cookies during the uncached PYS AJAX refresh.'
);

assert_same(
    true,
    $bridge->filterMarketingConsentMode(false),
    'Marketing consent must grant PixelYourSite ad consent mode during the uncached PYS AJAX refresh.'
);

unset($_COOKIE['wp_consent_statistics'], $_COOKIE['wp_consent_marketing']);
$_COOKIE['klaro'] = json_encode([
    'klaro' => true,
    'facebook-for-woocommerce' => false,
    'pixelyoursite-statistics' => true,
    'pixelyoursite-marketing' => true,
]);

assert_same(
    false,
    $bridge->filterDisableAnalytics(false),
    'Klaro statistics consent must release PixelYourSite analytics during the uncached PYS AJAX refresh.'
);

assert_same(
    false,
    $bridge->filterDisableMarketing(false),
    'Klaro marketing consent must release PixelYourSite marketing during the uncached PYS AJAX refresh.'
);

assert_same(
    true,
    $bridge->filterMarketingConsentMode(false),
    'Klaro marketing consent must grant PixelYourSite ad consent mode during the uncached PYS AJAX refresh.'
);

fwrite(STDOUT, "PixelYourSite bridge PHP checks passed.\n"); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Test runner writes success output to STDOUT.
