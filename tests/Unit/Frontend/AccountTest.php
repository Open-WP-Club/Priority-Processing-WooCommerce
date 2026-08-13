<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class AccountTest extends TestCase
{
    private Frontend_Account $account;

    protected function setUp(): void
    {
        wpp_reset();
        $this->account = new Frontend_Account();
    }

    private function priorityOrder(): WC_Order {
        $order = new WC_Order();
        $order->set_meta('_priority_processing', 'yes');
        return $order;
    }

    private function nonPriorityOrder(): WC_Order {
        return new WC_Order();
    }

    public function test_outputs_nothing_when_badge_disabled(): void
    {
        wpp_set_option('wpp_account_badge_enabled', '0');

        ob_start();
        $this->account->render_badge($this->priorityOrder());
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    public function test_outputs_nothing_for_non_priority_order(): void
    {
        wpp_set_option('wpp_account_badge_enabled', '1');

        ob_start();
        $this->account->render_badge($this->nonPriorityOrder());
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    public function test_outputs_nothing_on_the_thank_you_page(): void
    {
        wpp_set_option('wpp_account_badge_enabled', '1');
        $GLOBALS['_wpp_is_order_received_page'] = true;

        ob_start();
        $this->account->render_badge($this->priorityOrder());
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    public function test_outputs_badge_for_priority_order_on_view_order_page(): void
    {
        wpp_set_option('wpp_account_badge_enabled', '1');
        wpp_set_option('wpp_account_badge_label', 'Priority Active');
        wpp_set_option('wpp_account_badge_message', 'Shipping fast!');
        $GLOBALS['_wpp_is_order_received_page'] = false;

        ob_start();
        $this->account->render_badge($this->priorityOrder());
        $output = ob_get_clean();

        $this->assertStringContainsString('Priority Active', $output);
        $this->assertStringContainsString('Shipping fast!', $output);
    }

    public function test_omits_message_paragraph_when_message_is_blank(): void
    {
        wpp_set_option('wpp_account_badge_enabled', '1');
        wpp_set_option('wpp_account_badge_label', 'Priority Active');
        wpp_set_option('wpp_account_badge_message', '   ');

        ob_start();
        $this->account->render_badge($this->priorityOrder());
        $output = ob_get_clean();

        $this->assertStringContainsString('Priority Active', $output);
        $this->assertStringNotContainsString('<p', $output);
    }

    public function test_default_label_and_message_are_non_empty(): void
    {
        $this->assertNotSame('', Frontend_Account::get_default_label());
        $this->assertNotSame('', Frontend_Account::get_default_message());
    }
}
