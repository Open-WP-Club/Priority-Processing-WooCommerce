<?php

declare(strict_types=1);

// The main tests/bootstrap.php defines a fake Core_Permissions stub (for tests
// that need to control access as a black box). This is a separate bootstrap,
// run as its own PHPUnit suite, that loads the REAL permissions.php instead so
// its actual access-control logic gets exercised directly.

define('ABSPATH', __DIR__ . '/');

// ---------------------------------------------------------------------------
// Minimal WordPress stubs needed by includes/core/permissions.php
// ---------------------------------------------------------------------------

function get_option(string $option, mixed $default = false): mixed {
    return $GLOBALS['_wpp_options'][$option] ?? $default;
}

function update_option(string $option, mixed $value): bool {
    $GLOBALS['_wpp_options'][$option] = $value;
    return true;
}

function current_user_can(string $capability): bool {
    return in_array($capability, $GLOBALS['_wpp_current_user_caps'] ?? [], true);
}

function is_user_logged_in(): bool {
    return $GLOBALS['_wpp_logged_in'] ?? false;
}

function wp_get_current_user(): object {
    return (object) ['roles' => $GLOBALS['_wpp_current_user_roles'] ?? []];
}

function user_can(int $userId, string $capability): bool {
    return in_array($capability, $GLOBALS['_wpp_user_caps'][$userId] ?? [], true);
}

function get_user_by(string $field, mixed $value): object|false {
    return $GLOBALS['_wpp_users'][$value] ?? false;
}

function __(string $text, string $domain = ''): string {
    return $text;
}

// ---------------------------------------------------------------------------
// Load the real class under test.
// ---------------------------------------------------------------------------

require_once __DIR__ . '/../includes/core/permissions.php';

// ---------------------------------------------------------------------------
// Test helpers
// ---------------------------------------------------------------------------

function wpp_perm_set_option(string $key, mixed $value): void {
    $GLOBALS['_wpp_options'][$key] = $value;
}

function wpp_perm_reset(): void {
    $GLOBALS['_wpp_options']            = [];
    $GLOBALS['_wpp_current_user_caps']  = [];
    $GLOBALS['_wpp_current_user_roles'] = [];
    $GLOBALS['_wpp_logged_in']          = false;
    $GLOBALS['_wpp_user_caps']          = [];
    $GLOBALS['_wpp_users']              = [];
}
