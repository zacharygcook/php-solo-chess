<?php

declare(strict_types=1);

require __DIR__ . '/../../../src/bootstrap.php';

use SoloChess\Controllers\AuthController;
use SoloChess\Http\JsonRequest;
use SoloChess\Http\JsonResponse;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JsonResponse::send(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$payload = [];
try {
    $payload = JsonRequest::readObject();
} catch (\InvalidArgumentException $error) {
    JsonResponse::send(['success' => false, 'message' => $error->getMessage()], 400);
}

$result = AuthController::default()->login($payload);
JsonResponse::send($result['payload'], $result['status']);
