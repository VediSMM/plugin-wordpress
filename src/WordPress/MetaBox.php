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

    public function render(mixed $post = null): void
    {
        if (function_exists('wp_nonce_field')) {
            wp_nonce_field(self::NONCE_ACTION, 'vedismm_nonce');
        }

        $shortenLabel = self::escape(self::translate('Shorten links'));
        $shortenHelp = self::escape(self::translate('Create a separate short link automatically for each target network.'));
        $sourceLabel = self::escape(self::translate('Add network source'));
        $sourceHelp = self::escape(self::translate('If utm_source is absent, add utm_source=<network> and preserve existing utm_term. If utm_source exists, preserve it and replace or add one utm_term=<network>.'));
        $postId = is_object($post) ? (int) ($post->ID ?? 0) : 0;
        $saved = $postId > 0 && function_exists('get_post_meta')
            ? get_post_meta($postId, '_vedismm_tracking', true)
            : [];
        $tracking = PostSubmissionHandler::normalizeTracking(is_array($saved) ? $saved : []);
        $shortenChecked = $tracking['shorten_links'] ? ' checked' : '';
        $sourceChecked = $tracking['add_source'] ? ' checked' : '';
        $sourceDisabled = $tracking['shorten_links'] ? '' : ' aria-disabled="true" disabled';

        echo '<fieldset class="vedismm-tracking-options">';
        echo '<legend>' . self::escape(self::translate('Tracking links')) . '</legend>';
        echo '<p><label for="vedismm-shorten-links">';
        echo '<input type="checkbox" id="vedismm-shorten-links" name="vedismm_tracking[shorten_links]" value="1" aria-describedby="vedismm-shorten-links-help"' . $shortenChecked . '> ';
        echo $shortenLabel . '</label></p>';
        echo '<p class="description" id="vedismm-shorten-links-help">' . $shortenHelp . '</p>';
        echo '<p><label for="vedismm-add-source">';
        echo '<input type="checkbox" id="vedismm-add-source" name="vedismm_tracking[add_source]" value="1" aria-describedby="vedismm-add-source-help"' . $sourceChecked . $sourceDisabled . '> ';
        echo $sourceLabel . '</label></p>';
        echo '<p class="description" id="vedismm-add-source-help">' . $sourceHelp . '</p>';
        echo '</fieldset>';
        echo '<script>(function(){var shorten=document.getElementById("vedismm-shorten-links");var source=document.getElementById("vedismm-add-source");if(!shorten||!source){return;}function sync(){var disabled=!shorten.checked;source.disabled=disabled;source.setAttribute("aria-disabled",disabled?"true":"false");if(disabled){source.checked=false;}}shorten.addEventListener("change",sync);sync();}());</script>';
        echo '<button type="submit" class="button button-primary" name="vedismm_submit_action" value="draft">' . self::escape(self::translate('Send to VediSMM')) . '</button>';
    }

    private static function translate(string $text): string
    {
        return function_exists('__') ? (string) __($text, 'vedismm') : $text;
    }

    private static function escape(string $text): string
    {
        return function_exists('esc_html')
            ? (string) esc_html($text)
            : htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
