=== Viktor's Consent and Tracking Guard ===
Contributors: szepeviktor
Tags: consent, cookies, privacy, wp consent api, google consent mode
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.4.10
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Controls consent-aware tracking, service disclosures, embeds, and WP Consent API sync.

== Description ==

Viktor's Consent and Tracking Guard is a consent management platform (CMP) for WordPress. It adds a Klaro-powered consent notice and preferences modal, controls supported tracking services, documents configured services for visitors, and synchronizes consent choices with WP Consent API when that API is available.

The plugin is built for sites that need consent-aware loading for analytics, marketing, ecommerce, multilingual, and embed use cases without depending on visitor-specific server-side HTML. This makes it suitable for full-page cached WordPress sites.

= Features =

* Klaro-powered consent notice and preferences modal.
* Customizable banner and modal copy.
* Modal style presets, including Klaro default, Viktor default, light, dark, Twenty Twenty-Five, and Cookieno-inspired styles.
* Optional floating privacy button to reopen consent settings.
* Google Consent Mode v2 support through configured Google Tag Manager or Google Analytics measurement IDs.
* Consent-aware loading for Microsoft Clarity, Hotjar, Meta Pixel, and LinkedIn Insight Tag.
* YouTube embed blocking until marketing consent is granted.
* Cookie and service disclosure data for WP Consent API-compatible tools.
* Full-page caching-friendly browser-side consent handoff.
* Translatable strings and included Latvian, Lithuanian, and Russian translations.

= WordPress and plugin integrations =

The plugin includes settings or bridges for:

* WP Consent API 2.0.1 or newer.
* WooCommerce.
* Polylang.
* Meta for WooCommerce.
* PixelYourSite.
* Triple Whale Pixel for WooCommerce.
* Klaviyo.
* Site Kit by Google Sign in with Google and One Tap disclosure.
* WoodMart theme cookies.
* Wordfence security cookies.

Only enable integrations for services actually used on the site.

= Consent categories =

Services are grouped into functional, preferences, anonymous statistics, statistics, and marketing purposes. Required functional consent stores the visitor's consent choices. Optional statistics and marketing services load only after matching consent.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/viktors-consent-tracking-guard` directory, or install the plugin ZIP through the WordPress Plugins screen.
2. Activate the plugin through the Plugins screen in WordPress.
3. Go to Settings > Consent & Tracking.
4. Review the default consent notice and preferences text.
5. Enter only the vendor IDs and integrations used by this site.
6. If the site uses WP Consent API, make sure the WP Consent API plugin is active.

== Frequently Asked Questions ==

= Does this plugin require WP Consent API? =

No. The consent banner and service controls still render without WP Consent API. When WP Consent API is available, the plugin registers cookie information and syncs visitor choices to the API.

= Does this plugin provide Google Consent Mode v2? =

Yes. When a Google Tag Manager ID or Google Analytics measurement ID is configured, the plugin initializes Google consent defaults and updates analytics and advertising consent according to the visitor's choices.

= Can this work with full-page caching? =

Yes. The plugin avoids releasing supported tracking services from visitor-specific PHP output. Consent-dependent behavior is handled in the browser so cached HTML can stay shared.

= Does it block YouTube embeds? =

Yes, when YouTube blocking is enabled. YouTube embeds are replaced until the visitor grants marketing consent.

= Can visitors change their choices later? =

Yes. Enable the floating privacy button to give visitors a persistent way to reopen privacy settings.

= Is this legal advice? =

No. This plugin provides technical consent controls and service disclosures. Site owners remain responsible for their own legal review, privacy policy, and regional compliance requirements.

== Screenshots ==

1. Consent notice displayed on the frontend.
2. Preferences modal grouped by consent purpose.
3. Settings > Consent & Tracking admin screen.
4. YouTube placeholder before marketing consent.

== Changelog ==

= 3.0.0 =

* First release on WordPress.org
