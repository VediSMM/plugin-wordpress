<?php

declare(strict_types=1);

namespace VediSMM\WordPress\Domain;

final class Idempotency
{
    public static function forAction(
        string $installationId,
        string $entityType,
        int $entityId,
        int $revision,
        string $action
    ): string {
        return sprintf(
            'cms:%s:%s:%d:%d:%s',
            self::segment($installationId),
            self::segment($entityType),
            $entityId,
            $revision,
            self::segment($action)
        );
    }

    private static function segment(string $value): string
    {
        $segment = preg_replace('/[^A-Za-z0-9._-]+/', '-', trim($value));
        $segment = trim((string) $segment, '-');

        return $segment === '' ? 'unknown' : $segment;
    }
}
