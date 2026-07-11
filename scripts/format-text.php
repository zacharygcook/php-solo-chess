<?php

declare(strict_types=1);

$mode = $argv[1] ?? '';
if (!in_array($mode, ['--check', '--write'], true)) {
    fwrite(STDERR, "Usage: php scripts/format-text.php --check|--write\n");
    exit(12);
}

$root = dirname(__DIR__);
$extensions = ['css', 'html', 'js'];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/frontend', FilesystemIterator::SKIP_DOTS)
);
$unformatted = [];

foreach ($iterator as $file) {
    if (!$file->isFile() || !in_array($file->getExtension(), $extensions, true)) {
        continue;
    }

    $path = $file->getPathname();
    $original = file_get_contents($path);
    if ($original === false) {
        throw new RuntimeException("Unable to read {$path}");
    }

    $formatted = str_replace(["\r\n", "\r"], "\n", $original);
    $formatted = preg_replace('/[ \t]+$/m', '', $formatted);
    if ($formatted === null) {
        throw new RuntimeException("Unable to format {$path}");
    }
    $formatted = rtrim($formatted, "\n") . "\n";

    if ($formatted === $original) {
        continue;
    }

    $relativePath = substr($path, strlen($root) + 1);
    if ($mode === '--write') {
        file_put_contents($path, $formatted);
        fwrite(STDOUT, "Formatted {$relativePath}\n");
    } else {
        $unformatted[] = $relativePath;
    }
}

if ($unformatted !== []) {
    fwrite(STDERR, "Frontend files need formatting:\n - " . implode("\n - ", $unformatted) . "\n");
    exit(1);
}
