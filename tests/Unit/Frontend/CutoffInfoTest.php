<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class CutoffInfoTest extends TestCase
{
    private Frontend_Checkout $checkout;
    private ReflectionMethod $method;

    protected function setUp(): void
    {
        wpp_reset();
        $this->checkout = new Frontend_Checkout();
        $this->method   = new ReflectionMethod(Frontend_Checkout::class, 'get_cutoff_info');
    }

    private function invoke(?DateTimeImmutable $now = null): mixed
    {
        return $this->method->invoke($this->checkout, $now);
    }

    public function test_returns_null_when_option_is_empty(): void
    {
        wpp_set_option('wpp_cutoff_time', '');
        $this->assertNull($this->invoke());
    }

    public function test_returns_null_when_option_not_set(): void
    {
        $this->assertNull($this->invoke());
    }

    public function test_returns_null_for_invalid_format(): void
    {
        wpp_set_option('wpp_cutoff_time', 'noon');
        $this->assertNull($this->invoke());
    }

    public function test_returns_before_cutoff_message_with_hours_and_minutes(): void
    {
        wpp_set_option('wpp_cutoff_time', '18:00');
        $GLOBALS['_wpp_timezone'] = 'UTC';

        // "now" is 10:30, cutoff is 18:00 → 7h 30m remaining
        $now = new DateTimeImmutable('2026-06-16 10:30:00', new DateTimeZone('UTC'));
        $result = $this->invoke($now);

        $this->assertNotNull($result);
        $this->assertGreaterThan(0, $result['remaining_seconds']);
        $this->assertStringContainsString('7h', $result['message']);
        $this->assertStringContainsString('30m', $result['message']);
        $this->assertGreaterThan(0, $result['cutoff_ts']);
    }

    public function test_returns_minutes_only_when_less_than_one_hour(): void
    {
        wpp_set_option('wpp_cutoff_time', '14:00');
        $GLOBALS['_wpp_timezone'] = 'UTC';

        // "now" is 13:35, cutoff is 14:00 → 25 min remaining
        $now = new DateTimeImmutable('2026-06-16 13:35:00', new DateTimeZone('UTC'));
        $result = $this->invoke($now);

        $this->assertNotNull($result);
        $this->assertStringNotContainsString('h ', $result['message']);
        $this->assertStringContainsString('25 minutes', $result['message']);
    }

    public function test_returns_passed_message_when_cutoff_has_elapsed(): void
    {
        wpp_set_option('wpp_cutoff_time', '10:00');
        $GLOBALS['_wpp_timezone'] = 'UTC';

        // "now" is 14:00, cutoff was 10:00 → passed
        $now = new DateTimeImmutable('2026-06-16 14:00:00', new DateTimeZone('UTC'));
        $result = $this->invoke($now);

        $this->assertNotNull($result);
        $this->assertSame(0, $result['remaining_seconds']);
        $this->assertSame(0, $result['cutoff_ts']);
        $this->assertStringContainsString('passed', $result['message']);
    }

    public function test_exact_cutoff_moment_is_treated_as_passed(): void
    {
        wpp_set_option('wpp_cutoff_time', '12:00');
        $GLOBALS['_wpp_timezone'] = 'UTC';

        $now    = new DateTimeImmutable('2026-06-16 12:00:00', new DateTimeZone('UTC'));
        $result = $this->invoke($now);

        $this->assertNotNull($result);
        $this->assertSame(0, $result['remaining_seconds']);
    }

    public function test_cutoff_ts_is_milliseconds(): void
    {
        wpp_set_option('wpp_cutoff_time', '20:00');
        $GLOBALS['_wpp_timezone'] = 'UTC';

        $now    = new DateTimeImmutable('2026-06-16 10:00:00', new DateTimeZone('UTC'));
        $result = $this->invoke($now);

        // Unix timestamp in ms should be a 13-digit number
        $this->assertGreaterThan(1_000_000_000_000, $result['cutoff_ts']);
    }

    public function test_respects_store_timezone(): void
    {
        wpp_set_option('wpp_cutoff_time', '14:00');
        $GLOBALS['_wpp_timezone'] = 'Europe/Sofia'; // UTC+3 in summer

        // "now" in Sofia is 16:00, cutoff 14:00 → passed
        $now    = new DateTimeImmutable('2026-06-16 16:00:00', new DateTimeZone('Europe/Sofia'));
        $result = $this->invoke($now);

        $this->assertSame(0, $result['remaining_seconds']);
    }
}
