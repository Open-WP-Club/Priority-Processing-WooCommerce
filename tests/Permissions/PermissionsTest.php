<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class PermissionsTest extends TestCase
{
    protected function setUp(): void
    {
        wpp_perm_reset();
    }

    public function test_shop_manager_capability_always_has_access(): void
    {
        $GLOBALS['_wpp_logged_in']         = true;
        $GLOBALS['_wpp_current_user_caps'] = ['manage_woocommerce'];
        wpp_perm_set_option('wpp_allowed_user_roles', []);

        $this->assertTrue(Core_Permissions::can_access_priority_processing());
    }

    public function test_administrator_capability_always_has_access(): void
    {
        $GLOBALS['_wpp_logged_in']         = true;
        $GLOBALS['_wpp_current_user_caps'] = ['administrator'];
        wpp_perm_set_option('wpp_allowed_user_roles', []);

        $this->assertTrue(Core_Permissions::can_access_priority_processing());
    }

    public function test_guest_allowed_when_option_enabled(): void
    {
        $GLOBALS['_wpp_logged_in'] = false;
        wpp_perm_set_option('wpp_allow_guests', '1');

        $this->assertTrue(Core_Permissions::can_access_priority_processing());
    }

    public function test_guest_denied_when_option_disabled(): void
    {
        $GLOBALS['_wpp_logged_in'] = false;
        wpp_perm_set_option('wpp_allow_guests', '0');

        $this->assertFalse(Core_Permissions::can_access_priority_processing());
    }

    public function test_logged_in_user_with_allowed_role_has_access(): void
    {
        $GLOBALS['_wpp_logged_in']          = true;
        $GLOBALS['_wpp_current_user_roles'] = ['customer'];
        wpp_perm_set_option('wpp_allowed_user_roles', ['customer']);

        $this->assertTrue(Core_Permissions::can_access_priority_processing());
    }

    public function test_logged_in_user_without_allowed_role_is_denied(): void
    {
        $GLOBALS['_wpp_logged_in']          = true;
        $GLOBALS['_wpp_current_user_roles'] = ['subscriber'];
        wpp_perm_set_option('wpp_allowed_user_roles', ['customer']);

        $this->assertFalse(Core_Permissions::can_access_priority_processing());
    }

    public function test_empty_allowed_roles_allows_any_logged_in_user(): void
    {
        $GLOBALS['_wpp_logged_in']          = true;
        $GLOBALS['_wpp_current_user_roles'] = ['subscriber'];
        wpp_perm_set_option('wpp_allowed_user_roles', []);

        $this->assertTrue(Core_Permissions::can_access_priority_processing());
    }

    public function test_user_can_access_returns_false_for_unknown_user(): void
    {
        $this->assertFalse(Core_Permissions::user_can_access(999));
    }

    public function test_user_can_access_true_for_shop_manager_capability(): void
    {
        $GLOBALS['_wpp_users'][42]        = (object) ['ID' => 42, 'roles' => ['shop_manager']];
        $GLOBALS['_wpp_user_caps'][42]    = ['manage_woocommerce'];

        $this->assertTrue(Core_Permissions::user_can_access(42));
    }

    public function test_user_can_access_checks_allowed_roles(): void
    {
        $GLOBALS['_wpp_users'][7] = (object) ['ID' => 7, 'roles' => ['customer']];
        wpp_perm_set_option('wpp_allowed_user_roles', ['customer']);

        $this->assertTrue(Core_Permissions::user_can_access(7));
    }

    public function test_user_can_access_denies_role_not_allowed(): void
    {
        $GLOBALS['_wpp_users'][8] = (object) ['ID' => 8, 'roles' => ['subscriber']];
        wpp_perm_set_option('wpp_allowed_user_roles', ['customer']);

        $this->assertFalse(Core_Permissions::user_can_access(8));
    }

    public function test_priority_cannot_be_enabled_when_feature_is_disabled(): void
    {
        wpp_perm_set_option('wpp_enabled', '0');
        wpp_perm_set_option('wpp_allow_guests', '1');

        $this->assertFalse(Core_Permissions::can_enable_priority_processing());
    }

    public function test_priority_can_be_enabled_when_feature_and_access_are_allowed(): void
    {
        wpp_perm_set_option('wpp_enabled', '1');
        wpp_perm_set_option('wpp_allow_guests', '1');
        wpp_perm_set_option('wpp_min_order_amount', '0');

        $this->assertTrue(Core_Permissions::can_enable_priority_processing());
    }
}
