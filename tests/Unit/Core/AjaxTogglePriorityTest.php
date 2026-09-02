<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class AjaxTogglePriorityTest extends TestCase
{
    private Core_Orders $orders;

    protected function setUp(): void
    {
        wpp_reset();
        $this->orders = new Core_Orders();
    }

    private function postDefaults(array $overrides = []): void
    {
        $_POST = array_merge([
            'nonce'           => 'test_nonce',
            'order_id'        => 1,
            'priority_action' => 'add',
        ], $overrides);
    }

    private function registerOrder(int $id = 1, string $status = 'processing', string $priorityMeta = ''): WC_Order
    {
        $order = new WC_Order();
        $order->set_id($id);
        $order->set_status($status);
        if ('' !== $priorityMeta) {
            $order->update_meta_data('_priority_processing', $priorityMeta);
        }
        wpp_register_order($order);
        return $order;
    }

    // -- guard clauses ------------------------------------------------------

    public function test_denies_when_user_lacks_capability(): void
    {
        $GLOBALS['_wpp_user_can'] = false;
        $this->postDefaults();

        try {
            $this->orders->ajax_toggle_order_priority();
            $this->fail('Expected WPP_Json_Error to be thrown');
        } catch (WPP_Json_Error $e) {
            $this->assertSame('Permission denied', $e->data);
        }
    }

    public function test_denies_when_nonce_invalid(): void
    {
        $GLOBALS['_wpp_nonce_valid'] = false;
        $this->postDefaults();

        try {
            $this->orders->ajax_toggle_order_priority();
            $this->fail('Expected WPP_Json_Error to be thrown');
        } catch (WPP_Json_Error $e) {
            $this->assertSame('Invalid nonce', $e->data);
        }
    }

    public function test_denies_when_order_id_missing(): void
    {
        $this->postDefaults(['order_id' => 0]);

        try {
            $this->orders->ajax_toggle_order_priority();
            $this->fail('Expected WPP_Json_Error to be thrown');
        } catch (WPP_Json_Error $e) {
            $this->assertSame('Invalid parameters', $e->data);
        }
    }

    public function test_denies_when_action_not_add_or_remove(): void
    {
        $this->postDefaults(['priority_action' => 'delete']);

        try {
            $this->orders->ajax_toggle_order_priority();
            $this->fail('Expected WPP_Json_Error to be thrown');
        } catch (WPP_Json_Error $e) {
            $this->assertSame('Invalid parameters', $e->data);
        }
    }

    public function test_denies_when_order_not_found(): void
    {
        $this->postDefaults(['order_id' => 999]);

        try {
            $this->orders->ajax_toggle_order_priority();
            $this->fail('Expected WPP_Json_Error to be thrown');
        } catch (WPP_Json_Error $e) {
            $this->assertSame('Order not found', $e->data);
        }
    }

    public function test_denies_when_order_status_is_terminal(): void
    {
        $this->registerOrder(1, 'completed');
        $this->postDefaults();

        try {
            $this->orders->ajax_toggle_order_priority();
            $this->fail('Expected WPP_Json_Error to be thrown');
        } catch (WPP_Json_Error $e) {
            $this->assertSame('Cannot modify this order status', $e->data);
        }
    }

    public function test_denies_add_when_order_already_has_priority(): void
    {
        $this->registerOrder(1, 'processing', 'yes');
        $this->postDefaults(['priority_action' => 'add']);

        try {
            $this->orders->ajax_toggle_order_priority();
            $this->fail('Expected WPP_Json_Error to be thrown');
        } catch (WPP_Json_Error $e) {
            $this->assertSame('Order already has priority processing', $e->data);
        }
    }

    // -- happy paths ----------------------------------------------------

    public function test_add_sets_meta_adds_fee_and_note(): void
    {
        $order = $this->registerOrder(1, 'processing');
        wpp_set_option('wpp_fee_amount', '5.00');
        wpp_set_option('wpp_fee_label', 'Priority Processing & Express Shipping');
        $this->postDefaults(['priority_action' => 'add']);

        try {
            $this->orders->ajax_toggle_order_priority();
            $this->fail('Expected WPP_Json_Success to be thrown');
        } catch (WPP_Json_Success $e) {
            $this->assertSame('add', $e->data['action']);
            $this->assertTrue($e->data['has_priority']);
        }

        $this->assertSame('yes', $order->get_meta('_priority_processing'));
		$this->assertSame('yes', $order->get_meta('_requires_express_shipping'));
		$this->assertSame(5.0, $order->get_meta('_priority_fee_amount'));
		$this->assertSame('express', $order->get_meta('_priority_service_level'));
        $this->assertCount(1, $order->get_fees());
		$fee = array_values($order->get_fees())[0];
		$this->assertSame('yes', $fee->get_meta(Frontend_Fees::FEE_META_KEY, true));
        $this->assertNotEmpty($order->notes);
        $this->assertTrue($order->saved);
    }

    public function test_add_skips_fee_item_when_fee_amount_is_zero(): void
    {
        $order = $this->registerOrder(1, 'processing');
        wpp_set_option('wpp_fee_amount', '0');
        $this->postDefaults(['priority_action' => 'add']);

        try {
            $this->orders->ajax_toggle_order_priority();
            $this->fail('Expected WPP_Json_Success to be thrown');
        } catch (WPP_Json_Success $e) {
            // expected
        }

        $this->assertSame('yes', $order->get_meta('_priority_processing'));
        $this->assertCount(0, $order->get_fees());
    }

    public function test_remove_clears_meta_removes_fee_and_note(): void
    {
        $order = $this->registerOrder(1, 'processing', 'yes');
        wpp_set_option('wpp_fee_label', 'Priority Processing & Express Shipping');

        $fee = new WC_Order_Item_Fee();
        $fee->set_name('Priority Processing & Express Shipping');
        $fee->set_total(5.0);
		$fee->add_meta_data(Frontend_Fees::FEE_META_KEY, Frontend_Fees::FEE_META_VALUE, true);
        $order->add_item($fee);
		$order->update_meta_data('_requires_express_shipping', 'yes');
		$order->update_meta_data('_priority_fee_amount', 5.0);
		$order->update_meta_data('_priority_service_level', 'express');

        $this->postDefaults(['priority_action' => 'remove']);

        try {
            $this->orders->ajax_toggle_order_priority();
            $this->fail('Expected WPP_Json_Success to be thrown');
        } catch (WPP_Json_Success $e) {
            $this->assertSame('remove', $e->data['action']);
            $this->assertFalse($e->data['has_priority']);
        }

        $this->assertSame('', $order->get_meta('_priority_processing'));
		$this->assertSame('', $order->get_meta('_requires_express_shipping'));
		$this->assertSame('', $order->get_meta('_priority_fee_amount'));
		$this->assertSame('', $order->get_meta('_priority_service_level'));
        $this->assertCount(0, $order->get_fees());
        $this->assertNotEmpty($order->notes);
        $this->assertTrue($order->saved);
    }

	public function test_remove_keeps_unrelated_fee_containing_priority_in_its_name(): void
	{
		$order = $this->registerOrder(1, 'processing', 'yes');
		wpp_set_option('wpp_fee_label', 'Rush Fee');

		$fee = new WC_Order_Item_Fee();
		$fee->set_name('Priority Gift Packaging');
		$fee->set_total(5.0);
		$order->add_item($fee);

		$this->postDefaults(['priority_action' => 'remove']);

		try {
			$this->orders->ajax_toggle_order_priority();
			$this->fail('Expected WPP_Json_Success to be thrown');
		} catch (WPP_Json_Success $e) {
			// expected
		}

		$this->assertCount(1, $order->get_fees());
	}
}
