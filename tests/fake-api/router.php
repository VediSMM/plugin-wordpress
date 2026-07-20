<?php

declare(strict_types=1);

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
$body = file_get_contents('php://input') ?: '';

header('Content-Type: application/json');
header('Request-Id: fixture-wordpress-smoke');

if ($method === 'POST' && $path === '/api/v1/posts') {
    file_put_contents(sys_get_temp_dir() . '/vedismm-wordpress-draft.json', $body);
    http_response_code(201);
    header('ETag: "v1"');
    echo json_encode(['data' => ['id' => 301, 'status' => 'draft', 'version' => 1]], JSON_THROW_ON_ERROR);
    return;
}

if ($method === 'POST' && $path === '/api/v1/posts/301/publish') {
    http_response_code(202);
    echo json_encode(['data' => ['id' => 401, 'status' => 'queued']], JSON_THROW_ON_ERROR);
    return;
}

http_response_code(401);
header('Content-Type: application/problem+json');
echo json_encode(['code' => 'unauthorized', 'status' => 401], JSON_THROW_ON_ERROR);
