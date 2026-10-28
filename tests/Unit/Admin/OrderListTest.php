<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Minimal stand-in for WP_Query used by the legacy list filter. */
class WPP_Fake_Query
{
    public array $vars = [];
    public function __construct(array $vars = [], private bool $main = true) { $this->vars = $vars; }
    public function is_main_query(): bool { return $this->main; }
    public function get(string $k): mixed { return $this->vars[$k] ?? ''; }
    public function set(string $k, mixed $v): void { $this->vars[$k] = $v; }
}

class OrderListTest extends TestCase
{
    private Admin_Order_List $list;

    protected function setUp(): void
    {
        wpp_reset();
        $_GET = [];
        $this->list = new Admin_Order_List(new Core_Orders());
    }

    protected function tearDown(): void
    {
        $_GET = [];
    }

    private function order(int $id, bool $priority, string $status = 'processing'): WC_Order
    {
        $order = new WC_Order();
        $order->set_id($id);
        $order->set_status($status);
        if ($priority) {
            $order->set_meta('_priority_processing', 'yes');
        }
        wpp_register_order($order);
        return $order;
    }

    public function test_sanitize_filter_allow_list(): void
    {
        $this->assertSame('priority', Admin_Order_List::sanitize_filter('priority'));
        $this->assertSame('not_priority', Admin_Order_List::sanitize_filter('not_priority'));
        $this->assertSame('', Admin_Order_List::sanitize_filter('bogus'));
        $this->assertSame('', Admin_Order_List::sanitize_filter(['priority']));
        $this->assertSame('', Admin_Order_List::sanitize_filter(null));
    }

    public function test_hpos_args_unchanged_without_filter(): void
    {
        $args = ['status' => ['wc-processing']];
        $this->assertSame($args, $this->list->filter_hpos_query_args($args));
    }

    public function test_hpos_priority_only_sets_meta_query(): void
    {
        $_GET['wpp_priority_filter'] = 'priority';
        $args = $this->list->filter_hpos_query_args([]);
        $this->assertSame(
            [['key' => '_priority_processing', 'value' => 'yes', 'compare' => '=']],
            $args['meta_query']
        );
    }

    public function test_hpos_not_priority_uses_or_group(): void
    {
        $_GET['wpp_priority_filter'] = 'not_priority';
        $clause = $this->list->filter_hpos_query_args([])['meta_query'][0];
        $this->assertSame('OR', $clause['relation']);
        $this->assertSame('NOT EXISTS', $clause[0]['compare']);
        $this->assertSame('!=', $clause[1]['compare']);
    }

    public function test_existing_meta_query_is_preserved(): void
    {
        $_GET['wpp_priority_filter'] = 'priority';
        $existing = [['key' => 'foo', 'value' => 'bar']];
        $meta = $this->list->filter_hpos_query_args(['meta_query' => $existing])['meta_query'];
        $this->assertSame('AND', $meta['relation']);
        $this->assertSame($existing, $meta[0]);
        $this->assertSame('_priority_processing', $meta[1]['key']);
    }

    public function test_invalid_filter_value_is_ignored(): void
    {
        $_GET['wpp_priority_filter'] = 'evil';
        $this->assertSame([], $this->list->filter_hpos_query_args([]));
    }

    public function test_legacy_query_filtered_only_for_main_shop_order_query(): void
    {
        $_GET['wpp_priority_filter'] = 'priority';
        $GLOBALS['_wpp_is_admin'] = true;

        $q = new WPP_Fake_Query(['post_type' => 'shop_order', 'meta_query' => [['key' => 'foo']]]);
        $this->list->filter_legacy_query($q);
        $this->assertSame('AND', $q->vars['meta_query']['relation']);

        $other = new WPP_Fake_Query(['post_type' => 'post']);
        $this->list->filter_legacy_query($other);
        $this->assertArrayNotHasKey('meta_query', $other->vars);

        $secondary = new WPP_Fake_Query(['post_type' => 'shop_order'], false);
        $this->list->filter_legacy_query($secondary);
        $this->assertArrayNotHasKey('meta_query', $secondary->vars);
    }

    public function test_dropdown_preserves_selected_state(): void
    {
        $_GET['wpp_priority_filter'] = 'not_priority';
        ob_start();
        $this->list->render_filter();
        $html = ob_get_clean();
        $this->assertStringContainsString('<option value="not_priority" selected="selected">', $html);
        $this->assertStringNotContainsString('<option value="priority" selected', $html);
    }

    public function test_legacy_dropdown_only_for_shop_order(): void
    {
        ob_start();
        $this->list->render_filter_legacy('post');
        $this->assertSame('', ob_get_clean());
    }

    public function test_bulk_actions_registered(): void
    {
        $actions = $this->list->register_bulk_actions(['trash' => 'Trash']);
        $this->assertSame('Mark as priority', $actions['wpp_mark_priority']);
        $this->assertSame('Remove priority', $actions['wpp_remove_priority']);
        $this->assertArrayHasKey('trash', $actions);
    }

    public function test_bulk_mark_counts_and_skips_noops(): void
    {
        $a = $this->order(1, false);
        $b = $this->order(2, true);
        $c = $this->order(3, false, 'completed');

        $url = $this->list->handle_bulk_actions(
            'http://example.com/wp-admin/admin.php?page=wc-orders&wpp_bulk_removed=9',
            'wpp_mark_priority',
            ['1', 2, 3, 3, 'abc', 999]
        );

        $this->assertSame('yes', $a->get_meta('_priority_processing'));
        $this->assertTrue($a->saved);
        $this->assertStringContainsString('via bulk action', $a->notes[0]);
        $this->assertSame([], $b->notes);
        $this->assertSame('', $c->get_meta('_priority_processing'));
        $this->assertStringContainsString('wpp_bulk_marked=1', $url);
        $this->assertStringNotContainsString('wpp_bulk_removed', $url);
        $this->assertStringContainsString('page=wc-orders', $url);
    }

    public function test_bulk_remove(): void
    {
        $a = $this->order(1, true);
        $b = $this->order(2, false);

        $url = $this->list->handle_bulk_actions('http://example.com/edit.php', 'wpp_remove_priority', [1, 2]);

        $this->assertSame('', $a->get_meta('_priority_processing'));
        $this->assertStringContainsString('Admin via bulk action', $a->notes[0]);
        $this->assertSame([], $b->notes);
        $this->assertStringContainsString('wpp_bulk_removed=1', $url);
    }

    public function test_bulk_denied_without_capability(): void
    {
        $GLOBALS['_wpp_user_can'] = false;
        $a = $this->order(1, false);

        $url = $this->list->handle_bulk_actions('http://example.com/edit.php', 'wpp_mark_priority', [1]);

        $this->assertSame('http://example.com/edit.php', $url);
        $this->assertSame('', $a->get_meta('_priority_processing'));
    }

    public function test_foreign_bulk_action_ignored(): void
    {
        $this->assertSame('http://x/', $this->list->handle_bulk_actions('http://x/', 'trash', [1]));
    }

    public function test_notice_messages_are_plural_aware(): void
    {
        $this->assertSame('1 order marked as priority.', $this->list->get_notice_message(['wpp_bulk_marked' => '1']));
        $this->assertSame('3 orders marked as priority.', $this->list->get_notice_message(['wpp_bulk_marked' => '3']));
        $this->assertSame('Priority removed from 1 order.', $this->list->get_notice_message(['wpp_bulk_removed' => '1']));
        $this->assertSame('Priority removed from 2 orders.', $this->list->get_notice_message(['wpp_bulk_removed' => '2']));
        $this->assertStringStartsWith('No orders were changed', $this->list->get_notice_message(['wpp_bulk_marked' => '0']));
        $this->assertSame('', $this->list->get_notice_message([]));
    }

    public function test_notice_not_rendered_outside_orders_screen(): void
    {
        $_GET['wpp_bulk_marked'] = '2';
        ob_start();
        $this->list->render_bulk_notice();
        $this->assertSame('', ob_get_clean());
    }

    public function test_notice_rendered_on_hpos_orders_screen(): void
    {
        $_GET = ['page' => 'wc-orders', 'wpp_bulk_marked' => '2'];
        ob_start();
        $this->list->render_bulk_notice();
        $this->assertStringContainsString('2 orders marked as priority.', ob_get_clean());
    }
}
