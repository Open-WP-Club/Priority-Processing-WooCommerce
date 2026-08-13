<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class CheckPermissionTest extends TestCase
{
    private WPP_REST_Controller $controller;

    protected function setUp(): void
    {
        wpp_reset();
        $this->controller = new WPP_REST_Controller();
    }

    public function test_allows_when_user_can_manage_woocommerce(): void
    {
        $GLOBALS['_wpp_user_can'] = true;
        $this->assertTrue($this->controller->check_permission());
    }

    public function test_denies_when_user_cannot_manage_woocommerce(): void
    {
        $GLOBALS['_wpp_user_can'] = false;
        $this->assertFalse($this->controller->check_permission());
    }
}
