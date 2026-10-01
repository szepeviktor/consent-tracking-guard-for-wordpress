<?php

declare(strict_types=1);

use SzepeViktor\ConsentTrackingGuard\Frontend\GoogleSiteKitSignInBridge;
use SzepeViktor\ConsentTrackingGuard\Frontend\Assets;
use SzepeViktor\ConsentTrackingGuard\Frontend\ConsentApiBridge;
use SzepeViktor\ConsentTrackingGuard\Options;

require sprintf('%s/assets-script-guard.php', __DIR__);
require sprintf('%s/src/Frontend/GoogleSiteKitSignInBridge.php', dirname(__DIR__, 2));

$bridge = new GoogleSiteKitSignInBridge(new Options());
$google = ['src' => 'https://accounts.google.com/gsi/client'];
$initializer = [
    'src' => '/wp-content/plugins/google-site-kit/dist/assets/js/sign-in-with-google-example.js',
    'data-siwg-config' => '{"clientID":"example"}',
    'nonce' => 'csp-nonce',
];

assert_same($google, $bridge->filterScriptAttributes($google), 'Disabled bridge must preserve Google scripts.');
$GLOBALS['assets_script_guard_options'][Options::OPTION_NAME] = ['enable_google_site_kit_sign_in' => 1];

foreach ([$google, $initializer] as $original) {
    $guarded = $bridge->filterScriptAttributes($original);
    assert_same(false, isset($guarded['src']), 'Blocked scripts must not have an active source.');
    $isInitializer = isset($original['data-siwg-config']);
    assert_same($original['src'], $guarded[$isInitializer ? 'data-cmp-src' : 'data-src'], 'The original source must survive.');
    assert_same('text/plain', $guarded['type'], 'Scripts must be inert before consent.');
    assert_same($isInitializer ? 'text/plain' : 'application/javascript', $guarded['data-type'], 'Only Google may be activated directly by Klaro.');
    assert_same('google-site-kit-sign-in', $guarded['data-name'], 'Both scripts must use the same service.');
}

$guarded = $bridge->filterScriptAttributes($initializer);
assert_same($initializer['data-siwg-config'], $guarded['data-siwg-config'], 'Site Kit configuration must survive.');
assert_same('csp-nonce', $guarded['nonce'], 'CSP nonce must survive.');
assert_same($guarded, $bridge->filterScriptAttributes($guarded), 'Already guarded tags must remain unchanged.');
$unrelated = ['src' => 'https://example.com/script.js'];
assert_same($unrelated, $bridge->filterScriptAttributes($unrelated), 'Unrelated scripts must remain unchanged.');

$assets = new Assets(new Options(), new ConsentApiBridge('consent-tracking-guard-for-wordpress/plugin.php'));
$method = new ReflectionMethod($assets, 'buildGoogleSiteKitSignInService');
$method->setAccessible(true);
$service = $method->invoke($assets, 'en');
assert_same(false, $service['default'], 'Google sign-in must require opt-in.');
assert_same(false, $service['required'], 'Visitors must be able to decline Google sign-in.');

fwrite(STDOUT, "Site Kit sign-in bridge PHP checks passed.\n"); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Test runner writes success output to STDOUT.
