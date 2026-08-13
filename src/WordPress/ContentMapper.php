<?php

declare(strict_types=1);

namespace VediSMM\WordPress\WordPress;

use VediSMM\WordPress\Domain\DraftInput;

final class ContentMapper
{
    /** @param array<string,mixed> $post @param array<string,array<int,mixed>> $targets @param array<string,mixed> $tracking */
    public static function fromPost(array $post, array $targets, array $tracking = []): DraftInput
    {
        $link = $post['permalink'] ?? $post['guid'] ?? null;

        return new DraftInput(
            (string) ($post['post_title'] ?? ''),
            (string) ($post['post_content'] ?? ''),
            is_string($link) ? $link : null,
            $targets['account_ids'] ?? [],
            $targets['group_ids'] ?? [],
            $targets['media_ids'] ?? [],
            self::checked($tracking['shorten_links'] ?? false),
            self::checked($tracking['add_source'] ?? false)
        );
    }

    private static function checked(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }
}
