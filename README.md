# Viktor's Consent and Tracking Guard for WordPress

Viktor's Consent and Tracking Guard for WordPress is a consent management platform (CMP) for WordPress.
It controls consent-aware tracking, service disclosures, blocked embeds, and WP Consent API sync.
Powered by [Klaro](https://github.com/kiprotect/klaro).

## Compatibility

This CMP is built to work with WordPress consent and tracking compatibility requirements.

- Google Consent Mode v2
- WP Consent API 2.0.1+ (`wp-consent-api`)
- Full-page caching support
- Multilingual sites
- WooCommerce stores
- Consent-aware script loading for Google Tag Manager, Microsoft Clarity, Hotjar, Meta Pixel, and LinkedIn Insight Tag
- YouTube embed blocking

## WordPress Plugin Integrations

This plugin includes consent-aware disclosures and bridges for other WordPress plugins.
It has been checked with these plugin integrations.

- Klaviyo 3.8.3+ (`klaviyo`) - bridge implemented
- Meta for WooCommerce 3.7.0+ (`facebook-for-woocommerce`) - bridge implemented
- PixelYourSite 12.5.0+ (`pixelyoursite-pro`) - bridge implemented
- Triple Whale Pixel for WooCommerce 1.0.4+ (`triplewhale-pixel-woo-extension`) - bridge implemented
- Site Kit by Google (`google-site-kit`) - Sign in with Google disclosure implemented
- GTM4WP (`duracelltomi-google-tag-manager`)
- Polylang (`polylang`)
- WooCommerce (`woocommerce`)
- Wordfence (`wordfence`)

Optional hook-based disclosure examples live in `examples/`, including shorthand, already-normalized Klaro, LiteSpeed Cache, and WoodMart theme cookie examples.
Use `consent_tracking_guard_for_wordpress_disclosure_services` to add disclosure-only services.

## Installation

1. Download the latest plugin ZIP from [Releases](https://github.com/szepeviktor/consent-tracking-guard-for-wordpress/releases).
2. In WordPress, go to **Plugins → Add New Plugin → Upload Plugin**.
3. Upload the ZIP, install it, and activate the plugin.
4. Go to **Settings → Consent & Tracking** to customize the banner and integrations.

Requires WordPress 6.4 or later and PHP 7.4 or later.

## License

GPL-2.0-or-later.
