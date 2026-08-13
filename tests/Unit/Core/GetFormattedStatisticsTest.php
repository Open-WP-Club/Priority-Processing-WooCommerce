<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class GetFormattedStatisticsTest extends TestCase
{
    private Core_Statistics $statistics;

    protected function setUp(): void
    {
        wpp_reset();
        $this->statistics = new Core_Statistics();
    }

    private function stats(array $overrides = []): array
    {
        return array_merge([
            'total_priority_orders'      => 1234,
            'total_priority_revenue'     => 6170.5,
            'today_priority_orders'      => 3,
            'this_week_priority_orders'  => 20,
            'this_month_priority_orders' => 80,
            'priority_percentage'        => 12.5,
            'average_priority_fee'       => 5.0,
            'last_updated'               => '2024-01-15 10:30:00',
        ], $overrides);
    }

    public function test_large_numbers_get_thousands_separators(): void
    {
        $result = $this->statistics->get_formatted_statistics($this->stats());
        $this->assertSame('1,234', $result['total_priority_orders']);
    }

    public function test_revenue_and_average_fee_are_formatted_as_price(): void
    {
        $result = $this->statistics->get_formatted_statistics($this->stats());
        $this->assertSame('$6,170.50', $result['total_priority_revenue']);
        $this->assertSame('$5.00', $result['average_priority_fee']);
    }

    public function test_percentage_gets_percent_suffix(): void
    {
        $result = $this->statistics->get_formatted_statistics($this->stats(['priority_percentage' => 33.3]));
        $this->assertSame('33.3%', $result['priority_percentage']);
    }

    public function test_last_updated_is_reformatted_as_datetime(): void
    {
        $result = $this->statistics->get_formatted_statistics($this->stats());
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result['last_updated']);
    }

    public function test_all_expected_keys_present(): void
    {
        $result = $this->statistics->get_formatted_statistics($this->stats());

        foreach ([
            'total_priority_orders', 'total_priority_revenue', 'today_priority_orders',
            'this_week_priority_orders', 'this_month_priority_orders',
            'priority_percentage', 'average_priority_fee', 'last_updated',
        ] as $key) {
            $this->assertArrayHasKey($key, $result, "Missing key: $key");
        }
    }
}
