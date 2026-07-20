<?php

declare(strict_types=1);

namespace VediSMM\WordPress\WordPress;

final class SettingsPage
{
    public function register(): void
    {
        if (function_exists('register_setting')) {
            register_setting('vedismm', 'vedismm_token', [
                'type' => 'string',
                'sanitize_callback' => static fn (mixed $value): ?string => self::sanitizeToken(
                    is_string($value) ? $value : '',
                    is_string(get_option('vedismm_token', null)) ? get_option('vedismm_token') : null
                ),
            ]);
        }
    }

    public static function sanitizeToken(string $submitted, ?string $existing, bool $remove = false): ?string
    {
        if ($remove) {
            return null;
        }

        $trimmed = trim($submitted);
        return $trimmed === '' ? $existing : $trimmed;
    }

    public static function renderTokenValue(?string $savedToken): string
    {
        return '';
    }
}
