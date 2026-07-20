<?php

declare(strict_types=1);

namespace VediSMM\WordPress\WordPress;

final class MetaBox
{
    public const NONCE_ACTION = 'vedismm_submit_post';

    public function register(): void
    {
        if (function_exists('add_meta_box')) {
            foreach (['post', 'page'] as $screen) {
                add_meta_box('vedismm', 'VediSMM', [$this, 'render'], $screen, 'side');
            }
        }
    }

    public function render(): void
    {
        if (function_exists('wp_nonce_field')) {
            wp_nonce_field(self::NONCE_ACTION, 'vedismm_nonce');
        }

        echo '<button type="button" class="button button-primary" data-vedismm-action="draft">Send to VediSMM</button>';
    }
}
