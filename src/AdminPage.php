<?php

declare(strict_types=1);

namespace SzepeViktor\ConsentTrackingGuard;

use SzepeViktor\ConsentTrackingGuard\Frontend\ConsentApiBridge;

use function __;
use function add_action;
use function add_options_page;
use function add_settings_field;
use function add_settings_section;
use function checked;
use function current_user_can;
use function do_settings_sections;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function esc_textarea;
use function register_setting;
use function selected;
use function settings_fields;
use function submit_button;

final class AdminPage
{
    public const MENU_SLUG = 'consent-tracking-guard-for-wordpress';
    public const PAGE_SLUG = 'consent-tracking-guard-for-wordpress-page';
    public const OPTION_GROUP = 'consent_tracking_guard_for_wordpress';
    public const BANNER_SECTION = 'consent-tracking-guard-for-wordpress-banner';
    public const DISPLAY_SECTION = 'consent-tracking-guard-for-wordpress-display';
    public const INTEGRATIONS_SECTION = 'consent-tracking-guard-for-wordpress-integrations';

    private Options $options;

    private ConsentApiBridge $consentApiBridge;

    public function __construct(Options $options, ConsentApiBridge $consentApiBridge)
    {
        $this->options = $options;
        $this->consentApiBridge = $consentApiBridge;
    }

    public function boot(): void
    {
        add_action('admin_menu', [$this, 'addSettingsPage']);
        add_action('admin_init', [$this, 'addFields']);
    }

    public function addSettingsPage(): void
    {
        add_options_page(
            __('Viktor\'s Consent and Tracking Guard for WordPress', 'consent-tracking-guard-for-wordpress'),
            __('Consent & Tracking', 'consent-tracking-guard-for-wordpress'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderSettingsPage']
        );
    }

    public function addFields(): void
    {
        $this->registerSettings();
        $this->addSections();
        $this->addBannerFields();
        $this->addIntegrationFields();
        $this->addDisplayFields();
    }

    private function registerSettings(): void
    {
        register_setting(
            self::OPTION_GROUP,
            Options::OPTION_NAME,
            [
                'type' => 'array',
                'default' => $this->options->defaults(),
                'sanitize_callback' => [$this->options, 'sanitize'],
            ]
        );
    }

    private function addSections(): void
    {
        add_settings_section(
            self::BANNER_SECTION,
            __('Banner copy', 'consent-tracking-guard-for-wordpress'),
            [$this, 'renderBannerSection'],
            self::PAGE_SLUG
        );
        add_settings_section(
            self::DISPLAY_SECTION,
            __('Display', 'consent-tracking-guard-for-wordpress'),
            [$this, 'renderDisplaySection'],
            self::PAGE_SLUG
        );
        add_settings_section(
            self::INTEGRATIONS_SECTION,
            __('Integrations', 'consent-tracking-guard-for-wordpress'),
            [$this, 'renderIntegrationsSection'],
            self::PAGE_SLUG
        );
    }

    private function addBannerFields(): void
    {
        $this->addTextField(
            'notice_title',
            __('Notice title', 'consent-tracking-guard-for-wordpress'),
            self::BANNER_SECTION
        );
        $this->addTextareaField(
            'notice_description',
            __('Notice description', 'consent-tracking-guard-for-wordpress'),
            self::BANNER_SECTION
        );
        $this->addTextField(
            'modal_title',
            __('Modal title', 'consent-tracking-guard-for-wordpress'),
            self::BANNER_SECTION
        );
        $this->addTextareaField(
            'modal_description',
            __('Modal description', 'consent-tracking-guard-for-wordpress'),
            self::BANNER_SECTION
        );
    }

    private function addDisplayFields(): void
    {
        add_settings_field(
            'consent-tracking-guard-for-wordpress-modal-style',
            __('Modal style', 'consent-tracking-guard-for-wordpress'),
            [$this, 'renderModalStyleField'],
            self::PAGE_SLUG,
            self::DISPLAY_SECTION
        );
        add_settings_field(
            'consent-tracking-guard-for-wordpress-enable-floating',
            __('Floating privacy button', 'consent-tracking-guard-for-wordpress'),
            [$this, 'renderCheckboxField'],
            self::PAGE_SLUG,
            self::DISPLAY_SECTION,
            [
                'name' => 'enable_floating',
                'label' => __('Show a floating button that reopens the privacy settings.', 'consent-tracking-guard-for-wordpress'),
            ]
        );
    }

    private function addIntegrationFields(): void
    {
        $this->addTextField(
            'gtm_id',
            __('Google Tag Manager ID', 'consent-tracking-guard-for-wordpress'),
            self::INTEGRATIONS_SECTION
        );
        $this->addTextField(
            'clarity_project_id',
            __('Microsoft Clarity project ID', 'consent-tracking-guard-for-wordpress'),
            self::INTEGRATIONS_SECTION
        );
        $this->addTextField(
            'hotjar_id',
            __('Hotjar site ID', 'consent-tracking-guard-for-wordpress'),
            self::INTEGRATIONS_SECTION
        );
        $this->addNumberField(
            'hotjar_version',
            __('Hotjar script version', 'consent-tracking-guard-for-wordpress'),
            self::INTEGRATIONS_SECTION
        );
        $this->addTextField(
            'meta_pixel_id',
            __('Meta Pixel ID', 'consent-tracking-guard-for-wordpress'),
            self::INTEGRATIONS_SECTION
        );
        $this->addTextField(
            'linkedin_partner_id',
            __('LinkedIn partner ID', 'consent-tracking-guard-for-wordpress'),
            self::INTEGRATIONS_SECTION
        );
        $this->addPolylangField();
        $this->addWooCommerceField();
        $this->addFacebookForWooCommerceField();
        $this->addPixelYourSiteField();
        $this->addKlaviyoField();
        $this->addWoodMartField();
        $this->addWordfenceField();
        $this->addYouTubeField();
    }

    private function addPolylangField(): void
    {
        add_settings_field(
            'consent-tracking-guard-for-wordpress-enable-polylang',
            __('Polylang disclosure', 'consent-tracking-guard-for-wordpress'),
            [$this, 'renderCheckboxField'],
            self::PAGE_SLUG,
            self::INTEGRATIONS_SECTION,
            [
                'name' => 'enable_polylang',
                'label' => __(
                    'Show the Polylang language cookie for multilingual sites and WooCommerce shops.',
                    'consent-tracking-guard-for-wordpress'
                ),
            ]
        );
    }

    private function addWooCommerceField(): void
    {
        add_settings_field(
            'consent-tracking-guard-for-wordpress-enable-woocommerce',
            __('WooCommerce disclosure', 'consent-tracking-guard-for-wordpress'),
            [$this, 'renderCheckboxField'],
            self::PAGE_SLUG,
            self::INTEGRATIONS_SECTION,
            [
                'name' => 'enable_woocommerce',
                'label' => __(
                    'Show WooCommerce cart, checkout, session, and source attribution cookies.',
                    'consent-tracking-guard-for-wordpress'
                ),
            ]
        );
    }

    private function addFacebookForWooCommerceField(): void
    {
        add_settings_field(
            'consent-tracking-guard-for-wordpress-enable-facebook-for-woocommerce',
            __('Meta for WooCommerce consent bridge', 'consent-tracking-guard-for-wordpress'),
            [$this, 'renderCheckboxField'],
            self::PAGE_SLUG,
            self::INTEGRATIONS_SECTION,
            [
                'name' => 'enable_facebook_for_woocommerce',
                'label' => __(
                    'Hold Meta for WooCommerce tracking until marketing consent is granted.',
                    'consent-tracking-guard-for-wordpress'
                ),
            ]
        );
    }

    private function addPixelYourSiteField(): void
    {
        add_settings_field(
            'consent-tracking-guard-for-wordpress-enable-pixelyoursite',
            __('PixelYourSite consent bridge', 'consent-tracking-guard-for-wordpress'),
            [$this, 'renderCheckboxField'],
            self::PAGE_SLUG,
            self::INTEGRATIONS_SECTION,
            [
                'name' => 'enable_pixelyoursite',
                'label' => __(
                    'Control PixelYourSite browser pixels, server events, cookies, and Google Consent Mode values from this consent banner.',
                    'consent-tracking-guard-for-wordpress'
                ),
            ]
        );
    }

    private function addKlaviyoField(): void
    {
        add_settings_field(
            'consent-tracking-guard-for-wordpress-enable-klaviyo',
            __('Klaviyo disclosure', 'consent-tracking-guard-for-wordpress'),
            [$this, 'renderCheckboxField'],
            self::PAGE_SLUG,
            self::INTEGRATIONS_SECTION,
            [
                'name' => 'enable_klaviyo',
                'label' => __(
                    'Show Klaviyo cookies when the Klaviyo WooCommerce plugin loads tracking outside this plugin’s control.',
                    'consent-tracking-guard-for-wordpress'
                ),
            ]
        );
    }

    private function addWoodMartField(): void
    {
        add_settings_field(
            'consent-tracking-guard-for-wordpress-enable-woodmart',
            __('WoodMart disclosure', 'consent-tracking-guard-for-wordpress'),
            [$this, 'renderCheckboxField'],
            self::PAGE_SLUG,
            self::INTEGRATIONS_SECTION,
            [
                'name' => 'enable_woodmart',
                'label' => __(
                    'Show WoodMart cookies for wishlist, compare, product history, popups, and shop preferences.',
                    'consent-tracking-guard-for-wordpress'
                ),
            ]
        );
    }

    private function addWordfenceField(): void
    {
        add_settings_field(
            'consent-tracking-guard-for-wordpress-enable-wordfence',
            __('Wordfence disclosure', 'consent-tracking-guard-for-wordpress'),
            [$this, 'renderCheckboxField'],
            self::PAGE_SLUG,
            self::INTEGRATIONS_SECTION,
            [
                'name' => 'enable_wordfence',
                'label' => __(
                    'Show Wordfence security cookies for the firewall, access controls, login alerts, and linking.',
                    'consent-tracking-guard-for-wordpress'
                ),
            ]
        );
    }

    private function addYouTubeField(): void
    {
        add_settings_field(
            'consent-tracking-guard-for-wordpress-enable-youtube',
            __('YouTube blocking', 'consent-tracking-guard-for-wordpress'),
            [$this, 'renderCheckboxField'],
            self::PAGE_SLUG,
            self::INTEGRATIONS_SECTION,
            [
                'name' => 'enable_youtube',
                'label' => __(
                    'Block and replace YouTube embeds until marketing consent is granted.',
                    'consent-tracking-guard-for-wordpress'
                ),
            ]
        );
    }

    public function renderSettingsPage(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $compatibilityNotice = $this->compatibilityNotice();

        ?>
        <div class="wrap">
            <h1>
                <?php
                esc_html_e(
                    'Viktor\'s Consent and Tracking Guard for WordPress',
                    'consent-tracking-guard-for-wordpress'
                );
                ?>
            </h1>
            <p>
                <?php
                esc_html_e(
                    'Configure the consent texts and vendor IDs used by the frontend Klaro banner.',
                    'consent-tracking-guard-for-wordpress'
                );
                ?>
            </p>

            <?php if ($compatibilityNotice !== '') : ?>
                <div class="notice notice-warning inline">
                    <p><?php printf('%s', esc_html($compatibilityNotice)); ?></p>
                </div>
            <?php endif; ?>

            <form action="options.php" method="POST">
                <?php
                settings_fields(self::OPTION_GROUP);
                do_settings_sections(self::PAGE_SLUG);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    private function compatibilityNotice(): string
    {
        if (! $this->consentApiBridge->is_api_available()) {
            return sprintf(
                '%s %s %s',
                __('WP Consent API was not detected.', 'consent-tracking-guard-for-wordpress'),
                __(
                    'The consent controls will still render, but the WordPress compatibility bridge will stay inactive',
                    'consent-tracking-guard-for-wordpress'
                ),
                __('until the API plugin is available.', 'consent-tracking-guard-for-wordpress')
            );
        }

        if (! $this->consentApiBridge->has_consent_type_conflict()) {
            return '';
        }

        return sprintf(
            '%s %s %s',
            __(
                'Another plugin already provides the WP Consent API consent type.',
                'consent-tracking-guard-for-wordpress'
            ),
            __(
                'Viktor\'s Consent and Tracking Guard for WordPress preserves that value; verify that only one consent management platform',
                'consent-tracking-guard-for-wordpress'
            ),
            __('controls the site.', 'consent-tracking-guard-for-wordpress')
        );
    }

    public function renderBannerSection(): void
    {
        printf(
            '%s %s',
            esc_html__(
                'Set the text displayed in the consent notice and preferences dialog.',
                'consent-tracking-guard-for-wordpress'
            ),
            esc_html__(
                'Use [privacy-policy] to insert WordPress’ configured Privacy Policy URL.',
                'consent-tracking-guard-for-wordpress'
            )
        );
    }

    public function renderIntegrationsSection(): void
    {
        esc_html_e('Enter only the services that this site uses.', 'consent-tracking-guard-for-wordpress');
    }

    public function renderDisplaySection(): void
    {
        esc_html_e('Control how visitors can access their privacy settings.', 'consent-tracking-guard-for-wordpress');
    }

    /**
     * @param array{name: string} $args
     */
    public function renderTextField(array $args): void
    {
        $this->renderInput($args['name'], 'regular-text', 'text');
    }

    /**
     * @param array{name: string} $args
     */
    public function renderNumberField(array $args): void
    {
        $this->renderInput($args['name'], 'small-text', 'number', ' min="1"');
    }

    /**
     * @param array{name: string} $args
     */
    public function renderTextareaField(array $args): void
    {
        $name = $args['name'];
        $options = $this->options->all();

        printf(
            '<textarea class="large-text" id="%1$s" name="%2$s[%3$s]" rows="4">%4$s</textarea>',
            esc_attr($this->fieldId($name)),
            esc_attr(Options::OPTION_NAME),
            esc_attr($name),
            esc_textarea((string) $options[$name])
        );
    }

    /**
     * @param array{name: string, label: string} $args
     */
    public function renderCheckboxField(array $args): void
    {
        $name = $args['name'];
        $options = $this->options->all();

        printf(
            '<label for="%1$s"><input id="%1$s" name="%2$s[%3$s]" type="checkbox" value="1" %4$s> %5$s</label>',
            esc_attr($this->fieldId($name)),
            esc_attr(Options::OPTION_NAME),
            esc_attr($name),
            checked((bool) $options[$name], true, false),
            esc_html($args['label'])
        );
    }

    public function renderModalStyleField(): void
    {
        $options = $this->options->all();
        $styles = [
            Options::MODAL_STYLE_KLARO_DEFAULT => __('Klaro’s default', 'consent-tracking-guard-for-wordpress'),
            Options::MODAL_STYLE_VIKTOR_DEFAULT => __('Viktor’s default', 'consent-tracking-guard-for-wordpress'),
            Options::MODAL_STYLE_LIGHT => __('Light', 'consent-tracking-guard-for-wordpress'),
            Options::MODAL_STYLE_DARK => __('Dark', 'consent-tracking-guard-for-wordpress'),
            Options::MODAL_STYLE_TWENTY_TWENTY_FIVE => __('Twenty Twenty-Five', 'consent-tracking-guard-for-wordpress'),
            Options::MODAL_STYLE_COOKIENO => __('CookieNo', 'consent-tracking-guard-for-wordpress'),
        ];

        printf(
            '<select id="%1$s" name="%2$s[modal_style]">',
            esc_attr($this->fieldId('modal_style')),
            esc_attr(Options::OPTION_NAME)
        );

        foreach ($styles as $modalStyle => $label) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr($modalStyle),
                selected((string) $options['modal_style'], $modalStyle, false),
                esc_html($label)
            );
        }

        echo '</select>'; // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- Closing select markup.
        printf(
            '<p class="description">%s</p>',
            esc_html(
                sprintf(
                    '%s %s',
                    __('Klaro’s default applies no custom modal theme;', 'consent-tracking-guard-for-wordpress'),
                    __(
                        'the component stylesheet only positions and styles plugin controls.',
                        'consent-tracking-guard-for-wordpress'
                    )
                )
            )
        );
    }

    private function addTextField(string $name, string $title, string $section): void
    {
        $this->addField($name, $title, $section, 'renderTextField');
    }

    private function addTextareaField(string $name, string $title, string $section): void
    {
        $this->addField($name, $title, $section, 'renderTextareaField');
    }

    private function addNumberField(string $name, string $title, string $section): void
    {
        $this->addField($name, $title, $section, 'renderNumberField');
    }

    private function addField(string $name, string $title, string $section, string $callback): void
    {
        add_settings_field(
            $this->fieldId($name),
            $title,
            [$this, $callback],
            self::PAGE_SLUG,
            $section,
            ['name' => $name]
        );
    }

    private function renderInput(string $name, string $cssClass, string $type, string $attributes = ''): void
    {
        $options = $this->options->all();

        printf(
            '<input class="%1$s" id="%2$s" name="%3$s[%4$s]" type="%5$s" value="%6$s"%7$s>',
            esc_attr($cssClass),
            esc_attr($this->fieldId($name)),
            esc_attr(Options::OPTION_NAME),
            esc_attr($name),
            esc_attr($type),
            esc_attr((string) $options[$name]),
            $attributes
        );
    }

    private function fieldId(string $name): string
    {
        return sprintf('consent-tracking-guard-for-wordpress-%s', str_replace('_', '-', $name));
    }
}
