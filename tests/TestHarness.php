<?php

declare(strict_types=1);

final class TestHarness
{
    /** @var array<string, Closure(): void> */
    private array $tests = [];

    public function test(string $name, Closure $test): void
    {
        $this->tests[$name] = $test;
    }

    public function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected === $actual) {
            return;
        }

        $detail = $message !== '' ? $message : 'Values are not identical.';
        throw new RuntimeException(sprintf(
            "%s\nExpected: %s\nActual:   %s",
            $detail,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }

    public function assertTrue(bool $condition, string $message = ''): void
    {
        if (!$condition) {
            throw new RuntimeException($message !== '' ? $message : 'Expected condition to be true.');
        }
    }

    public function run(): int
    {
        $failures = 0;

        foreach ($this->tests as $name => $test) {
            try {
                $test();
                fwrite(STDOUT, "PASS  {$name}\n");
            } catch (Throwable $error) {
                $failures++;
                fwrite(STDERR, "FAIL  {$name}\n{$error->getMessage()}\n");
            }
        }

        $count = count($this->tests);
        fwrite(STDOUT, sprintf("%d tests, %d failures\n", $count, $failures));

        return $failures === 0 ? 0 : 1;
    }
}
