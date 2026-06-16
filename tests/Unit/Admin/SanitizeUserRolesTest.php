<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class SanitizeUserRolesTest extends TestCase
{
    private Admin_Settings $settings;

    protected function setUp(): void
    {
        wpp_reset();
        $this->settings = new Admin_Settings();
    }

    public function test_empty_array_returns_default_customer(): void
    {
        $this->assertSame(['customer'], $this->settings->sanitize_user_roles([]));
    }

    public function test_null_returns_default_customer(): void
    {
        $this->assertSame(['customer'], $this->settings->sanitize_user_roles(null));
    }

    public function test_valid_roles_array_is_preserved(): void
    {
        $result = $this->settings->sanitize_user_roles(['customer', 'subscriber']);
        $this->assertSame(['customer', 'subscriber'], $result);
    }

    public function test_string_is_converted_to_array(): void
    {
        $result = $this->settings->sanitize_user_roles('customer');
        $this->assertSame(['customer'], $result);
    }

    public function test_html_tags_are_stripped(): void
    {
        $result = $this->settings->sanitize_user_roles(['<script>admin</script>']);
        $this->assertSame(['admin'], $result);
    }

    public function test_empty_strings_in_array_are_removed(): void
    {
        $result = $this->settings->sanitize_user_roles(['customer', '', 'subscriber']);
        $this->assertSame(['customer', 'subscriber'], $result);
    }

    public function test_array_with_only_empty_strings_returns_default(): void
    {
        $result = $this->settings->sanitize_user_roles(['', '']);
        $this->assertSame(['customer'], $result);
    }

    public function test_roles_are_trimmed(): void
    {
        $result = $this->settings->sanitize_user_roles(['  customer  ']);
        $this->assertSame(['customer'], $result);
    }
}
