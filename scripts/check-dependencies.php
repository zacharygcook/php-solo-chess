<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$composer = json_decode((string) file_get_contents($root . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
$lock = json_decode((string) file_get_contents($root . '/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
$policy = json_decode((string) file_get_contents($root . '/config/dependency-policy.json'), true, flags: JSON_THROW_ON_ERROR);
$errors = [];
$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

foreach ($policy['composer_dev'] as $package => $approval) {
    $configured = $composer['require-dev'][$package] ?? null;
    if ($configured !== $approval['version']) {
        $errors[] = "{$package} must be exactly pinned to approved version {$approval['version']}.";
    }

    $lockedVersion = null;
    foreach ($lock['packages-dev'] as $lockedPackage) {
        if ($lockedPackage['name'] === $package) {
            $lockedVersion = ltrim($lockedPackage['version'], 'v');
            break;
        }
    }
    if ($lockedVersion !== $approval['version']) {
        $errors[] = "composer.lock does not contain approved {$package} {$approval['version']}.";
    }

    if ($now < new DateTimeImmutable($approval['eligible_after'])) {
        $errors[] = "{$package} {$approval['version']} is younger than the 30-day adoption window.";
    }
}

if (count($composer['require-dev']) !== count($policy['composer_dev'])) {
    $errors[] = 'Every direct Composer development dependency must have an approval record.';
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

fwrite(STDOUT, "Dependency pins and minimum release ages passed.\n");
