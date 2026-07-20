<?php

declare(strict_types=1);

namespace VediSMM\WordPress\WordPress;

use VediSMM\WordPress\Domain\DraftInput;

final class ContentMapper
{
    /** @param array<string,mixed> $post @param array<string,array<int,mixed>> $targets */
    public static function fromPost(array $post, array $targets): DraftInput
    {
        $link = $post['permalink'] ?? $post['guid'] ?? null;

        return new DraftInput(
            (string) ($post['post_title'] ?? ''),
            (string) ($post['post_content'] ?? ''),
            is_string($link) ? $link : null,
            $targets['account_ids'] ?? [],
            $targets['group_ids'] ?? [],
            $targets['media_ids'] ?? []
        );
    }
}
