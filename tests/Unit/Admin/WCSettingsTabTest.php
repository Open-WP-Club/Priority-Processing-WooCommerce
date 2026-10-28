<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class WCSettingsTabTest extends TestCase
{
    private Admin_WC_Settings_Tab $tab;

    protected function setUp(): void
    {
        wpp_reset();
        $_GET = [];
        $this->tab = new Admin_WC_Settings_Tab(new Admin_Settings());
    }

    protected function tearDown(): void
    {
        $_GET = [];
    }

    public function test_tab_url(): void
    {
        $this->assertSame(
            'http://example.com/wp-admin/admin.php?page=wc-settings&tab=priority-processing',
            Admin_WC_Settings_Tab::get_tab_url()
        );
        $this->assertStringEndsWith('&section=display', Admin_WC_Settings_Tab::get_tab_url('display'));
    }

    public function test_register_page_leaves_pages_untouched_without_woocommerce(): void
    {
        if (class_exists('WC_Settings_Page', false)) {
            $this->markTestSkipped('WC_Settings_Page available.');
        }
        $this->assertSame(['x'], $this->tab->register_page(['x']));
    }

    public function test_register_page_appends_settings_page(): void
    {
        if (!class_exists('WC_Settings_Page', false)) {
            eval('class WC_Settings_Page { public $id; public $label; public function __construct() {} }');
        }

        $pages = $this->tab->register_page([]);

        $this->assertCount(1, $pages);
        $this->assertInstanceOf(WPP_WC_Settings_Page::class, $pages[0]);
        $this->assertSame('priority-processing', $pages[0]->id);
    }

    public function test_sections(): void
    {
        $this->assertSame(
            ['', 'access', 'display', 'messages', 'badge', 'statistics'],
            array_keys(Admin_WC_Settings_Tab::get_sections())
        );
    }

    public function test_every_section_has_fields_with_balanced_title_and_sectionend(): void
    {
        foreach (array_keys(Admin_WC_Settings_Tab::get_sections()) as $section) {
            $types = array_column(Admin_WC_Settings_Tab::get_fields($section), 'type');
            $this->assertSame('title', $types[0], "section '$section'");
            $this->assertSame('sectionend', end($types), "section '$section'");
        }
    }

    public function test_new_options_are_defined(): void
    {
        $general = array_column(Admin_WC_Settings_Tab::get_fields(''), null, 'id');
        $display = array_column(Admin_WC_Settings_Tab::get_fields('display'), null, 'id');

        $this->assertSame('number', $general['wpp_daily_limit']['type']);
        $this->assertSame('0', $general['wpp_daily_limit']['custom_attributes']['min']);
        $this->assertSame('wpp_toggle', $display['wpp_cart_button_enabled']['type']);
    }

    public function test_all_plugin_options_have_a_field(): void
    {
        $ids = [];
        foreach (array_keys(Admin_WC_Settings_Tab::get_sections()) as $section) {
            $ids = array_merge($ids, array_column(Admin_WC_Settings_Tab::get_fields($section), 'id'));
        }

        foreach ([
            'wpp_enabled', 'wpp_fee_amount', 'wpp_checkbox_label', 'wpp_description', 'wpp_fee_label',
            'wpp_section_title', 'wpp_allowed_user_roles', 'wpp_allow_guests', 'wpp_min_order_amount',
            'wpp_cutoff_time', 'wpp_cart_message_enabled', 'wpp_cart_messages', 'wpp_cart_message_mode',
            'wpp_cart_message_threshold', 'wpp_product_message_enabled', 'wpp_product_messages',
            'wpp_product_message_mode', 'wpp_product_message_threshold', 'wpp_account_badge_enabled',
            'wpp_account_badge_label', 'wpp_account_badge_message', 'wpp_daily_limit', 'wpp_cart_button_enabled',
        ] as $option) {
            $this->assertContains($option, $ids, $option);
        }
    }

    public function test_sanitize_option_toggle_uses_raw_value(): void
    {
        $field = ['id' => 'wpp_enabled', 'type' => 'wpp_toggle'];
        $this->assertSame('1', $this->tab->sanitize_option('yes', $field, '1'));
        $this->assertSame('0', $this->tab->sanitize_option('yes', $field, '0'));
        $this->assertSame('0', $this->tab->sanitize_option('', $field, null));
    }

    public function test_sanitize_option_roles_array_and_empty_fallback(): void
    {
        $field = ['id' => 'wpp_allowed_user_roles', 'type' => 'multiselect'];
        $this->assertSame(['customer', 'subscriber'], $this->tab->sanitize_option(['customer', 'subscriber'], $field, []));
        $this->assertSame(['customer'], $this->tab->sanitize_option([], $field, []));
    }

    public function test_sanitize_option_messages_and_daily_limit(): void
    {
        $this->assertSame(
            "One\nTwo",
            $this->tab->sanitize_option("One\r\n\r\nTwo\n", ['id' => 'wpp_cart_messages', 'type' => 'textarea'], '')
        );
        $this->assertSame('0', $this->tab->sanitize_option('-3', ['id' => 'wpp_daily_limit', 'type' => 'number'], '-3'));
        $this->assertSame('', $this->tab->sanitize_option('25:99', ['id' => 'wpp_cutoff_time', 'type' => 'time'], ''));
    }

    public function test_legacy_page_redirects_to_tab(): void
    {
        $_GET['page'] = 'woo-priority-processing';
        $this->assertSame(Admin_WC_Settings_Tab::get_tab_url(), Admin_WC_Settings_Tab::get_legacy_redirect_url());

        $_GET['page'] = 'wc-settings';
        $this->assertNull(Admin_WC_Settings_Tab::get_legacy_redirect_url());
    }

    public function test_is_tab_request(): void
    {
        $this->assertFalse(Admin_WC_Settings_Tab::is_tab_request());
        $_GET['tab'] = 'priority-processing';
        $this->assertTrue(Admin_WC_Settings_Tab::is_tab_request());
    }

    public function test_sanitize_option_ignores_fields_that_were_not_posted(): void
    {
        // WooCommerce passes null for fields missing from the request and then skips saving them.
        foreach (array_keys($this->tab->get_sanitizers()) as $id) {
            $this->assertNull($this->tab->sanitize_option(null, ['id' => $id, 'type' => 'text'], null), $id);
        }
    }
}
