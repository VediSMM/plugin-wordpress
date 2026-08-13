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

function wp_domain_check(string $name, bool $condition, mixed $detail = null): void
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

function wp_domain_finish(): never
{
    global $passed, $failed;

    echo "\n----------------------------------------\n";
    echo "Passed: {$passed}, Failed: {$failed}\n";
    exit($failed > 0 ? 1 : 0);
}

$draftInputClass = 'VediSMM\\WordPress\\Domain\\DraftInput';
$normalizerClass = 'VediSMM\\WordPress\\Domain\\Normalizer';
$idempotencyClass = 'VediSMM\\WordPress\\Domain\\Idempotency';
$mapperClass = 'VediSMM\\WordPress\\WordPress\\ContentMapper';

wp_domain_check('DraftInput value exists', class_exists($draftInputClass));
wp_domain_check('Normalizer service exists', class_exists($normalizerClass));
wp_domain_check('Idempotency service exists', class_exists($idempotencyClass));
wp_domain_check('WordPress content mapper exists', class_exists($mapperClass));
if (!class_exists($draftInputClass)
    || !class_exists($normalizerClass)
    || !class_exists($idempotencyClass)
    || !class_exists($mapperClass)) {
    wp_domain_finish();
}

$longTitle = str_repeat('Ж', 220);
$draft = new $draftInputClass(
    $longTitle,
    "  <p>Hello&nbsp;<strong>CMS</strong></p>\n<p>Привет</p>  ",
    ' HTTPS://Example.Test/Post?utm_source=wp ',
    [101, '101', 0, -5, 102, 'not-an-id'],
    ['201', 201, 0],
    [301, 301, 302]
);

wp_domain_check('DraftInput truncates titles with Unicode-safe length',
    mb_strlen($draft->title) === 190);
wp_domain_check('DraftInput normalizes HTML content to readable text',
    $draft->content === 'Hello CMS Привет',
    $draft->content);
wp_domain_check('DraftInput canonicalizes only HTTP(S) URLs',
    $draft->link === 'https://Example.Test/Post?utm_source=wp',
    $draft->link);
wp_domain_check('DraftInput keeps positive unique account, group and media IDs',
    $draft->accountIds === [101, 102]
    && $draft->groupIds === [201]
    && $draft->mediaIds === [301, 302]);
wp_domain_check('DraftInput preserves legacy construction with tracking disabled',
    $draft->toArray()['options']['tracking'] === [
        'shorten_links' => false,
        'add_source' => false,
    ],
    $draft->toArray());

$trackedDraft = new $draftInputClass(
    'Tracked',
    'Body',
    'https://example.test/tracked',
    [],
    [],
    [],
    true,
    true
);
wp_domain_check('DraftInput serializes explicit booleans only under options.tracking',
    $trackedDraft->toArray()['options'] === [
        'tracking' => [
            'shorten_links' => true,
            'add_source' => true,
        ],
    ]
    && !array_key_exists('shorten_links', $trackedDraft->toArray())
    && !array_key_exists('add_source', $trackedDraft->toArray()),
    $trackedDraft->toArray());
wp_domain_check('non-HTTP URL normalizes to null',
    $normalizerClass::url('javascript:alert(1)') === null
    && $normalizerClass::url('ftp://example.test/file') === null);
wp_domain_check('idempotency keys use the exact CMS scoped format',
    $idempotencyClass::forAction('install-abc', 'post', 42, 7, 'draft')
        === 'cms:install-abc:post:42:7:draft');

$mapped = $mapperClass::fromPost([
    'ID' => 42,
    'post_title' => '  Launch &amp; update  ',
    'post_content' => '<h1>Title</h1><p>Body&nbsp;text</p>',
    'permalink' => 'https://example.test/launch',
], [
    'account_ids' => ['101', '101', '102'],
    'group_ids' => ['201'],
    'media_ids' => [],
]);
wp_domain_check('WordPress mapper is pure and maps array posts to DraftInput',
    $mapped instanceof $draftInputClass
    && $mapped->title === 'Launch & update'
    && $mapped->content === 'Title Body text'
    && $mapped->link === 'https://example.test/launch'
    && $mapped->accountIds === [101, 102]
    && $mapped->groupIds === [201]
    && $mapped->mediaIds === []);

$mappedTracking = $mapperClass::fromPost([
    'post_title' => 'Tracked',
    'post_content' => 'Body',
    'permalink' => 'https://example.test/tracked',
], [], [
    'shorten_links' => '1',
    'add_source' => '1',
]);
wp_domain_check('WordPress mapper recognizes native checked values',
    $mappedTracking->toArray()['options']['tracking'] === [
        'shorten_links' => true,
        'add_source' => true,
    ]);

$mappedUnchecked = $mapperClass::fromPost([], [], [
    'shorten_links' => 'false',
    'add_source' => 'on',
]);
wp_domain_check('WordPress mapper rejects truthy strings and enforces the source dependency',
    $mappedUnchecked->toArray()['options']['tracking'] === [
        'shorten_links' => false,
        'add_source' => false,
    ],
    $mappedUnchecked->toArray());

$mappedDependent = $mapperClass::fromPost([], [], [
    'shorten_links' => false,
    'add_source' => true,
]);
wp_domain_check('WordPress mapper forces source off when shortening is off',
    $mappedDependent->toArray()['options']['tracking']['add_source'] === false);

wp_domain_finish();
