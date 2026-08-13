<?php

declare(strict_types=1);

namespace VediSMM\WordPress\Application;

use RuntimeException;
use VediSMM\WordPress\Api\VediSMMGateway;
use VediSMM\WordPress\Domain\Idempotency;
use VediSMM\WordPress\WordPress\ContentMapper;

final class SubmissionService
{
    public function __construct(
        private readonly VediSMMGateway $gateway,
        private readonly string $installationId
    ) {
    }

    /** @param array<string,mixed> $post @param array<string,array<int,mixed>> $targets @param array<string,mixed> $context */
    public function submit(array $post, array $targets, array $context): SubmissionResult
    {
        if (($context['can_publish'] ?? false) !== true) {
            throw new RuntimeException('vedismm_permission_denied');
        }
        if (($context['nonce_valid'] ?? false) !== true) {
            throw new RuntimeException('vedismm_invalid_nonce');
        }

        $action = (string) ($context['action'] ?? 'draft');
        $entityId = (int) ($post['ID'] ?? 0);
        $revision = (int) ($context['revision'] ?? 1);
        $tracking = is_array($context['tracking'] ?? null) ? $context['tracking'] : [];
        $draft = ContentMapper::fromPost($post, $targets, $tracking);
        $draftKey = Idempotency::forAction($this->installationId, 'post', $entityId, $revision, 'draft');
        $draftResponse = $this->gateway->createDraft($draft, $draftKey);
        $draftData = is_array($draftResponse['body']['data'] ?? null) ? $draftResponse['body']['data'] : [];
        $draftId = (int) ($draftData['id'] ?? 0);
        $version = (int) ($draftData['version'] ?? 1);
        $etag = $draftResponse['headers']['ETag'] ?? null;

        if ($action === 'draft') {
            return $this->result($entityId, $revision, 'draft', $draftResponse, $draftId, null, $draftKey, $etag, $version);
        }

        if ($action === 'schedule') {
            $scheduleResponse = $this->gateway->schedule(
                $draftId,
                (string) $etag,
                (string) ($context['scheduled_at'] ?? '')
            );

            return $this->result($entityId, $revision, 'schedule', $scheduleResponse, $draftId, null, $draftKey, $scheduleResponse['headers']['ETag'] ?? null, $version + 1);
        }

        if ($action === 'publish') {
            $publishKey = Idempotency::forAction($this->installationId, 'post', $entityId, $revision, 'publish');
            $publishResponse = $this->gateway->publish($draftId, $version, $publishKey);
            $publishData = is_array($publishResponse['body']['data'] ?? null) ? $publishResponse['body']['data'] : [];

            return $this->result(
                $entityId,
                $revision,
                'publish',
                $publishResponse,
                $draftId,
                (int) ($publishData['id'] ?? 0),
                $publishKey,
                null,
                $version
            );
        }

        throw new RuntimeException('vedismm_unknown_action');
    }

    /** @param array{headers:array<string,string>,body:array<string,mixed>} $response */
    private function result(
        int $entityId,
        int $revision,
        string $action,
        array $response,
        int $postId,
        ?int $jobId,
        ?string $idempotencyKey,
        ?string $etag,
        int $version
    ): SubmissionResult {
        $data = is_array($response['body']['data'] ?? null) ? $response['body']['data'] : [];
        $requestId = $response['headers']['Request-Id'] ?? null;

        return new SubmissionResult(
            $postId,
            (string) ($data['status'] ?? 'unknown'),
            $jobId,
            $requestId,
            $idempotencyKey,
            [
                'entity_type' => 'post',
                'entity_id' => $entityId,
                'revision' => $revision,
                'action' => $action,
                'request_id' => $requestId,
            ],
            $etag,
            $version
        );
    }
}
