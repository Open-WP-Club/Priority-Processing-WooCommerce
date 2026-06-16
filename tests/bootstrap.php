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
function wp_verify_nonce(): bool { return true; }
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
// error_log() is a native PHP function — no stub needed

// ---------------------------------------------------------------------------
// WordPress/WooCommerce class stubs
// ---------------------------------------------------------------------------

class Core_Permissions {
    public static function can_access_priority_processing(): bool {
        return $GLOBALS['_wpp_can_access'] ?? true;
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
}

class WC_Cart_Stub {
    public function get_subtotal(): float {
        return (float) ($GLOBALS['_wpp_cart_subtotal'] ?? 100.0);
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
    static $instance = null;
    return $instance ??= new WC_Stub();
}

// ---------------------------------------------------------------------------
// Load plugin classes (Core_Permissions stubbed above — don't load the real one)
// ---------------------------------------------------------------------------

require_once __DIR__ . '/../includes/admin/settings.php';
require_once __DIR__ . '/../includes/frontend/checkout.php';

// ---------------------------------------------------------------------------
// Test helpers
// ---------------------------------------------------------------------------

function wpp_set_option(string $key, mixed $value): void {
    $GLOBALS['_wpp_options'][$key] = $value;
}

function wpp_reset(): void {
    $GLOBALS['_wpp_options']          = [];
    $GLOBALS['_wpp_can_access']       = true;
    $GLOBALS['_wpp_cart_subtotal']    = 100.0;
    $GLOBALS['_wpp_timezone']         = 'UTC';
    $GLOBALS['_wpp_allowed_roles']    = ['customer'];
    $GLOBALS['_wpp_permission_summary'] = ['Customers'];
}
