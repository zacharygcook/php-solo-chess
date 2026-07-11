<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$config = json_decode(
    (string) file_get_contents($root . '/config/architecture-layers.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
$violations = [];
$sourceFiles = array_merge(
    recursivePhpFiles($root . '/backend/src'),
    recursivePhpFiles($root . '/backend/public/api'),
);

foreach ($sourceFiles as $path) {
    if (basename($path) === 'bootstrap.php') {
        continue;
    }

    $relative = substr($path, strlen($root) + 1);
    $sourceLayer = str_starts_with($relative, 'backend/public/api/')
        ? 'Api'
        : explode('/', $relative)[2];
    $allowed = $config['layers'][$sourceLayer] ?? null;
    if ($allowed === null) {
        $violations[] = "Unclassified architecture layer for {$relative}: {$sourceLayer}";
        continue;
    }

    $source = (string) file_get_contents($path);
    preg_match_all('/^use SoloChess\\\\([A-Za-z]+)\\\\/m', $source, $matches);
    foreach (array_unique($matches[1]) as $targetLayer) {
        if (!in_array($targetLayer, $allowed, true)) {
            $violations[] = "Forbidden dependency {$sourceLayer} -> {$targetLayer} in {$relative}";
        }
    }
}

if ($violations !== []) {
    fwrite(STDERR, implode("\n", $violations) . "\n");
    exit(1);
}

fwrite(STDOUT, "Architecture layer boundaries passed.\n");

/** @return list<string> */
function recursivePhpFiles(string $directory): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    sort($files, SORT_STRING);

    return $files;
}
