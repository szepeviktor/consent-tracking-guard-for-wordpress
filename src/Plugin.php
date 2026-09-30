<?php

declare(strict_types=1);

namespace SzepeViktor\ConsentTrackingGuard;

use SzepeViktor\ConsentTrackingGuard\Frontend\Assets;
use SzepeViktor\ConsentTrackingGuard\Frontend\ConsentApiBridge;
use SzepeViktor\ConsentTrackingGuard\Frontend\FacebookForWooCommerceBridge;
use SzepeViktor\ConsentTrackingGuard\Frontend\PixelYourSiteBridge;

use function add_action;
use function is_admin;
use function wp_doing_ajax;

final class Plugin
{
    private static ?Options $options = null;

    private static ?ConsentApiBridge $consentApiBridge = null;

    private function __construct()
    {
    }

    public static function boot(): void
    {
        self::$options = new Options();
        self::$consentApiBridge = new ConsentApiBridge(Config::get('baseName'));

        self::$consentApiBridge->register();
        (new FacebookForWooCommerceBridge(self::$options))->register();
        (new PixelYourSiteBridge(self::$options))->register();
        (new Shortcodes())->register();

        add_action('init', [self::class, 'registerAssets'], 9, 0);

        if (is_admin() && ! wp_doing_ajax()) { // phpcs:ignore SlevomatCodingStandard.ControlStructures.EarlyExit.EarlyExitNotUsed -- Admin boot reads clearer as a positive condition.
            (new AdminPage(self::$options, self::$consentApiBridge))->boot();
        }
    }

    public static function registerAssets(): void
    {
        if (self::$options === null || self::$consentApiBridge === null) {
            return;
        }

        (new Assets(self::$options, self::$consentApiBridge))->register();
    }
}
