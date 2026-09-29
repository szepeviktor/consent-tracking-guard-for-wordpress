<?php

/**
 * Viktor's Consent and Tracking Guard
 *
 * @author            Viktor Szépe <viktor@szepe.net>
 * @license           GNU General Public License v2 or later
 * @link              https://github.com/szepeviktor/consent-tracking-guard-for-wordpress
 *
 * @wordpress-plugin
 * Plugin Name:       Viktor's Consent and Tracking Guard
 * Plugin URI:        https://github.com/szepeviktor/consent-tracking-guard-for-wordpress
 * Description:       Controls consent-aware tracking, service disclosures, embeds, and WP Consent API sync.
 * Version:           2.4.10
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Viktor Szépe
 * Author URI:        https://github.com/szepeviktor
 * Text Domain:       viktors-consent-tracking-guard
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Update URI:        false
 */

declare(strict_types=1);

namespace SzepeViktor\ConsentTrackingGuard;

use function plugin_basename;

const VERSION = '2.4.10';

// Prevent direct execution.
if (! defined('ABSPATH')) {
    exit; // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- Direct execution guard.
}

require sprintf('%s/vendor/autoload.php', __DIR__);

Config::init(
    [
    'filePath' => __FILE__,
    'baseName' => plugin_basename(__FILE__),
    'slug' => 'viktors-consent-tracking-guard',
    'version' => VERSION,
    ]
);

add_action('plugins_loaded', [Plugin::class, 'boot'], 10, 0);
