<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ApplyDerivedStatsTest extends TestCase
{
    private Core_Statistics $statistics;
    private ReflectionMethod $method;

    protected function setUp(): void
    {
        wpp_reset();
        $this->statistics = new Core_Statistics();
        $this->method     = new ReflectionMethod(Core_Statistics::class, 'apply_derived_stats');
    }

    /** @param array<string, mixed> $stats */
    private function invoke(array $stats, float $totalFeeAmount, int $feeCount): array
    {
        $stats += ['priority_percentage' => 0.0, 'average_priority_fee' => 0.0];
        return $this->method->invoke($this->statistics, $stats, $totalFeeAmount, $feeCount);
    }

    public function test_percentage_and_average_are_calculated(): void
    {
        $result = $this->invoke(['total_orders' => 200, 'total_priority_orders' => 50], 250.0, 50);

        $this->assertSame(25.0, $result['priority_percentage']);
        $this->assertSame(5.0, $result['average_priority_fee']);
    }

    public function test_zero_total_orders_leaves_percentage_at_default(): void
    {
        $result = $this->invoke(['total_orders' => 0, 'total_priority_orders' => 0], 0.0, 0);

        $this->assertSame(0.0, $result['priority_percentage']);
    }

    public function test_zero_fee_count_leaves_average_at_default(): void
    {
        $result = $this->invoke(['total_orders' => 100, 'total_priority_orders' => 10], 0.0, 0);

        $this->assertSame(10.0, $result['priority_percentage']);
        $this->assertSame(0.0, $result['average_priority_fee']);
    }

    public function test_percentage_is_rounded_to_one_decimal(): void
    {
        $result = $this->invoke(['total_orders' => 3, 'total_priority_orders' => 1], 10.0, 1);

        $this->assertSame(33.3, $result['priority_percentage']);
    }

    public function test_average_fee_is_rounded_to_two_decimals(): void
    {
        $result = $this->invoke(['total_orders' => 10, 'total_priority_orders' => 3], 10.0, 3);

        $this->assertSame(3.33, $result['average_priority_fee']);
    }
}
