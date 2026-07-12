<?php

declare(strict_types=1);

namespace SoloChess\Controllers;

use SoloChess\Services\PgnDownloadService;

final class PgnController
{
    public function __construct(private PgnDownloadService $downloads) {}

    public static function default(): self
    {
        return new self(PgnDownloadService::default());
    }

    public function export(?int $gameId): void
    {
        $result = $this->downloads->exportResult($gameId);
        http_response_code($result['status']);
        foreach ($result['headers'] as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $result['body'];
        exit;
    }
}
