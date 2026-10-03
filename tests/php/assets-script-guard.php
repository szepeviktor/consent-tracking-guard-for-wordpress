<?php

declare(strict_types=1);

use SzepeViktor\ConsentTrackingGuard\Frontend\Assets;
use SzepeViktor\ConsentTrackingGuard\Frontend\ConsentApiBridge;
use SzepeViktor\ConsentTrackingGuard\Options;

$GLOBALS['assets_script_guard_options'] = [];
$GLOBALS['assets_script_guard_inline_scripts'] = [];

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

function wp_add_inline_script(string $handle, string $scriptData, string $position = 'after'): bool
{
    $GLOBALS['assets_script_guard_inline_scripts'][] = [$handle, $scriptData, $position];

    return true;
}

function twpwe_pending_events_get_events_script(): string
{
    $events = $GLOBALS['vendor_pending_events'] ?? '';
    $GLOBALS['vendor_pending_events'] = '';

    return $events;
}

function wp_print_inline_script_tag(string $code): void
{
    $GLOBALS['printed_vendor_events'][] = $code;
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

$GLOBALS['assets_script_guard_options'][Options::OPTION_NAME] = ['enable_triple_whale' => 1];
$vendorTag = '<script>window.TriplePixelData = {plat: "woocommerce"};</script><script src="/snippet.js"></script><script>TriplePixel("purchase", {order_id: 1});</script>';
$guarded = $assets->filter_triple_whale_script_loader_tag($vendorTag, 'triplewhale-pixel-snippet', '/snippet.js');
assert_same(false, strpos($guarded, ' src='), 'The loader must have no executable src.');
assert_same(true, strpos($guarded, 'data-ctg-triple-whale-src="/snippet.js"') !== false, 'The bridge must retain the loader URL.');
assert_same(true, strpos($guarded, 'window.TriplePixelData = {plat: "woocommerce"}') !== false, 'Vendor configuration must survive gating.');
assert_same(true, strpos($guarded, 'window.ctgTripleWhaleEvent && window.ctgTripleWhaleEvent("purchase"') !== false, 'Purchase events must use the consent gate.');
$fragments = ['div.triple_pixel_ef_container' => '<script>TriplePixel("addtocart", {});</script>', 'div.cart' => '<div>cart</div>'];
assert_same(
    ['div.triple_pixel_ef_container' => '<script>window.ctgTripleWhaleEvent && window.ctgTripleWhaleEvent("addtocart", {});</script>', 'div.cart' => '<div>cart</div>'],
    $assets->filter_triple_whale_fragments($fragments),
    'Only Triple Whale AJAX fragments must be gated.'
);
foreach ([null, '{"triple-whale-pixel":false}', '{"triple-whale-pixel":true}'] as $consentCookie) {
    unset($_COOKIE['klaro']);
    if ($consentCookie !== null) {
        $_COOKIE['klaro'] = $consentCookie;
    }

    assert_same(
        $guarded,
        $assets->filter_triple_whale_script_loader_tag($vendorTag, 'triplewhale-pixel-snippet', '/snippet.js'),
        'Cached loader and event HTML must be identical for unknown, denied, and granted consent.'
    );
    assert_same(
        ['div.triple_pixel_ef_container' => '<script>window.ctgTripleWhaleEvent && window.ctgTripleWhaleEvent("addtocart", {});</script>', 'div.cart' => '<div>cart</div>'],
        $assets->filter_triple_whale_fragments($fragments),
        'AJAX fragment gates must not depend on server-side consent cookies.'
    );
    $GLOBALS['vendor_pending_events'] = 'TriplePixel("addtocart", {item: 1});';
    $GLOBALS['printed_vendor_events'] = [];
    $assets->print_triple_whale_pending_events();
    assert_same('', $GLOBALS['vendor_pending_events'], 'Pending events must be consumed immediately.');
    assert_same(
        ['window.ctgTripleWhaleEvent && window.ctgTripleWhaleEvent("addtocart", {item: 1});'],
        $GLOBALS['printed_vendor_events'],
        'Pending event gates must be identical for every consent state.'
    );
}
unset($_COOKIE['klaro']);
$GLOBALS['assets_script_guard_options'][Options::OPTION_NAME] = ['enable_triple_whale' => 0];
assert_same($vendorTag, $assets->filter_triple_whale_script_loader_tag($vendorTag, 'triplewhale-pixel-snippet', '/snippet.js'), 'Disabled integration must preserve vendor output.');
assert_same($fragments, $assets->filter_triple_whale_fragments($fragments), 'Disabled integration must preserve fragments.');

fwrite(STDOUT, "Assets script guard PHP checks passed.\n"); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Test runner writes success output to STDOUT.
