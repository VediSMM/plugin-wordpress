<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [];
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

echo 'Static analysis checked ' . count($files) . " PHP files.\n";
