<?php

declare(strict_types=1);

namespace SoloChess\Observability;

final class RequestLogger
{
    public static function start(): void
    {
        $requestId = self::requestId((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
        $startedAt = hrtime(true);
        header('X-Request-ID: ' . $requestId);

        register_shutdown_function(static function () use ($requestId, $startedAt): void {
            $status = http_response_code();
            $record = self::completionRecord(
                $requestId,
                $startedAt,
                is_int($status) ? $status : 200,
                (string) ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'),
                (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH),
                error_get_last(),
            );
            error_log(self::encode($record));
        });
    }

    public static function requestId(string $candidate): string
    {
        if (preg_match('/^[A-Za-z0-9-]{8,64}$/', $candidate) === 1) {
            return $candidate;
        }

        return bin2hex(random_bytes(8));
    }

    /** @param array{type: int, message: string, file: string, line: int}|null $error
     *  @return array<string, bool|float|int|string>
     */
    public static function completionRecord(
        string $requestId,
        int $startedAt,
        int $status,
        string $method,
        string $path,
        ?array $error = null,
    ): array {
        $record = [
            'timestamp' => gmdate(DATE_ATOM),
            'level' => $status >= 500 || $error !== null ? 'error' : ($status >= 400 ? 'warning' : 'info'),
            'event' => 'http.request.completed',
            'request_id' => $requestId,
            'method' => $method,
            'path' => $path,
            'status' => $status,
            'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 3),
        ];
        if ($error !== null) {
            $record['error_type'] = $error['type'];
            $record['error_file'] = basename($error['file']);
            $record['error_line'] = $error['line'];
        }

        return $record;
    }

    /** @param array<string, bool|float|int|string> $record */
    public static function encode(array $record): string
    {
        return (string) json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
