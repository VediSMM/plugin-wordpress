<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'VediSMM\\WordPress\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

$passed = 0;
$failed = 0;

function wp_submission_check(string $name, bool $condition, mixed $detail = null): void
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo "  ✓ {$name}\n";
        return;
    }

    $failed++;
    echo "  ✗ FAIL: {$name}\n";
    if ($detail !== null) {
        echo '    - ' . json_encode($detail, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }
}

function wp_submission_finish(): never
{
    global $passed, $failed;

    echo "\n----------------------------------------\n";
    echo "Passed: {$passed}, Failed: {$failed}\n";
    exit($failed > 0 ? 1 : 0);
}

$gatewayClass = 'VediSMM\\WordPress\\Api\\VediSMMGateway';
$serviceClass = 'VediSMM\\WordPress\\Application\\SubmissionService';
$resultClass = 'VediSMM\\WordPress\\Application\\SubmissionResult';
$settingsClass = 'VediSMM\\WordPress\\WordPress\\SettingsPage';
$pluginClass = 'VediSMM\\WordPress\\WordPress\\Plugin';
$metaboxClass = 'VediSMM\\WordPress\\WordPress\\MetaBox';
$handlerClass = 'VediSMM\\WordPress\\WordPress\\PostSubmissionHandler';

foreach ([
    'VediSMM gateway exists' => $gatewayClass,
    'Submission service exists' => $serviceClass,
    'Submission result exists' => $resultClass,
    'Settings page exists' => $settingsClass,
    'Plugin bootstrap exists' => $pluginClass,
    'Meta box adapter exists' => $metaboxClass,
    'Native post submission handler exists' => $handlerClass,
] as $label => $class) {
    wp_submission_check($label, class_exists($class));
}
if (!class_exists($gatewayClass)
    || !class_exists($serviceClass)
    || !class_exists($resultClass)
    || !class_exists($settingsClass)
    || !class_exists($pluginClass)
    || !class_exists($metaboxClass)
    || !class_exists($handlerClass)) {
    wp_submission_finish();
}

$calls = [];
$transport = static function (array $request) use (&$calls): array {
    $calls[] = $request;
    if ($request['path'] === '/posts') {
        return [
            'status' => 201,
            'headers' => [
                'Request-Id' => 'fixture-draft-0001',
                'ETag' => '"v1"',
            ],
            'body' => [
                'data' => [
                    'id' => 301,
                    'status' => 'draft',
                    'version' => 1,
                ],
            ],
        ];
    }

    if ($request['path'] === '/posts/301/schedule') {
        return [
            'status' => 200,
            'headers' => [
                'Request-Id' => 'fixture-schedule-0001',
                'ETag' => '"v2"',
            ],
            'body' => [
                'data' => [
                    'id' => 301,
                    'status' => 'scheduled',
                    'version' => 2,
                ],
            ],
        ];
    }

    if ($request['path'] === '/posts/301/publish') {
        return [
            'status' => 202,
            'headers' => [
                'Request-Id' => 'fixture-publish-0001',
            ],
            'body' => [
                'data' => [
                    'id' => 401,
                    'status' => 'queued',
                ],
            ],
        ];
    }

    throw new RuntimeException('unexpected request ' . $request['path']);
};

$gateway = new $gatewayClass('secret-token', $transport);
$service = new $serviceClass($gateway, 'install-abc');
$post = [
    'ID' => 42,
    'post_title' => '  Launch &amp; update  ',
    'post_content' => '<p>Body&nbsp;text</p>',
    'permalink' => 'https://example.test/launch',
];
$targets = ['account_ids' => ['101', '101'], 'group_ids' => ['201'], 'media_ids' => []];
$context = [
    'can_publish' => true,
    'nonce_valid' => true,
    'revision' => 7,
    'tracking' => ['shorten_links' => '1', 'add_source' => '1'],
];

$draftResult = $service->submit($post, $targets, $context + ['action' => 'draft']);
wp_submission_check('draft submission returns a typed result',
    $draftResult instanceof $resultClass
    && $draftResult->postId === 301
    && $draftResult->status === 'draft'
    && $draftResult->jobId === null
    && $draftResult->requestId === 'fixture-draft-0001');
wp_submission_check('draft request carries body, token and stable idempotency key',
    $calls[0]['method'] === 'POST'
    && $calls[0]['path'] === '/posts'
    && $calls[0]['headers']['Authorization'] === 'Bearer secret-token'
    && $calls[0]['headers']['Idempotency-Key'] === 'cms:install-abc:post:42:7:draft'
    && $calls[0]['body'] === [
        'title' => 'Launch & update',
        'content' => 'Body text',
        'link' => 'https://example.test/launch',
        'account_ids' => [101],
        'group_ids' => [201],
        'media_ids' => [],
        'options' => [
            'tracking' => [
                'shorten_links' => true,
                'add_source' => true,
            ],
        ],
    ],
    $calls[0] ?? null);
wp_submission_check('gateway emits no top-level tracking fields or rewritten URLs',
    !array_key_exists('shorten_links', $calls[0]['body'])
    && !array_key_exists('add_source', $calls[0]['body'])
    && $calls[0]['body']['link'] === 'https://example.test/launch'
    && $calls[0]['body']['content'] === 'Body text',
    $calls[0]['body'] ?? null);

$calls = [];
$scheduleResult = $service->submit($post, $targets, $context + [
    'action' => 'schedule',
    'scheduled_at' => '2026-08-01T12:30:00+03:00',
]);
wp_submission_check('schedule creates a draft then schedules it with the returned ETag',
    $scheduleResult->status === 'scheduled'
    && count($calls) === 2
    && $calls[1]['path'] === '/posts/301/schedule'
    && $calls[1]['headers']['If-Match'] === '"v1"'
    && $calls[1]['body'] === ['scheduled_at' => '2026-08-01T12:30:00+03:00']);

$calls = [];
$publishResult = $service->submit($post, $targets, $context + ['action' => 'publish']);
wp_submission_check('publish creates a draft then queues publish with version and idempotency',
    $publishResult->status === 'queued'
    && $publishResult->jobId === 401
    && count($calls) === 2
    && $calls[1]['path'] === '/posts/301/publish'
    && $calls[1]['headers']['Idempotency-Key'] === 'cms:install-abc:post:42:7:publish'
    && $calls[1]['body'] === ['version' => 1]);
wp_submission_check('submission result exposes safe audit metadata',
    $publishResult->audit === [
        'entity_type' => 'post',
        'entity_id' => 42,
        'revision' => 7,
        'action' => 'publish',
        'request_id' => 'fixture-publish-0001',
    ]);

$sameKey = $service->submit($post, $targets, $context + ['action' => 'draft'])->idempotencyKey;
wp_submission_check('duplicate submission reuses the same stable idempotency key',
    $sameKey === 'cms:install-abc:post:42:7:draft');

try {
    $service->submit($post, $targets, ['can_publish' => false, 'nonce_valid' => true, 'revision' => 7, 'action' => 'draft']);
    wp_submission_check('permission failure blocks submission', false);
} catch (RuntimeException $exception) {
    wp_submission_check('permission failure blocks submission',
        $exception->getMessage() === 'vedismm_permission_denied');
}

try {
    $service->submit($post, $targets, ['can_publish' => true, 'nonce_valid' => false, 'revision' => 7, 'action' => 'draft']);
    wp_submission_check('nonce failure blocks submission', false);
} catch (RuntimeException $exception) {
    wp_submission_check('nonce failure blocks submission',
        $exception->getMessage() === 'vedismm_invalid_nonce');
}

$redactingGateway = new $gatewayClass('secret-token', static function (): array {
    throw new RuntimeException('upstream leaked Bearer secret-token');
});
try {
    (new $serviceClass($redactingGateway, 'install-abc'))
        ->submit($post, $targets, $context + ['action' => 'draft']);
    wp_submission_check('gateway errors redact credentials', false);
} catch (RuntimeException $exception) {
    wp_submission_check('gateway errors redact credentials',
        str_contains($exception->getMessage(), 'vedismm_api_error')
        && !str_contains($exception->getMessage(), 'secret-token'));
}

wp_submission_check('settings keep saved token on empty resubmission',
    $settingsClass::sanitizeToken('', 'saved-token') === 'saved-token');
wp_submission_check('settings can explicitly remove token',
    $settingsClass::sanitizeToken('', 'saved-token', true) === null);
wp_submission_check('settings never render the saved token value',
    $settingsClass::renderTokenValue('saved-token') === '');

$wpHooks = [];
$wpMeta = [];
$wpNonceValid = true;
$wpCanEdit = true;
if (!function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        global $wpHooks;
        $wpHooks[$hook][] = [$callback, $priority, $acceptedArgs];
    }
}
if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce(mixed $nonce, string $action): int|false
    {
        global $wpNonceValid;
        return $wpNonceValid && $nonce === 'valid-nonce' && $action === \VediSMM\WordPress\WordPress\MetaBox::NONCE_ACTION ? 1 : false;
    }
}
if (!function_exists('current_user_can')) {
    function current_user_can(string $capability, mixed ...$args): bool
    {
        global $wpCanEdit;
        return $wpCanEdit && $capability === 'edit_post' && ($args[0] ?? null) === 42;
    }
}
if (!function_exists('update_post_meta')) {
    function update_post_meta(int $postId, string $key, mixed $value): void
    {
        global $wpMeta;
        $wpMeta[$postId][$key] = $value;
    }
}
if (!function_exists('get_post_meta')) {
    function get_post_meta(int $postId, string $key, bool $single = false): mixed
    {
        global $wpMeta;
        return $wpMeta[$postId][$key] ?? ($single ? '' : []);
    }
}
if (!function_exists('get_permalink')) {
    function get_permalink(int $postId): string
    {
        return 'https://example.test/post/' . $postId;
    }
}
if (!function_exists('wp_is_post_autosave')) {
    function wp_is_post_autosave(int $postId): false
    {
        return false;
    }
}
if (!function_exists('wp_is_post_revision')) {
    function wp_is_post_revision(int $postId): false
    {
        return false;
    }
}

$calls = [];
$handler = new $handlerClass($service);
$handler->register();
wp_submission_check('WordPress registers the native save_post submission boundary',
    isset($wpHooks['save_post'][0])
    && $wpHooks['save_post'][0][1] === 10
    && $wpHooks['save_post'][0][2] === 3);

$_POST = [
    'vedismm_nonce' => 'valid-nonce',
    'vedismm_submit_action' => 'draft',
    'vedismm_tracking' => ['shorten_links' => '1', 'add_source' => '1'],
];
$handler->handle(42, (object) [
    'ID' => 42,
    'post_type' => 'post',
    'post_title' => 'Tracked post',
    'post_content' => '<p>Original body</p>',
    'post_modified_gmt' => '1970-01-01 00:00:07',
], true);
wp_submission_check('native save handler verifies request, persists state and reaches the gateway',
    ($wpMeta[42]['_vedismm_tracking'] ?? null) === ['shorten_links' => true, 'add_source' => true]
    && count($calls) === 1
    && ($calls[0]['body']['options']['tracking'] ?? null) === ['shorten_links' => true, 'add_source' => true]
    && ($calls[0]['body']['link'] ?? null) === 'https://example.test/post/42'
    && !array_key_exists('shorten_links', $calls[0]['body'])
    && !array_key_exists('add_source', $calls[0]['body']),
    $calls[0] ?? null);

$calls = [];
$_POST['vedismm_tracking'] = ['shorten_links' => '0', 'add_source' => '1'];
$handler->handle(42, (object) ['ID' => 42, 'post_type' => 'post', 'post_title' => 'Tracked post'], true);
wp_submission_check('native save handler strictly normalizes and persists the dependency clamp',
    ($wpMeta[42]['_vedismm_tracking'] ?? null) === ['shorten_links' => false, 'add_source' => false]
    && ($calls[0]['body']['options']['tracking'] ?? null) === ['shorten_links' => false, 'add_source' => false]);

$wpNonceValid = false;
$calls = [];
$handler->handle(42, (object) ['ID' => 42, 'post_type' => 'post', 'post_title' => 'Blocked'], true);
wp_submission_check('invalid native nonce blocks persistence and API submission', $calls === []);
$wpNonceValid = true;
$wpCanEdit = false;
$handler->handle(42, (object) ['ID' => 42, 'post_type' => 'post', 'post_title' => 'Blocked'], true);
wp_submission_check('missing edit_post capability blocks API submission', $calls === []);
$wpCanEdit = true;

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field(string $action, string $name): void
    {
        echo '<input type="hidden" name="' . $name . '" data-nonce-action="' . $action . '">';
    }
}

$wpMeta[42]['_vedismm_tracking'] = ['shorten_links' => true, 'add_source' => true];
ob_start();
(new $metaboxClass())->render((object) ['ID' => 42]);
$metabox = (string) ob_get_clean();
wp_submission_check('tracking controls are native accessible dependent checkboxes',
    str_contains($metabox, 'name="vedismm_tracking[shorten_links]"')
    && str_contains($metabox, 'name="vedismm_tracking[add_source]"')
    && str_contains($metabox, 'aria-describedby="vedismm-shorten-links-help"')
    && str_contains($metabox, 'aria-describedby="vedismm-add-source-help"')
    && str_contains($metabox, 'disabled')
    && str_contains($metabox, 'utm_source')
    && str_contains($metabox, 'utm_term')
    && str_contains($metabox, 'name="vedismm_submit_action"')
    && str_contains($metabox, 'type="submit"')
    && substr_count($metabox, ' checked') >= 2
    && str_contains($metabox, $metaboxClass::NONCE_ACTION),
    $metabox);

wp_submission_finish();
