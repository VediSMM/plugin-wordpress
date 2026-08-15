<?php
/**
 * Plugin Name: VediSMM
 * Plugin URI: https://vedismm.ru/
 * Description: Send WordPress content to VediSMM as drafts, scheduled posts, or explicit publish jobs.
 * Version: 1.1.0
 * Author: VediSMM
 * License: MIT
 * Text Domain: vedismm
 * Domain Path: /languages
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'VediSMM\\WordPress\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

VediSMM\WordPress\WordPress\Plugin::boot();
