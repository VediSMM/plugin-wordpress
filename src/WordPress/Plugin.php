<?php

declare(strict_types=1);

namespace VediSMM\WordPress\WordPress;

final class Plugin
{
    public static function boot(): void
    {
        $settings = new SettingsPage();
        $metabox = new MetaBox();

        if (function_exists('add_action')) {
            add_action('admin_init', [$settings, 'register']);
            add_action('add_meta_boxes', [$metabox, 'register']);
        }
    }
}
