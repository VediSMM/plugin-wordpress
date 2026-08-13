<?php

declare(strict_types=1);

namespace VediSMM\WordPress\WordPress;

use RuntimeException;

final class WordPressTransport
{
    /** @param array<string, mixed> $request @return array<string, mixed> */
    public function __invoke(array $request): array
    {
        if (!function_exists('wp_remote_request')) {
            throw new RuntimeException('WordPress HTTP API is unavailable');
        }

        $response = wp_remote_request(
            rtrim((string) ($request['base_url'] ?? ''), '/') . (string) ($request['path'] ?? ''),
            [
                'method' => (string) ($request['method'] ?? 'POST'),
                'headers' => is_array($request['headers'] ?? null) ? $request['headers'] : [],
                'body' => function_exists('wp_json_encode')
                    ? wp_json_encode($request['body'] ?? [])
                    : json_encode($request['body'] ?? [], JSON_THROW_ON_ERROR),
                'timeout' => 20,
            ],
        );
        if (function_exists('is_wp_error') && is_wp_error($response)) {
            throw new RuntimeException((string) $response->get_error_message());
        }
        if (!is_array($response)) {
            throw new RuntimeException('Invalid WordPress HTTP response');
        }

        $body = function_exists('wp_remote_retrieve_body')
            ? (string) wp_remote_retrieve_body($response)
            : (string) ($response['body'] ?? '');
        $decoded = json_decode($body, true);
        $headers = function_exists('wp_remote_retrieve_headers')
            ? wp_remote_retrieve_headers($response)
            : ($response['headers'] ?? []);

        return [
            'status' => function_exists('wp_remote_retrieve_response_code')
                ? (int) wp_remote_retrieve_response_code($response)
                : (int) ($response['response']['code'] ?? 0),
            'headers' => is_object($headers) && method_exists($headers, 'getAll')
                ? $headers->getAll()
                : (is_array($headers) ? $headers : []),
            'body' => is_array($decoded) ? $decoded : [],
        ];
    }
}
