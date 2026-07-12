<?php

declare(strict_types=1);

$mode = $argv[1] ?? '';
if (!in_array($mode, ['--check', '--write'], true)) {
    fwrite(STDERR, "Usage: php scripts/generate-api-docs.php --check|--write\n");
    exit(12);
}

$root = dirname(__DIR__);
$manifest = json_decode(
    (string) file_get_contents($root . '/config/api-endpoints.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
$paths = [];
$markdownRows = [];

foreach ($manifest['endpoints'] as $endpoint) {
    $relativePhpPath = ltrim($endpoint['path'], '/');
    $sourcePath = $root . '/' . $relativePhpPath;
    if (!is_file($sourcePath)) {
        throw new RuntimeException("API manifest references missing endpoint: {$relativePhpPath}");
    }

    $source = (string) file_get_contents($sourcePath);
    $expectedMethod = strtoupper($endpoint['method']);
    if (!str_contains($source, "!== '{$expectedMethod}'")) {
        throw new RuntimeException("Endpoint method drift for {$relativePhpPath}: expected {$expectedMethod}");
    }

    $responseSchema = $endpoint['response_schema'] ?? 'GameResponse';
    $operation = [
        'summary' => $endpoint['summary'],
        'operationId' => $endpoint['method'] . ucfirst(pathinfo($endpoint['path'], PATHINFO_FILENAME)),
        'responses' => [
            (string) $endpoint['success_status'] => [
                'description' => 'JSON result',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/' . $responseSchema],
                    ],
                ],
            ],
            '405' => [
                'description' => 'Method not allowed',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                    ],
                ],
            ],
        ],
    ];

    if (isset($endpoint['request_schema'])) {
        $operation['requestBody'] = [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => ['$ref' => '#/components/schemas/' . $endpoint['request_schema']],
                ],
            ],
        ];
    }

    $paths[$endpoint['path']] = [$endpoint['method'] => $operation];
    $markdownRows[] = sprintf(
        '| `%s` | `%s` | %s | `%d` |',
        $expectedMethod,
        $endpoint['path'],
        $endpoint['summary'],
        $endpoint['success_status'],
    );
}

ksort($paths);
$openApi = [
    'openapi' => '3.1.0',
    'info' => [
        'title' => 'PHP Solo Chess API',
        'version' => '0.1.0',
        'description' => 'Same-origin local session API. Send the PHP session cookie on every request.',
    ],
    'servers' => [['url' => 'http://127.0.0.1:8080']],
    'paths' => $paths,
    'components' => [
        'schemas' => [
            'MoveRequest' => [
                'type' => 'object',
                'required' => ['from', 'to'],
                'properties' => [
                    'from' => ['type' => 'string', 'pattern' => '^[a-h][1-8]$', 'example' => 'e2'],
                    'to' => ['type' => 'string', 'pattern' => '^[a-h][1-8]$', 'example' => 'e4'],
                    'promotion' => ['type' => ['string', 'null'], 'enum' => ['queen', 'rook', 'bishop', 'knight', null]],
                ],
                'additionalProperties' => false,
            ],
            'FenRequest' => [
                'type' => 'object',
                'required' => ['fen'],
                'properties' => ['fen' => ['type' => 'string', 'minLength' => 1]],
                'additionalProperties' => false,
            ],
            'AuthRegisterRequest' => [
                'type' => 'object',
                'required' => ['username', 'displayName', 'password'],
                'properties' => [
                    'username' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 32],
                    'displayName' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 80],
                    'password' => ['type' => 'string', 'minLength' => 8],
                ],
                'additionalProperties' => false,
            ],
            'AuthLoginRequest' => [
                'type' => 'object',
                'required' => ['username', 'password'],
                'properties' => [
                    'username' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 32],
                    'password' => ['type' => 'string', 'minLength' => 8],
                ],
                'additionalProperties' => false,
            ],
            'AuthUser' => [
                'type' => 'object',
                'required' => ['id', 'username', 'displayName', 'createdAt', 'updatedAt'],
                'properties' => [
                    'id' => ['type' => 'integer', 'minimum' => 1],
                    'username' => ['type' => 'string'],
                    'displayName' => ['type' => 'string'],
                    'createdAt' => ['type' => 'string'],
                    'updatedAt' => ['type' => 'string'],
                ],
                'additionalProperties' => false,
            ],
            'AuthState' => [
                'type' => 'object',
                'required' => ['user'],
                'properties' => [
                    'user' => [
                        'anyOf' => [
                            ['$ref' => '#/components/schemas/AuthUser'],
                            ['type' => 'null'],
                        ],
                    ],
                ],
                'additionalProperties' => false,
            ],
            'GameState' => [
                'type' => 'object',
                'required' => ['board', 'moveHistory', 'activeColor'],
                'properties' => [
                    'board' => ['type' => 'array', 'minItems' => 8, 'maxItems' => 8, 'items' => ['type' => 'array']],
                    'moveHistory' => ['type' => 'array', 'items' => ['type' => 'object']],
                    'activeColor' => ['type' => 'string', 'enum' => ['white', 'black']],
                    'kingInCheck' => ['type' => ['string', 'null'], 'enum' => ['white', 'black', null]],
                    'castlingRights' => [
                        'type' => 'object',
                        'properties' => [
                            'white' => ['$ref' => '#/components/schemas/CastlingRights'],
                            'black' => ['$ref' => '#/components/schemas/CastlingRights'],
                        ],
                        'additionalProperties' => false,
                    ],
                    'enPassantTarget' => ['type' => ['string', 'null'], 'pattern' => '^[a-h][1-8]$'],
                    'halfmoveClock' => ['type' => 'integer', 'minimum' => 0],
                    'fullmoveNumber' => ['type' => 'integer', 'minimum' => 1],
                    'positionHistory' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'legalMoves' => [
                        'type' => 'object',
                        'additionalProperties' => [
                            'type' => 'array',
                            'items' => ['type' => 'string', 'pattern' => '^[a-h][1-8]$'],
                        ],
                    ],
                    'fen' => ['type' => 'string'],
                    'gameStatus' => ['type' => 'string', 'enum' => ['active', 'finished']],
                    'result' => ['type' => ['string', 'null'], 'enum' => ['1-0', '0-1', '1/2-1/2', null]],
                    'terminationReason' => [
                        'type' => ['string', 'null'],
                        'enum' => ['checkmate', 'stalemate', 'deadPosition', null],
                    ],
                    'drawClaims' => [
                        'type' => 'array',
                        'items' => ['type' => 'string', 'enum' => ['fiftyMoveRule', 'threefoldRepetition']],
                    ],
                    'availableActions' => [
                        'type' => 'array',
                        'items' => ['type' => 'string', 'enum' => ['claimDraw']],
                    ],
                ],
                'additionalProperties' => true,
            ],
            'CastlingRights' => [
                'type' => 'object',
                'required' => ['kingSide', 'queenSide'],
                'properties' => [
                    'kingSide' => ['type' => 'boolean'],
                    'queenSide' => ['type' => 'boolean'],
                ],
                'additionalProperties' => false,
            ],
            'GameResponse' => [
                'type' => 'object',
                'required' => ['success', 'message', 'state'],
                'properties' => [
                    'success' => ['type' => 'boolean'],
                    'message' => ['type' => ['string', 'null']],
                    'state' => ['$ref' => '#/components/schemas/GameState'],
                ],
            ],
            'AuthResponse' => [
                'type' => 'object',
                'required' => ['success', 'message', 'state'],
                'properties' => [
                    'success' => ['type' => 'boolean'],
                    'message' => ['type' => 'string'],
                    'state' => ['$ref' => '#/components/schemas/AuthState'],
                ],
            ],
            'ErrorResponse' => [
                'type' => 'object',
                'required' => ['success', 'message'],
                'properties' => [
                    'success' => ['type' => 'boolean', 'const' => false],
                    'message' => ['type' => 'string'],
                ],
            ],
        ],
    ],
];

$json = json_encode($openApi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
$markdown = implode("\n", [
    '# API Reference',
    '',
    'This file is generated from `config/api-endpoints.json`. Do not edit it by hand; run',
    '`php scripts/generate-api-docs.php --write` and commit the manifest plus generated artifacts.',
    '',
    'All endpoints are same-origin JSON and use the PHP session cookie described in `docs/ARCHITECTURE.md`.',
    '',
    'Successful responses keep the stable envelope `success`, `message`, and `state`. Game state',
    'contains the board, move history, active color, legal moves, FEN, castling/en-passant clocks,',
    'terminal fields, and draw-claim actions. Accepted move-history records include coordinate',
    'notation, SAN, and post-move FEN. Promotion requests use `queen`, `rook`, `bishop`, or `knight`.',
    'Auth state contains only the current safe user identity or `null`, never password material.',
    '',
    '| Method | Path | Purpose | Success |',
    '|---|---|---|---:|',
    ...$markdownRows,
    '',
    'The machine-readable OpenAPI 3.1 contract is [`openapi.json`](openapi.json).',
    '',
]);

$artifacts = [
    $root . '/docs/openapi.json' => $json,
    $root . '/docs/API.md' => $markdown,
];
$drift = [];

foreach ($artifacts as $path => $content) {
    if ($mode === '--write') {
        file_put_contents($path, $content);
        fwrite(STDOUT, 'Generated ' . substr($path, strlen($root) + 1) . "\n");
    } elseif (!is_file($path) || file_get_contents($path) !== $content) {
        $drift[] = substr($path, strlen($root) + 1);
    }
}

if ($drift !== []) {
    fwrite(STDERR, "Generated API documentation is stale:\n - " . implode("\n - ", $drift) . "\n");
    exit(1);
}

if ($mode === '--check') {
    fwrite(STDOUT, "Generated API documentation is current.\n");
}
