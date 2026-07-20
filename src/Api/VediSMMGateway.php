<?php

declare(strict_types=1);

namespace VediSMM\WordPress\Api;

use Closure;
use RuntimeException;
use Throwable;
use VediSMM\WordPress\Domain\DraftInput;

final class VediSMMGateway
{
    private Closure $transport;

    /** @param callable(array<string,mixed>): array<string,mixed> $transport */
    public function __construct(
        private readonly string $token,
        callable $transport,
        private readonly string $baseUrl = 'https://vedismm.ru/api/v1'
    ) {
        $this->transport = Closure::fromCallable($transport);
    }

    /** @return array{status:int,headers:array<string,string>,body:array<string,mixed>} */
    public function createDraft(DraftInput $input, string $idempotencyKey): array
    {
        return $this->request('POST', '/posts', [
            'Idempotency-Key' => $idempotencyKey,
        ], $input->toArray());
    }

    /** @return array{status:int,headers:array<string,string>,body:array<string,mixed>} */
    public function schedule(int $postId, string $etag, string $scheduledAt): array
    {
        return $this->request('POST', "/posts/{$postId}/schedule", [
            'If-Match' => $etag,
        ], ['scheduled_at' => $scheduledAt]);
    }

    /** @return array{status:int,headers:array<string,string>,body:array<string,mixed>} */
    public function publish(int $postId, int $version, string $idempotencyKey): array
    {
        return $this->request('POST', "/posts/{$postId}/publish", [
            'Idempotency-Key' => $idempotencyKey,
        ], ['version' => $version]);
    }

    /**
     * @param array<string,string> $headers
     * @param array<string,mixed> $body
     * @return array{status:int,headers:array<string,string>,body:array<string,mixed>}
     */
    private function request(string $method, string $path, array $headers, array $body): array
    {
        $request = [
            'method' => $method,
            'base_url' => $this->baseUrl,
            'path' => $path,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json',
            ] + $headers,
            'body' => $body,
        ];

        try {
            $response = ($this->transport)($request);
        } catch (Throwable $exception) {
            throw new RuntimeException('vedismm_api_error: ' . $this->redact($exception->getMessage()), 0, $exception);
        }

        return [
            'status' => (int) ($response['status'] ?? 0),
            'headers' => is_array($response['headers'] ?? null) ? $response['headers'] : [],
            'body' => is_array($response['body'] ?? null) ? $response['body'] : [],
        ];
    }

    private function redact(string $message): string
    {
        $redacted = str_replace($this->token, '[redacted]', $message);

        return (string) preg_replace('/Bearer\s+\S+/i', 'Bearer [redacted]', $redacted);
    }
}
