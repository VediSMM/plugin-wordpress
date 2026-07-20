<?php

declare(strict_types=1);

namespace VediSMM\WordPress\Application;

final readonly class SubmissionResult
{
    /** @param array<string,mixed> $audit */
    public function __construct(
        public int $postId,
        public string $status,
        public ?int $jobId,
        public ?string $requestId,
        public ?string $idempotencyKey,
        public array $audit,
        public ?string $etag = null,
        public int $version = 1
    ) {
    }
}
