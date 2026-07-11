<?php

declare(strict_types=1);

if ($argc !== 2 || !is_file($argv[1])) {
    fwrite(STDERR, "Usage: php scripts/check-dependency-weight.php <downloaded-runtime-asset>\n");
    exit(12);
}

$root = dirname(__DIR__);
$composer = json_decode((string) file_get_contents($root . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
$lock = json_decode((string) file_get_contents($root . '/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
$budgets = json_decode(
    (string) file_get_contents($root . '/config/dependency-weight.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
$lockedPackages = array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []);
$packageMap = [];
foreach ($lockedPackages as $package) {
    $packageMap[$package['name']] = $package;
}

$direct = array_keys($composer['require-dev'] ?? []);
$errors = [];
printf("Dependency weight: %d direct, %d locked Composer packages.\n", count($direct), count($lockedPackages));

if (count($direct) > $budgets['maximum_direct_composer_packages']) {
    $errors[] = 'Direct Composer package count exceeds its budget.';
}
if (count($lockedPackages) > $budgets['maximum_locked_composer_packages']) {
    $errors[] = 'Locked Composer package count exceeds its budget.';
}

foreach ($direct as $packageName) {
    $closure = dependencyClosure($packageName, $packageMap);
    printf(" - %s: %d package(s) in transitive closure\n", $packageName, count($closure));
    if (count($closure) > $budgets['maximum_transitive_packages_per_direct_dependency']) {
        $errors[] = "{$packageName} exceeds the transitive dependency budget.";
    }
}

$runtimeBytes = filesize($argv[1]);
printf(" - jquery runtime asset: %d bytes\n", $runtimeBytes);
if ($runtimeBytes === false || $runtimeBytes > $budgets['maximum_runtime_asset_bytes']) {
    $errors[] = 'jQuery runtime asset exceeds its byte budget.';
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

fwrite(STDOUT, "Dependency-weight budgets passed.\n");

/**
 * @param array<string, array<string, mixed>> $packageMap
 * @return array<string, true>
 */
function dependencyClosure(string $rootPackage, array $packageMap): array
{
    $seen = [];
    $pending = [$rootPackage];
    while ($pending !== []) {
        $packageName = array_pop($pending);
        if (isset($seen[$packageName]) || !isset($packageMap[$packageName])) {
            continue;
        }
        $seen[$packageName] = true;
        foreach (array_keys($packageMap[$packageName]['require'] ?? []) as $dependency) {
            if (isset($packageMap[$dependency])) {
                $pending[] = $dependency;
            }
        }
    }

    return $seen;
}
