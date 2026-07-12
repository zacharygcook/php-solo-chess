<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$composer = json_decode((string) file_get_contents($root . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
$usage = json_decode((string) file_get_contents($root . '/config/dependency-usage.json'), true, flags: JSON_THROW_ON_ERROR);
$declaredComposer = array_keys($composer['require-dev'] ?? []);
$mappedComposer = array_keys($usage['composer_dev'] ?? []);
sort($declaredComposer, SORT_STRING);
sort($mappedComposer, SORT_STRING);
$errors = [];

if ($declaredComposer !== $mappedComposer) {
    $errors[] = 'Every direct Composer development dependency must have exactly one usage mapping.';
}

foreach ($usage as $dependencyGroup) {
    foreach ($dependencyGroup as $dependency => $evidenceItems) {
        foreach ($evidenceItems as $evidence) {
            $path = $root . '/' . $evidence['path'];
            if (!is_file($path)) {
                $errors[] = "{$dependency} usage evidence file is missing: {$evidence['path']}";
                continue;
            }
            if (!str_contains((string) file_get_contents($path), $evidence['contains'])) {
                $errors[] = "{$dependency} appears unused: {$evidence['path']} lacks its required integration.";
            }
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

printf(
    "Dependency usage passed for %d Composer tool(s) and %d runtime dependency/dependencies.\n",
    count($usage['composer_dev']),
    count($usage['runtime_cdn'] ?? []),
);
