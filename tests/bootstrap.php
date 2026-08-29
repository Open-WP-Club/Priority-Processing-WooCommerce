<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('WP_DEBUG_LOG', true);

// ---------------------------------------------------------------------------
// WordPress function stubs
// ---------------------------------------------------------------------------

function get_option(string $option, mixed $default = false): mixed {
    return $GLOBALS['_wpp_options'][$option] ?? $default;
}

function update_option(string $option, mixed $value): bool {
    $GLOBALS['_wpp_options'][$option] = $value;
    return true;
}

function add_option(string $option, mixed $value): bool {
    if (!isset($GLOBALS['_wpp_options'][$option])) {
        $GLOBALS['_wpp_options'][$option] = $value;
    }
    return true;
}

function sanitize_text_field(string $str): string {
    return trim(strip_tags($str));
}

function sanitize_textarea_field(string $str): string {
    return trim($str);
}

function wp_timezone(): DateTimeZone {
    return new DateTimeZone($GLOBALS['_wpp_timezone'] ?? 'UTC');
}

function add_action(): void {}
function add_filter(): void {}
function register_setting(): void {}
function wp_verify_nonce(): bool { return $GLOBALS['_wpp_nonce_valid'] ?? true; }
function wp_create_nonce(): string { return 'test_nonce'; }
function admin_url(string $path = ''): string { return 'http://example.com/wp-admin/' . $path; }
function is_checkout(): bool { return true; }
function plugin_dir_path(): string { return __DIR__ . '/../'; }
function plugin_dir_url(): string { return 'http://example.com/wp-content/plugins/wpp/'; }
function plugin_basename(): string { return 'woocommerce-priority-processing/woocommerce-priority-processing.php'; }
function wp_enqueue_style(): void {}
function wp_enqueue_script(): void {}
function wp_localize_script(): void {}
function load_plugin_textdomain(): void {}
function esc_html(string $str): string { return htmlspecialchars($str, ENT_QUOTES); }
function esc_attr(string $str): string { return htmlspecialchars($str, ENT_QUOTES); }
function esc_textarea(string $str): string { return htmlspecialchars($str, ENT_QUOTES); }
function esc_html_e(string $str): void { echo htmlspecialchars($str, ENT_QUOTES); }
function __(string $text, string $domain = ''): string { return $text; }
function _e(string $text, string $domain = ''): void { echo $text; }
function checked(mixed $checked, mixed $current = true, bool $echo = true): string {
    $result = $checked == $current ? ' checked="checked"' : '';
    if ($echo) echo $result;
    return $result;
}
function wc_price(mixed $price): string { return '$' . number_format((float) $price, 2); }
function is_order_received_page(): bool { return $GLOBALS['_wpp_is_order_received_page'] ?? false; }
function esc_html__(string $text, string $domain = ''): string { return $text; }
function wp_unslash(mixed $value): mixed { return $value; }
function do_action(): void {}
function wpp_log(string $message): void {}
function current_user_can(string $capability): bool { return $GLOBALS['_wpp_user_can'] ?? true; }
function wp_get_current_user(): object {
    return (object) [
        'ID'           => $GLOBALS['_wpp_current_user_id'] ?? 1,
        'display_name' => $GLOBALS['_wpp_current_user_display_name'] ?? 'Admin',
    ];
}
function wc_get_order(int $id): WC_Order|false {
    return $GLOBALS['_wpp_orders'][$id] ?? false;
}

/**
 * Stub for wc_get_orders(), driven by a page-keyed result queue so tests can
 * simulate pagination. Set via $GLOBALS['_wpp_order_query_pages'][$page].
 */
function wc_get_orders(array $args): object {
    if (!empty($GLOBALS['_wpp_wc_get_orders_throws'])) {
        throw new Exception('wc_get_orders stub failure');
    }

    $page = $args['paged'] ?? 1;
    return $GLOBALS['_wpp_order_query_pages'][$page] ?? (object) [
        'orders'        => [],
        'total'         => 0,
        'max_num_pages' => 1,
    ];
}

/**
 * wp_send_json_success()/wp_send_json_error() normally call wp_die(), halting
 * execution. Throwing an \Error (not \Exception) reproduces that halt while
 * staying immune to the plugin's own `catch (Exception $e)` blocks, so tests
 * can catch these specifically and inspect the payload.
 */
class WPP_Json_Success extends \Error {
    public function __construct(public readonly mixed $data = null) { parent::__construct('wp_send_json_success'); }
}
class WPP_Json_Error extends \Error {
    public function __construct(public readonly mixed $data = null) { parent::__construct('wp_send_json_error'); }
}
function wp_send_json_success(mixed $data = null): void { throw new WPP_Json_Success($data); }
function wp_send_json_error(mixed $data = null): void { throw new WPP_Json_Error($data); }
// error_log() is a native PHP function — no stub needed

// ---------------------------------------------------------------------------
// WordPress/WooCommerce class stubs
// ---------------------------------------------------------------------------

class Core_Permissions {
    public static function can_access_priority_processing(): bool {
        return $GLOBALS['_wpp_can_access'] ?? true;
    }
    public static function can_enable_priority_processing(): bool {
        return $GLOBALS['_wpp_can_enable'] ?? true;
    }
    public static function get_allowed_user_roles(): array {
        return $GLOBALS['_wpp_allowed_roles'] ?? ['customer'];
    }
    public static function get_available_user_roles(): array {
        return ['customer' => 'Customer', 'subscriber' => 'Subscriber', 'administrator' => 'Administrator'];
    }
    public static function get_permission_summary(): array {
        return $GLOBALS['_wpp_permission_summary'] ?? ['Customers'];
    }
    public static function is_priority_active(): bool {
        $priority = WC()->session->get('priority_processing', false);
        return ($priority === true || $priority === '1' || $priority === 1);
    }
}

class WC_Cart_Stub {
    public array $fees = [];

    public function get_subtotal(): float {
        return (float) ($GLOBALS['_wpp_cart_subtotal'] ?? 100.0);
    }

    public function add_fee(string $label, float $amount, bool $taxable = true): void {
        $this->fees[] = ['label' => $label, 'amount' => $amount, 'taxable' => $taxable];
    }
}

class WC_Session_Stub {
    private array $data = [];
    public function get(string $key, mixed $default = null): mixed { return $this->data[$key] ?? $default; }
    public function set(string $key, mixed $value): void { $this->data[$key] = $value; }
}

class WC_Stub {
    public WC_Cart_Stub $cart;
    public WC_Session_Stub $session;
    public function __construct() {
        $this->cart    = new WC_Cart_Stub();
        $this->session = new WC_Session_Stub();
    }
}

function WC(): WC_Stub {
    return $GLOBALS['_wpp_wc_instance'] ??= new WC_Stub();
}

class WC_Order_Item_Fee {
    private static int $next_id = 1;
    public int $id;
    private string $name = '';
    private float $total = 0.0;

    public function __construct() {
        $this->id = self::$next_id++;
    }

    public function set_name(string $name): void { $this->name = $name; }
    public function set_amount(float $amount): void {}
    public function set_total(float $total): void { $this->total = $total; }
    public function set_order_id(int $order_id): void {}
    public function get_name(): string { return $this->name; }
    public function get_total(): float { return $this->total; }
}

class WC_Order {
    private array $meta = [];
    private int $id = 1;
    private string $status = 'processing';
    private array $fees = [];
    public array $notes = [];
    public bool $saved = false;
    private float $total = 100.0;

    public function set_id(int $id): void { $this->id = $id; }
    public function get_id(): int { return $this->id; }
    public function set_status(string $status): void { $this->status = $status; }
    public function get_status(): string { return $this->status; }

    public function set_meta(string $key, string $value): void {
        $this->meta[$key] = $value;
    }

    public function get_meta(string $key): mixed {
        return $this->meta[$key] ?? '';
    }

    public function update_meta_data(string $key, mixed $value): void {
        $this->meta[$key] = $value;
    }

    public function delete_meta_data(string $key): void {
        unset($this->meta[$key]);
    }

    /** @return WC_Order_Item_Fee[] */
    public function get_fees(): array {
        return $this->fees;
    }

    public function add_item(WC_Order_Item_Fee $item): void {
        $this->fees[$item->id] = $item;
    }

    public function remove_item(int $fee_id): void {
        unset($this->fees[$fee_id]);
    }

    public function add_order_note(string $note, bool $customer_note = false): void {
        $this->notes[] = $note;
    }

    public function calculate_totals(): void {}

    public function save(): void { $this->saved = true; }

    public function get_total(): float { return $this->total; }

    public function get_formatted_order_total(): string { return wc_price($this->total); }
}

class WC_Shipping_Rate_Stub {
    public array $meta = [];
    public function add_meta_data(string $key, mixed $value, bool $unique = false): void {
        $this->meta[$key] = $value;
    }
}

/**
 * Minimal stand-in for the real plugin singleton (never loaded in this
 * bootstrap) so Core_Orders::ajax_toggle_order_priority can call
 * WooCommerce_Priority_Processing::instance() without pulling in the whole
 * plugin bootstrap.
 */
class WooCommerce_Priority_Processing {
    private static ?self $instance = null;
    public $core_statistics = null;

    public static function instance(): self {
        return self::$instance ??= new self();
    }

    public static function reset_for_tests(): void {
        self::$instance = null;
    }
}

// ---------------------------------------------------------------------------
// Load plugin classes (Core_Permissions stubbed above — don't load the real one)
// ---------------------------------------------------------------------------

require_once __DIR__ . '/../includes/admin/settings.php';
require_once __DIR__ . '/../includes/frontend/checkout.php';
require_once __DIR__ . '/../includes/frontend/messages.php';
require_once __DIR__ . '/../includes/frontend/account.php';
require_once __DIR__ . '/../includes/core/statistics.php';
require_once __DIR__ . '/../includes/core/orders.php';
require_once __DIR__ . '/../includes/frontend/fees.php';
require_once __DIR__ . '/../includes/frontend/shipping.php';
require_once __DIR__ . '/../includes/api/rest-controller.php';
require_once __DIR__ . '/../includes/frontend/ajax.php';
require_once __DIR__ . '/../includes/frontend/blocks-integration.php';

// ---------------------------------------------------------------------------
// Test helpers
// ---------------------------------------------------------------------------

function wpp_set_option(string $key, mixed $value): void {
    $GLOBALS['_wpp_options'][$key] = $value;
}

function wpp_reset(): void {
    $GLOBALS['_wpp_options']          = [];
    $GLOBALS['_wpp_can_access']       = true;
    $GLOBALS['_wpp_can_enable']       = true;
    $GLOBALS['_wpp_cart_subtotal']    = 100.0;
    $GLOBALS['_wpp_timezone']         = 'UTC';
    $GLOBALS['_wpp_allowed_roles']    = ['customer'];
    $GLOBALS['_wpp_permission_summary'] = ['Customers'];
    $GLOBALS['_wpp_is_order_received_page'] = false;
    $GLOBALS['_wpp_nonce_valid']          = true;
    $GLOBALS['_wpp_user_can']             = true;
    $GLOBALS['_wpp_current_user_id']      = 1;
    $GLOBALS['_wpp_current_user_display_name'] = 'Admin';
    $GLOBALS['_wpp_orders']               = [];
    $GLOBALS['_wpp_wc_instance']          = new WC_Stub();
    $GLOBALS['_wpp_order_query_pages']    = [];
    $GLOBALS['_wpp_wc_get_orders_throws'] = false;
    $_POST                                = [];
    WooCommerce_Priority_Processing::reset_for_tests();
}

function wpp_register_order(WC_Order $order): void {
    $GLOBALS['_wpp_orders'][$order->get_id()] = $order;
}
