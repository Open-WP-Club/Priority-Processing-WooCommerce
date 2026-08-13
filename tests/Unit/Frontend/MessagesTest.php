<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class MessagesTest extends TestCase
{
    private Frontend_Messages $messages;
    private ReflectionMethod $should_display;
    private ReflectionMethod $get_random_message;

    protected function setUp(): void
    {
        wpp_reset();
        $this->messages          = new Frontend_Messages();
        $this->should_display    = new ReflectionMethod(Frontend_Messages::class, 'should_display');
        $this->get_random_message = new ReflectionMethod(Frontend_Messages::class, 'get_random_message');
    }

    private function shouldDisplay(string $enabled_option, string $mode_option, string $threshold_option): bool
    {
        return $this->should_display->invoke($this->messages, $enabled_option, $mode_option, $threshold_option);
    }

    private function randomMessage(string $option_name): string
    {
        return $this->get_random_message->invoke($this->messages, $option_name);
    }

    // -- should_display() -----------------------------------------------

    public function test_hidden_when_master_feature_disabled(): void
    {
        wpp_set_option('wpp_enabled', '0');
        wpp_set_option('wpp_cart_message_enabled', '1');
        wpp_set_option('wpp_cart_message_mode', 'always');
        $this->assertFalse($this->shouldDisplay('wpp_cart_message_enabled', 'wpp_cart_message_mode', 'wpp_cart_message_threshold'));
    }

    public function test_hidden_when_location_disabled(): void
    {
        wpp_set_option('wpp_enabled', '1');
        wpp_set_option('wpp_cart_message_enabled', '0');
        wpp_set_option('wpp_cart_message_mode', 'always');
        $this->assertFalse($this->shouldDisplay('wpp_cart_message_enabled', 'wpp_cart_message_mode', 'wpp_cart_message_threshold'));
    }

    public function test_shown_when_enabled_and_mode_always(): void
    {
        wpp_set_option('wpp_enabled', '1');
        wpp_set_option('wpp_cart_message_enabled', '1');
        wpp_set_option('wpp_cart_message_mode', 'always');
        $this->assertTrue($this->shouldDisplay('wpp_cart_message_enabled', 'wpp_cart_message_mode', 'wpp_cart_message_threshold'));
    }

    public function test_hidden_when_threshold_mode_and_subtotal_below_threshold(): void
    {
        wpp_set_option('wpp_enabled', '1');
        wpp_set_option('wpp_cart_message_enabled', '1');
        wpp_set_option('wpp_cart_message_mode', 'threshold');
        wpp_set_option('wpp_cart_message_threshold', '100.00');
        $GLOBALS['_wpp_cart_subtotal'] = 99.99;
        $this->assertFalse($this->shouldDisplay('wpp_cart_message_enabled', 'wpp_cart_message_mode', 'wpp_cart_message_threshold'));
    }

    public function test_shown_when_threshold_mode_and_subtotal_meets_threshold(): void
    {
        wpp_set_option('wpp_enabled', '1');
        wpp_set_option('wpp_cart_message_enabled', '1');
        wpp_set_option('wpp_cart_message_mode', 'threshold');
        wpp_set_option('wpp_cart_message_threshold', '100.00');
        $GLOBALS['_wpp_cart_subtotal'] = 100.00;
        $this->assertTrue($this->shouldDisplay('wpp_cart_message_enabled', 'wpp_cart_message_mode', 'wpp_cart_message_threshold'));
    }

    public function test_product_location_uses_its_own_options(): void
    {
        wpp_set_option('wpp_enabled', '1');
        wpp_set_option('wpp_cart_message_enabled', '1');
        wpp_set_option('wpp_product_message_enabled', '0');
        wpp_set_option('wpp_product_message_mode', 'always');
        $this->assertTrue($this->shouldDisplay('wpp_cart_message_enabled', 'wpp_cart_message_mode', 'wpp_cart_message_threshold'));
        $this->assertFalse($this->shouldDisplay('wpp_product_message_enabled', 'wpp_product_message_mode', 'wpp_product_message_threshold'));
    }

    // -- get_random_message() --------------------------------------------

    public function test_returns_empty_string_when_option_not_set(): void
    {
        $this->assertSame('', $this->randomMessage('wpp_cart_messages'));
    }

    public function test_returns_empty_string_when_only_blank_lines(): void
    {
        wpp_set_option('wpp_cart_messages', "\n   \n\n");
        $this->assertSame('', $this->randomMessage('wpp_cart_messages'));
    }

    public function test_returns_the_single_message_when_only_one_present(): void
    {
        wpp_set_option('wpp_cart_messages', 'Only message here');
        $this->assertSame('Only message here', $this->randomMessage('wpp_cart_messages'));
    }

    public function test_ignores_blank_lines_between_messages(): void
    {
        wpp_set_option('wpp_cart_messages', "First message\n\n  \nSecond message\n");
        $result = $this->randomMessage('wpp_cart_messages');
        $this->assertContains($result, ['First message', 'Second message']);
    }

    public function test_trims_whitespace_around_each_message(): void
    {
        wpp_set_option('wpp_cart_messages', '   Padded message   ');
        $this->assertSame('Padded message', $this->randomMessage('wpp_cart_messages'));
    }

    // -- default message getters ------------------------------------------

    public function test_default_cart_messages_has_multiple_non_empty_lines(): void
    {
        $lines = array_filter(explode("\n", Frontend_Messages::get_default_cart_messages()));
        $this->assertGreaterThanOrEqual(2, count($lines));
    }

    public function test_default_product_messages_has_multiple_non_empty_lines(): void
    {
        $lines = array_filter(explode("\n", Frontend_Messages::get_default_product_messages()));
        $this->assertGreaterThanOrEqual(2, count($lines));
    }

    // -- render methods (integration of should_display + output) ----------

    public function test_render_cart_message_outputs_nothing_when_disabled(): void
    {
        wpp_set_option('wpp_enabled', '1');
        wpp_set_option('wpp_cart_message_enabled', '0');

        ob_start();
        $this->messages->render_cart_message();
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    public function test_render_cart_message_outputs_message_when_enabled(): void
    {
        wpp_set_option('wpp_enabled', '1');
        wpp_set_option('wpp_cart_message_enabled', '1');
        wpp_set_option('wpp_cart_message_mode', 'always');
        wpp_set_option('wpp_cart_messages', 'Hurry up & order now');

        ob_start();
        $this->messages->render_cart_message();
        $output = ob_get_clean();

        $this->assertStringContainsString('Hurry up &amp; order now', $output);
    }

    public function test_render_product_message_outputs_message_when_enabled(): void
    {
        wpp_set_option('wpp_enabled', '1');
        wpp_set_option('wpp_product_message_enabled', '1');
        wpp_set_option('wpp_product_message_mode', 'always');
        wpp_set_option('wpp_product_messages', 'Get it first');

        ob_start();
        $this->messages->render_product_message();
        $output = ob_get_clean();

        $this->assertStringContainsString('Get it first', $output);
    }
}
