<?php

declare(strict_types=1);

require __DIR__ . '/../../../src/bootstrap.php';

use SoloChess\Controllers\AuthController;
use SoloChess\Http\JsonResponse;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    JsonResponse::send(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$result = AuthController::default()->currentUser();
JsonResponse::send($result['payload'], $result['status']);
