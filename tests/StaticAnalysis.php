<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [];
foreach (['vedismm.php', 'uninstall.php'] as $rootFile) {
    $path = $root . '/' . $rootFile;
    if (is_file($path)) {
        $files[] = $path;
    }
}

foreach (['src', 'tests'] as $directory) {
    $path = $root . '/' . $directory;
    if (!is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

sort($files, SORT_STRING);
$failed = [];
foreach ($files as $file) {
    $command = PHP_BINARY . ' -l ' . escapeshellarg($file);
    exec($command, $output, $exitCode);
    if ($exitCode !== 0) {
        $failed[] = $file;
    }
}

if ($failed !== []) {
    echo "Static analysis failed:\n";
    foreach ($failed as $file) {
        echo "- {$file}\n";
    }
    exit(1);
}

$metabox = (string) file_get_contents($root . '/src/WordPress/MetaBox.php');
$draftInput = (string) file_get_contents($root . '/src/Domain/DraftInput.php');
$mapper = (string) file_get_contents($root . '/src/WordPress/ContentMapper.php');
$english = (string) file_get_contents($root . '/docs/en/guide.md');
$russian = (string) file_get_contents($root . '/docs/ru/guide.md');
$pot = (string) file_get_contents($root . '/languages/vedismm.pot');
$po = (string) file_get_contents($root . '/languages/vedismm-ru_RU.po');

$contractChecks = [
    'shortening checkbox name' => str_contains($metabox, 'vedismm_tracking[shorten_links]'),
    'source checkbox name' => str_contains($metabox, 'vedismm_tracking[add_source]'),
    'source dependency state' => str_contains($metabox, 'aria-disabled'),
    'nested tracking request shape' => str_contains($draftInput, "'tracking'"),
    'strict WordPress checkbox normalization' => str_contains($mapper, "=== '1'"),
    'English UTM precedence documentation' => str_contains($english, 'utm_source') && str_contains($english, 'utm_term'),
    'Russian UTM precedence documentation' => str_contains($russian, 'utm_source') && str_contains($russian, 'utm_term'),
    'POT tracking controls' => str_contains($pot, 'Shorten links') && str_contains($pot, 'Add network source'),
    'Russian tracking controls' => str_contains($po, 'Сокращать ссылки') && str_contains($po, 'Добавлять источник площадки'),
    'no plugin URL rewriting' => !str_contains($mapper, 'parse_url') && !str_contains($mapper, 'go.vedismm.ru'),
    'no generated-link state' => !str_contains($metabox . $draftInput . $mapper, 'generated_link'),
];

foreach ($contractChecks as $name => $passed) {
    if (!$passed) {
        echo "Static contract check failed: {$name}\n";
        exit(1);
    }
}

echo 'Static analysis checked ' . count($files) . " PHP files.\n";
