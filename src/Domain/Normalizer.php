<?php

declare(strict_types=1);

namespace VediSMM\WordPress\Domain;

final class Normalizer
{
    public static function title(string $value): string
    {
        return mb_substr(self::text($value), 0, 190);
    }

    public static function text(string $value): string
    {
        $withBreaks = preg_replace(
            '/<\s*\/?(?:p|br|div|h[1-6]|li|ul|ol|blockquote)[^>]*>/i',
            ' ',
            $value
        );
        $plain = strip_tags((string) $withBreaks);
        $decoded = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = str_replace("\xc2\xa0", ' ', $decoded);
        $decoded = preg_replace('/\s+/u', ' ', $decoded);

        return trim((string) $decoded);
    }

    public static function url(?string $value): ?string
    {
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }

        $scheme = parse_url($trimmed, PHP_URL_SCHEME);
        if (!is_string($scheme) || !in_array(strtolower($scheme), ['http', 'https'], true)) {
            return null;
        }

        $canonical = strtolower($scheme) . substr($trimmed, strlen($scheme));
        return filter_var($canonical, FILTER_VALIDATE_URL) === false ? null : $canonical;
    }

    /** @param array<int,mixed> $values @return array<int,int> */
    public static function positiveUniqueIds(array $values): array
    {
        $seen = [];
        $ids = [];
        foreach ($values as $value) {
            if (is_string($value) && ctype_digit($value)) {
                $value = (int) $value;
            }

            if (!is_int($value) || $value <= 0 || isset($seen[$value])) {
                continue;
            }

            $seen[$value] = true;
            $ids[] = $value;
        }

        return $ids;
    }
}
