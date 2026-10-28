<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class BulgarianTranslationTest extends TestCase
{
    private const LANG_DIR = __DIR__ . '/../../../languages/';

    /** @return array<string, array<int, string>> msgid => [msgstr, ...plural forms] */
    private function parse(string $file): array
    {
        $entries = [];
        $id      = null;
        $field   = null;
        foreach (file(self::LANG_DIR . $file, FILE_IGNORE_NEW_LINES) as $line) {
            if (preg_match('/^(msgid|msgid_plural|msgstr(?:\[(\d)\])?)\s+"(.*)"$/', $line, $m)) {
                $text = stripcslashes($m[3]);
                if ('msgid' === $m[1]) {
                    $id    = $text;
                    $field = 'id';
                    $entries[$id] = [];
                } elseif ('msgid_plural' === $m[1]) {
                    $field = 'plural';
                } else {
                    $field = (int) ($m[2] ?? 0);
                    $entries[$id][$field] = $text;
                }
            } elseif (preg_match('/^"(.*)"$/', $line, $m) && null !== $id) {
                if (is_int($field)) {
                    $entries[$id][$field] .= stripcslashes($m[1]);
                } elseif ('id' === $field) {
                    unset($entries[$id]);
                    $id = $id . stripcslashes($m[1]);
                    $entries[$id] = [];
                }
            }
        }
        unset($entries['']);
        return $entries;
    }

    public function test_every_template_string_has_a_bulgarian_translation(): void
    {
        $template = array_keys($this->parse('woo-priority.pot'));
        $bulgarian = $this->parse('woo-priority-bg_BG.po');

        $missing = [];
        foreach ($template as $msgid) {
            $forms = $bulgarian[$msgid] ?? [];
            if ([] === $forms || in_array('', $forms, true)) {
                $missing[] = $msgid;
            }
        }

        $this->assertSame([], $missing, 'Untranslated strings: ' . implode(' | ', $missing));
    }

    public function test_translations_keep_the_same_placeholders_as_the_source(): void
    {
        $broken = [];
        foreach ($this->parse('woo-priority-bg_BG.po') as $msgid => $forms) {
            preg_match_all('/%(?:\d+\$)?[sd]/', $msgid, $source);
            foreach ($forms as $form) {
                preg_match_all('/%(?:\d+\$)?[sd]/', $form, $target);
                $expected = $source[0];
                $actual   = $target[0];
                sort($expected);
                sort($actual);
                // Plural source strings may drop the number in the singular form; every placeholder must still exist in the msgid.
                if (array_diff($actual, $expected) || (count($forms) === 1 && $expected !== $actual)) {
                    $broken[] = $msgid;
                }
            }
        }

        $this->assertSame([], array_values(array_unique($broken)), 'Placeholder mismatch');
    }

    public function test_cart_button_labels_are_fully_translated_and_compiled(): void
    {
        $bulgarian = $this->parse('woo-priority-bg_BG.po');
        $compiled  = (string) file_get_contents(self::LANG_DIR . 'woo-priority-bg_BG.mo');

        foreach (['Add Priority Processing', 'Remove Priority Processing'] as $msgid) {
            $label = $bulgarian[$msgid][0] ?? '';
            $this->assertNotSame('', $label, $msgid);
            $this->assertStringNotContainsString('Priority Processing', $label, 'The button label must not mix English and Bulgarian.');
            $this->assertStringContainsString($label, $compiled, 'The compiled .mo is out of date with the .po file.');
        }
    }
}
