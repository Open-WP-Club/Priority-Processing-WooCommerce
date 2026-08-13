<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class GetPriorityStatusFromRequestTest extends TestCase
{
    private Frontend_AJAX $ajax;
    private ReflectionMethod $method;

    protected function setUp(): void
    {
        wpp_reset();
        $this->ajax   = new Frontend_AJAX();
        $this->method = new ReflectionMethod(Frontend_AJAX::class, 'get_priority_status_from_request');
    }

    private function invoke(): bool
    {
        return $this->method->invoke($this->ajax);
    }

    public function test_false_when_neither_param_present(): void
    {
        $_POST = [];
        $this->assertFalse($this->invoke());
    }

    public function test_block_checkout_true_string(): void
    {
        $_POST = ['priority_enabled' => 'true'];
        $this->assertTrue($this->invoke());
    }

    public function test_block_checkout_one_string(): void
    {
        $_POST = ['priority_enabled' => '1'];
        $this->assertTrue($this->invoke());
    }

    public function test_block_checkout_false_string_is_false(): void
    {
        $_POST = ['priority_enabled' => 'false'];
        $this->assertFalse($this->invoke());
    }

    public function test_classic_checkout_param_used_when_block_param_absent(): void
    {
        $_POST = ['priority' => '1'];
        $this->assertTrue($this->invoke());
    }

    public function test_classic_checkout_param_used_as_fallback_when_block_param_is_false(): void
    {
        $_POST = ['priority_enabled' => 'false', 'priority' => '1'];
        $this->assertTrue($this->invoke());
    }

    public function test_block_param_true_takes_precedence_without_checking_classic(): void
    {
        $_POST = ['priority_enabled' => 'true', 'priority' => '0'];
        $this->assertTrue($this->invoke());
    }

    public function test_classic_checkout_false_string_is_false(): void
    {
        $_POST = ['priority' => 'false'];
        $this->assertFalse($this->invoke());
    }
}
