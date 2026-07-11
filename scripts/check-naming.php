<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$sourceRoot = $root . '/backend/src';
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS),
);

foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $source = (string) file_get_contents($file->getPathname());
    $tokens = token_get_all($source);
    $namespace = '';
    $className = null;

    for ($index = 0, $count = count($tokens); $index < $count; $index++) {
        $token = $tokens[$index];
        if (!is_array($token)) {
            continue;
        }

        if ($token[0] === T_NAMESPACE) {
            $parts = [];
            for ($cursor = $index + 1; $cursor < $count; $cursor++) {
                $part = $tokens[$cursor];
                if ($part === ';' || $part === '{') {
                    break;
                }
                if (is_array($part) && in_array($part[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                    $parts[] = $part[1];
                }
            }
            $namespace = implode('', $parts);
        }

        if ($token[0] === T_CLASS) {
            $name = nextNamedToken($tokens, $index);
            if ($name !== null) {
                $className = $name;
                if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $name)) {
                    $errors[] = "Class must use PascalCase: {$file->getPathname()}:{$token[2]} {$name}";
                }
            }
        }

        if ($token[0] === T_FUNCTION) {
            $name = nextNamedToken($tokens, $index);
            if ($name !== null && !preg_match('/^(?:__[a-z]+|[a-z][A-Za-z0-9]*)$/', $name)) {
                $errors[] = "Function/method must use camelCase: {$file->getPathname()}:{$token[2]} {$name}";
            }
        }

        if ($token[0] === T_CONST) {
            $name = nextNamedToken($tokens, $index);
            if ($name !== null && !preg_match('/^[A-Z][A-Z0-9_]*$/', $name)) {
                $errors[] = "Constant must use UPPER_SNAKE_CASE: {$file->getPathname()}:{$token[2]} {$name}";
            }
        }
    }

    if ($className === null) {
        if ($file->getFilename() !== 'bootstrap.php') {
            $errors[] = "Class source has no class declaration: {$file->getPathname()}";
        }
        continue;
    }

    if ($file->getBasename('.php') !== $className) {
        $errors[] = "Class filename must match {$className}: {$file->getPathname()}";
    }

    $relativeDirectory = trim(substr($file->getPath(), strlen($sourceRoot)), DIRECTORY_SEPARATOR);
    $expectedNamespace = 'SoloChess' . ($relativeDirectory === '' ? '' : '\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relativeDirectory));
    if ($namespace !== $expectedNamespace) {
        $errors[] = "Namespace/path mismatch: {$file->getPathname()} declares {$namespace}, expected {$expectedNamespace}";
    }
}

foreach (glob($root . '/backend/public/api/*.php') ?: [] as $path) {
    if (!preg_match('/^[a-z][a-z0-9-]*\.php$/', basename($path))) {
        $errors[] = "API filename must use lowercase kebab-case: {$path}";
    }
}

foreach (glob($root . '/scripts/*.sh') ?: [] as $path) {
    if (!preg_match('/^[a-z][a-z0-9-]*\.sh$/', basename($path))) {
        $errors[] = "Script filename must use lowercase kebab-case: {$path}";
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

fwrite(STDOUT, "Source naming contract passed.\n");

/** @param array<int, array{int, string, int}|string> $tokens */
function nextNamedToken(array $tokens, int $index): ?string
{
    for ($cursor = $index + 1, $count = count($tokens); $cursor < $count; $cursor++) {
        $candidate = $tokens[$cursor];
        if (is_array($candidate) && $candidate[0] === T_STRING) {
            return $candidate[1];
        }
        if ($candidate === '(' || $candidate === '{') {
            return null;
        }
    }

    return null;
}
