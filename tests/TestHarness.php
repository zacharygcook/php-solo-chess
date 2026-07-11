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
        $startedAt = hrtime(true);
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
        $durationMs = (hrtime(true) - $startedAt) / 1_000_000;
        $budgetMs = self::testTimeBudgetMs();
        $withinBudget = $durationMs <= $budgetMs;

        fwrite(STDOUT, sprintf(
            "%d tests, %d failures, %.2f ms (budget: %d ms)\n",
            $count,
            $failures,
            $durationMs,
            $budgetMs
        ));

        if (!$withinBudget) {
            fwrite(STDERR, sprintf(
                "FAIL  test suite exceeded its %d ms performance budget\n",
                $budgetMs
            ));
        }

        return $failures === 0 && $withinBudget ? 0 : 1;
    }

    private static function testTimeBudgetMs(): int
    {
        $configured = getenv('TEST_TIME_BUDGET_MS');
        if ($configured === false || $configured === '') {
            return 2_000;
        }

        if (!ctype_digit($configured) || (int) $configured < 1) {
            throw new RuntimeException('TEST_TIME_BUDGET_MS must be a positive integer.');
        }

        return (int) $configured;
    }
}
