<?php

declare(strict_types=1);

require __DIR__ . '/_shared.php';

use SoloChess\Controllers\PgnController;
use SoloChess\Http\JsonResponse;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    JsonResponse::send(['success' => false, 'message' => 'Method not allowed.'], 405);
}

PgnController::default()->export(soloChessGameIdFromQuery());
