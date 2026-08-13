<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class SavePriorityFieldTest extends TestCase
{
    private Frontend_Checkout $checkout;

    protected function setUp(): void
    {
        wpp_reset();
        $this->checkout = new Frontend_Checkout();
    }

    public function test_session_untouched_when_nonce_field_missing(): void
    {
        $_POST = ['priority_processing' => '1'];

        $this->checkout->save_priority_field(123);

        $this->assertNull(WC()->session->get('priority_processing'));
    }

    public function test_session_untouched_when_nonce_invalid(): void
    {
        $GLOBALS['_wpp_nonce_valid'] = false;
        $_POST = [
            'woocommerce-process-checkout-nonce' => 'bad_nonce',
            'priority_processing'                => '1',
        ];

        $this->checkout->save_priority_field(123);

        $this->assertNull(WC()->session->get('priority_processing'));
    }

    public function test_session_set_true_when_checked_with_valid_nonce(): void
    {
        $_POST = [
            'woocommerce-process-checkout-nonce' => 'valid_nonce',
            'priority_processing'                => '1',
        ];

        $this->checkout->save_priority_field(123);

        $this->assertTrue(WC()->session->get('priority_processing'));
    }

    public function test_session_set_false_when_checkbox_not_submitted(): void
    {
        $_POST = [
            'woocommerce-process-checkout-nonce' => 'valid_nonce',
        ];

        $this->checkout->save_priority_field(123);

        $this->assertFalse(WC()->session->get('priority_processing'));
    }

    public function test_session_set_false_when_checkbox_value_is_not_one(): void
    {
        $_POST = [
            'woocommerce-process-checkout-nonce' => 'valid_nonce',
            'priority_processing'                => '0',
        ];

        $this->checkout->save_priority_field(123);

        $this->assertFalse(WC()->session->get('priority_processing'));
    }
}
