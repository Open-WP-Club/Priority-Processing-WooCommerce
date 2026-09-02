<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class CalculatePriorityRevenueTest extends TestCase
{
    private Core_Statistics $statistics;
    private ReflectionMethod $method;

    protected function setUp(): void
    {
        wpp_reset();
        $this->statistics = new Core_Statistics();
        $this->method     = new ReflectionMethod(Core_Statistics::class, 'calculate_priority_revenue');
        wpp_set_option('wpp_fee_label', 'Priority Processing & Express Shipping');
    }

    /** @param array<string, mixed> $stats */
    private function invoke(array $stats): array
    {
        return $this->method->invoke($this->statistics, $stats);
    }

    private function baseStats(int $totalPriorityOrders, int $totalOrders = 10): array
    {
        return [
            'total_priority_orders'  => $totalPriorityOrders,
            'total_orders'           => $totalOrders,
            'total_priority_revenue' => 999.0, // sentinel to detect whether it gets overwritten
            'priority_percentage'    => 0.0,
            'average_priority_fee'   => 0.0,
        ];
    }

    private function feeOrder(int $id, array $fees): WC_Order
    {
        $order = new WC_Order();
        $order->set_id($id);
        foreach ($fees as [$name, $total]) {
            $fee = new WC_Order_Item_Fee();
            $fee->set_name($name);
            $fee->set_total($total);
            $order->add_item($fee);
        }
        return $order;
    }

    public function test_zero_priority_orders_skips_the_query_and_leaves_revenue_untouched(): void
    {
        $result = $this->invoke($this->baseStats(0));

        // apply_derived_stats() never touches total_priority_revenue, so the
        // sentinel surviving proves the wc_get_orders() loop was skipped.
        $this->assertSame(999.0, $result['total_priority_revenue']);
        $this->assertSame(0.0, $result['priority_percentage']);
    }

    public function test_sums_only_fees_with_an_exact_legacy_label(): void
    {
        $order = $this->feeOrder(1, [
            ['Priority Processing & Express Shipping', 5.0], // exact label match
            ['Priority Rush', 7.5],                          // partial match, must be excluded
            ['Gift Wrap', 2.0],                               // unrelated fee, must be excluded
        ]);

        $GLOBALS['_wpp_order_query_pages'][1] = (object) [
            'orders'        => [$order],
            'total'         => 1,
            'max_num_pages' => 1,
        ];

        $result = $this->invoke($this->baseStats(1));

        $this->assertSame(5.0, $result['total_priority_revenue']);
        $this->assertSame(5.0, $result['average_priority_fee']);
    }

    public function test_sums_marked_fee_after_its_label_changes(): void
    {
        $order = new WC_Order();
        $fee   = new WC_Order_Item_Fee();
        $fee->set_name('Old custom rush label');
        $fee->set_total(7.5);
        $fee->add_meta_data(Frontend_Fees::FEE_META_KEY, Frontend_Fees::FEE_META_VALUE, true);
        $order->add_item($fee);

        $GLOBALS['_wpp_order_query_pages'][1] = (object) [
            'orders' => [$order], 'total' => 1, 'max_num_pages' => 1,
        ];

        $result = $this->invoke($this->baseStats(1));

        $this->assertSame(7.5, $result['total_priority_revenue']);
        $this->assertSame(7.5, $result['average_priority_fee']);
    }

    public function test_prefers_recorded_priority_amount_over_fee_label(): void
    {
        $order = $this->feeOrder(1, [['Unrelated fee', 99.0]]);
        $order->update_meta_data('_priority_fee_amount', 8.25);

        $GLOBALS['_wpp_order_query_pages'][1] = (object) [
            'orders' => [$order], 'total' => 1, 'max_num_pages' => 1,
        ];

        $result = $this->invoke($this->baseStats(1));

        $this->assertSame(8.25, $result['total_priority_revenue']);
        $this->assertSame(8.25, $result['average_priority_fee']);
    }

    public function test_paginates_across_multiple_pages(): void
    {
        $orderPage1 = $this->feeOrder(1, [['Priority Processing & Express Shipping', 5.0]]);
        $orderPage2 = $this->feeOrder(2, [['Priority Processing & Express Shipping', 5.0]]);

        $GLOBALS['_wpp_order_query_pages'][1] = (object) [
            'orders' => [$orderPage1], 'total' => 2, 'max_num_pages' => 2,
        ];
        $GLOBALS['_wpp_order_query_pages'][2] = (object) [
            'orders' => [$orderPage2], 'total' => 2, 'max_num_pages' => 2,
        ];

        $result = $this->invoke($this->baseStats(2));

        $this->assertSame(10.0, $result['total_priority_revenue']);
    }

    public function test_priority_percentage_uses_total_orders(): void
    {
        $GLOBALS['_wpp_order_query_pages'][1] = (object) [
            'orders' => [], 'total' => 5, 'max_num_pages' => 1,
        ];

        $result = $this->invoke($this->baseStats(5, 20));

        $this->assertSame(25.0, $result['priority_percentage']);
    }

    public function test_query_failure_is_caught_and_leaves_revenue_at_zero(): void
    {
        $GLOBALS['_wpp_wc_get_orders_throws'] = true;

        $result = $this->invoke($this->baseStats(1));

        $this->assertSame(0.0, $result['total_priority_revenue']);
        $this->assertSame(0.0, $result['average_priority_fee']);
    }
}
