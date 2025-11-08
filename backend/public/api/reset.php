<?php

declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

use SoloChess\Controllers\GameController;
use SoloChess\Http\JsonResponse;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JsonResponse::send(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$controller = new GameController();
$controller->reset();
