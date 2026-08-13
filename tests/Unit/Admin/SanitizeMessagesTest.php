<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class SanitizeMessagesTest extends TestCase
{
    private Admin_Settings $settings;

    protected function setUp(): void
    {
        wpp_reset();
        $this->settings = new Admin_Settings();
    }

    // -- sanitize_messages() -----------------------------------------------

    public function test_drops_empty_lines(): void
    {
        $result = $this->settings->sanitize_messages("First\n\n\nSecond\n");
        $this->assertSame("First\nSecond", $result);
    }

    public function test_drops_whitespace_only_lines(): void
    {
        $result = $this->settings->sanitize_messages("First\n   \nSecond");
        $this->assertSame("First\nSecond", $result);
    }

    public function test_normalizes_windows_line_endings(): void
    {
        $result = $this->settings->sanitize_messages("First\r\nSecond\r\nThird");
        $this->assertSame("First\nSecond\nThird", $result);
    }

    public function test_strips_tags_from_each_line(): void
    {
        $result = $this->settings->sanitize_messages('<strong>Hello</strong> world');
        $this->assertSame('Hello world', $result);
    }

    public function test_preserves_message_order(): void
    {
        $result = $this->settings->sanitize_messages("C\nA\nB");
        $this->assertSame("C\nA\nB", $result);
    }

    public function test_empty_input_returns_empty_string(): void
    {
        $this->assertSame('', $this->settings->sanitize_messages(''));
        $this->assertSame('', $this->settings->sanitize_messages("\n\n   \n"));
    }

    // -- sanitize_message_mode() --------------------------------------------

    public function test_threshold_is_preserved(): void
    {
        $this->assertSame('threshold', $this->settings->sanitize_message_mode('threshold'));
    }

    public function test_always_is_preserved(): void
    {
        $this->assertSame('always', $this->settings->sanitize_message_mode('always'));
    }

    public function test_unknown_value_falls_back_to_always(): void
    {
        $this->assertSame('always', $this->settings->sanitize_message_mode('garbage'));
        $this->assertSame('always', $this->settings->sanitize_message_mode(''));
    }
}
