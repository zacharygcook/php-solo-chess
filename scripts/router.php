<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$requestPath = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

if ($requestPath === '/') {
    header('Location: /frontend/');
    http_response_code(302);
    exit;
}

if ($requestPath === '/frontend' || str_starts_with($requestPath, '/frontend/')) {
    $resolved = realpath($root . $requestPath);
    $frontendRoot = realpath($root . '/frontend');
    if ($resolved !== false && $frontendRoot !== false && str_starts_with($resolved, $frontendRoot)) {
        return false;
    }
}

if (preg_match('#^/backend/public/api/(?:[a-z][a-z0-9-]*/)?[a-z][a-z0-9-]*\.php$#', $requestPath)) {
    $resolved = realpath($root . $requestPath);
    $apiRoot = realpath($root . '/backend/public/api');
    if ($resolved !== false && $apiRoot !== false && str_starts_with($resolved, $apiRoot)) {
        return false;
    }
}

http_response_code(404);
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
echo json_encode(['success' => false, 'message' => 'Not found.']);
