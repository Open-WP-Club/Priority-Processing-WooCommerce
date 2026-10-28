<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class LimitsTest extends TestCase
{
    protected function setUp(): void
    {
        wpp_reset();
        $GLOBALS['_wpp_last_order_query_args'] = null;
    }

    private function todayCount(int $count): void
    {
        $GLOBALS['_wpp_order_query_pages'][1] = (object) ['orders' => [], 'total' => $count, 'max_num_pages' => 1];
    }

    public function test_zero_limit_is_unlimited_and_skips_query(): void
    {
        wpp_set_option('wpp_daily_limit', '0');
        $this->todayCount(500);

        $this->assertFalse(Core_Limits::is_limit_reached());
        $this->assertNull(Core_Limits::get_remaining());
        $this->assertNull($GLOBALS['_wpp_last_order_query_args']);
    }

    public function test_below_at_and_above_limit(): void
    {
        wpp_set_option('wpp_daily_limit', '5');

        $this->todayCount(4);
        $this->assertFalse(Core_Limits::is_limit_reached());
        $this->assertSame(1, Core_Limits::get_remaining());

        $GLOBALS['_wpp_transients'] = [];
        $this->todayCount(5);
        $this->assertTrue(Core_Limits::is_limit_reached());
        $this->assertSame(0, Core_Limits::get_remaining());

        $GLOBALS['_wpp_transients'] = [];
        $this->todayCount(9);
        $this->assertTrue(Core_Limits::is_limit_reached());
        $this->assertSame(0, Core_Limits::get_remaining());
    }

    public function test_negative_or_garbage_limit_is_unlimited(): void
    {
        wpp_set_option('wpp_daily_limit', '-3');
        $this->assertSame(0, Core_Limits::get_limit());
        wpp_set_option('wpp_daily_limit', 'abc');
        $this->assertFalse(Core_Limits::is_limit_reached());
    }

    public function test_query_excludes_cancelled_failed_refunded_and_filters_meta(): void
    {
        wpp_set_option('wpp_daily_limit', '5');
        $this->todayCount(1);
        Core_Limits::get_today_count();

        $args = $GLOBALS['_wpp_last_order_query_args'];
        $this->assertSame('_priority_processing', $args['meta_key']);
        $this->assertSame('yes', $args['meta_value']);
        $this->assertContains('wc-processing', $args['status']);
        foreach (['wc-cancelled', 'wc-failed', 'wc-refunded', 'trash'] as $status) {
            $this->assertNotContains($status, $args['status']);
        }
    }

    public function test_day_boundaries_use_store_timezone(): void
    {
        $GLOBALS['_wpp_timezone'] = 'Pacific/Auckland';
        $this->todayCount(0);
        Core_Limits::get_today_count();

        [$start, $end] = array_map('intval', explode('...', $GLOBALS['_wpp_last_order_query_args']['date_created']));
        $tz = new DateTimeZone('Pacific/Auckland');

        $this->assertSame('00:00:00', (new DateTimeImmutable('@' . $start))->setTimezone($tz)->format('H:i:s'));
        $this->assertSame('23:59:59', (new DateTimeImmutable('@' . $end))->setTimezone($tz)->format('H:i:s'));
        $this->assertSame(
            (new DateTimeImmutable('@' . $start))->setTimezone($tz)->format('Y-m-d'),
            (new DateTimeImmutable('@' . $end))->setTimezone($tz)->format('Y-m-d')
        );
    }

    public function test_count_is_cached_until_flushed(): void
    {
        wpp_set_option('wpp_daily_limit', '5');
        $this->todayCount(2);
        $this->assertSame(2, Core_Limits::get_today_count());

        $this->todayCount(5);
        $this->assertSame(2, Core_Limits::get_today_count());

        Core_Limits::flush_cache();
        $this->assertSame(5, Core_Limits::get_today_count());
    }

    public function test_message_is_not_empty(): void
    {
        $this->assertNotSame('', Core_Limits::get_limit_message());
    }
}
