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
    $responseContentType = $endpoint['response_content_type'] ?? 'application/json';
    $successContent = $responseContentType === 'application/json'
        ? [
            'application/json' => [
                'schema' => ['$ref' => '#/components/schemas/' . $responseSchema],
            ],
        ]
        : [
            $responseContentType => [
                'schema' => ['$ref' => '#/components/schemas/' . $responseSchema],
            ],
        ];
    $operation = [
        'summary' => $endpoint['summary'],
        'operationId' => $endpoint['method'] . ucfirst(pathinfo($endpoint['path'], PATHINFO_FILENAME)),
        'responses' => [
            (string) $endpoint['success_status'] => [
                'description' => $responseContentType === 'application/json' ? 'JSON result' : 'PGN download',
                'content' => $successContent,
            ],
            '405' => [
                'description' => 'Method not allowed',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                    ],
                ],
            ],
            '400' => [
                'description' => 'Malformed JSON body',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                    ],
                ],
            ],
            '401' => [
                'description' => 'Authentication required',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                    ],
                ],
            ],
            '404' => [
                'description' => 'Owner-scoped resource not found',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                    ],
                ],
            ],
            '422' => [
                'description' => 'Validation error',
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
        '| `%s` | `%s` | %s | `%d` | `%s` |',
        $expectedMethod,
        $endpoint['path'],
        $endpoint['summary'],
        $endpoint['success_status'],
        $responseContentType,
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
            'CreateGameRequest' => [
                'type' => 'object',
                'properties' => [
                    'whiteLabel' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 80],
                    'blackLabel' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 80],
                    'whiteParticipantType' => ['type' => 'string', 'enum' => ['local_human', 'engine']],
                    'blackParticipantType' => ['type' => 'string', 'enum' => ['local_human', 'engine']],
                    'timeControl' => ['$ref' => '#/components/schemas/TimeControlRequest'],
                ],
                'additionalProperties' => false,
            ],
            'TimeControlRequest' => [
                'oneOf' => [
                    [
                        'type' => 'object',
                        'required' => ['kind'],
                        'properties' => ['kind' => ['type' => 'string', 'const' => 'untimed']],
                        'additionalProperties' => false,
                    ],
                    [
                        'type' => 'object',
                        'required' => ['kind', 'preset'],
                        'properties' => [
                            'kind' => ['type' => 'string', 'const' => 'preset'],
                            'preset' => ['type' => 'string', 'enum' => ['1+0', '3+2', '5+0', '10+0', '15+10']],
                        ],
                        'additionalProperties' => false,
                    ],
                    [
                        'type' => 'object',
                        'required' => ['kind', 'baseMinutes', 'incrementSeconds'],
                        'properties' => [
                            'kind' => ['type' => 'string', 'const' => 'custom'],
                            'baseMinutes' => ['type' => 'integer', 'minimum' => 1],
                            'incrementSeconds' => ['type' => 'integer', 'minimum' => 0],
                        ],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'GameActionRequest' => [
                'type' => 'object',
                'required' => ['actorColor'],
                'properties' => [
                    'actorColor' => ['type' => 'string', 'enum' => ['white', 'black']],
                ],
                'additionalProperties' => false,
            ],
            'DrawClaimRequest' => [
                'type' => 'object',
                'required' => ['actorColor'],
                'properties' => [
                    'actorColor' => ['type' => 'string', 'enum' => ['white', 'black']],
                    'claim' => ['type' => 'string', 'enum' => ['fiftyMoveRule', 'threefoldRepetition']],
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
                    'participants' => ['$ref' => '#/components/schemas/Participants'],
                    'timeControl' => ['$ref' => '#/components/schemas/TimeControlState'],
                    'clockState' => ['$ref' => '#/components/schemas/ClockState'],
                    'legalMoves' => [
                        'type' => 'object',
                        'additionalProperties' => [
                            'type' => 'array',
                            'items' => ['type' => 'string', 'pattern' => '^[a-h][1-8]$'],
                        ],
                    ],
                    'fen' => ['type' => 'string'],
                    'gameStatus' => ['type' => 'string', 'enum' => ['active', 'finished']],
                    'result' => ['type' => ['string', 'null'], 'enum' => ['1-0', '0-1', '1/2-1/2', '*', null]],
                    'terminationReason' => [
                        'type' => ['string', 'null'],
                        'enum' => [
                            'checkmate',
                            'stalemate',
                            'deadPosition',
                            'fiftyMoveRule',
                            'threefoldRepetition',
                            'resignation',
                            'agreedDraw',
                            'timeout',
                            'abandoned',
                            null,
                        ],
                    ],
                    'completedAt' => ['type' => ['string', 'null']],
                    'drawOffer' => [
                        'anyOf' => [
                            ['$ref' => '#/components/schemas/DrawOffer'],
                            ['type' => 'null'],
                        ],
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
            'Participants' => [
                'type' => 'object',
                'required' => ['white', 'black'],
                'properties' => [
                    'white' => ['$ref' => '#/components/schemas/Participant'],
                    'black' => ['$ref' => '#/components/schemas/Participant'],
                ],
                'additionalProperties' => false,
            ],
            'Participant' => [
                'type' => 'object',
                'required' => ['label', 'type'],
                'properties' => [
                    'label' => ['type' => 'string'],
                    'type' => ['type' => 'string', 'enum' => ['local_human', 'engine']],
                ],
                'additionalProperties' => false,
            ],
            'TimeControlState' => [
                'type' => 'object',
                'required' => ['kind', 'label', 'baseMilliseconds', 'incrementMilliseconds'],
                'properties' => [
                    'kind' => ['type' => 'string', 'enum' => ['untimed', 'preset', 'custom']],
                    'label' => ['type' => 'string'],
                    'baseMilliseconds' => ['type' => ['integer', 'null'], 'minimum' => 0],
                    'incrementMilliseconds' => ['type' => ['integer', 'null'], 'minimum' => 0],
                ],
                'additionalProperties' => true,
            ],
            'ClockState' => [
                'type' => 'object',
                'required' => ['mode', 'whiteRemainingMilliseconds', 'blackRemainingMilliseconds', 'turnStartedAtMilliseconds'],
                'properties' => [
                    'mode' => ['type' => 'string', 'enum' => ['untimed', 'timed']],
                    'whiteRemainingMilliseconds' => ['type' => ['integer', 'null'], 'minimum' => 0],
                    'blackRemainingMilliseconds' => ['type' => ['integer', 'null'], 'minimum' => 0],
                    'turnStartedAtMilliseconds' => ['type' => ['integer', 'null'], 'minimum' => 0],
                    'activeColor' => ['type' => ['string', 'null'], 'enum' => ['white', 'black', null]],
                ],
                'additionalProperties' => true,
            ],
            'DrawOffer' => [
                'type' => 'object',
                'required' => ['offeredBy'],
                'properties' => [
                    'offeredBy' => ['type' => 'string', 'enum' => ['white', 'black']],
                ],
                'additionalProperties' => false,
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
            'GameSummary' => [
                'type' => 'object',
                'required' => ['id', 'date', 'status', 'whiteLabel', 'blackLabel', 'timeControl'],
                'properties' => [
                    'id' => ['type' => 'integer', 'minimum' => 1],
                    'date' => ['type' => 'string'],
                    'createdAt' => ['type' => 'string'],
                    'updatedAt' => ['type' => 'string'],
                    'completedAt' => ['type' => ['string', 'null']],
                    'status' => ['type' => 'string', 'enum' => ['active', 'finished']],
                    'result' => ['type' => ['string', 'null'], 'enum' => ['1-0', '0-1', '1/2-1/2', '*', null]],
                    'completionReason' => ['type' => ['string', 'null']],
                    'terminationReason' => ['type' => ['string', 'null']],
                    'whiteLabel' => ['type' => 'string'],
                    'blackLabel' => ['type' => 'string'],
                    'whitePlayerType' => ['type' => 'string'],
                    'blackPlayerType' => ['type' => 'string'],
                    'timeControl' => [
                        'anyOf' => [
                            ['$ref' => '#/components/schemas/TimeControlState'],
                            ['type' => 'null'],
                        ],
                    ],
                ],
                'additionalProperties' => false,
            ],
            'ReplayPosition' => [
                'type' => 'object',
                'required' => ['plyNumber', 'coordinate', 'san', 'fen', 'whiteClockMilliseconds', 'blackClockMilliseconds'],
                'properties' => [
                    'plyNumber' => ['type' => 'integer', 'minimum' => 0],
                    'coordinate' => ['type' => 'string'],
                    'san' => ['type' => ['string', 'null']],
                    'fen' => ['type' => 'string'],
                    'whiteClockMilliseconds' => ['type' => ['integer', 'null'], 'minimum' => 0],
                    'blackClockMilliseconds' => ['type' => ['integer', 'null'], 'minimum' => 0],
                ],
                'additionalProperties' => false,
            ],
            'ReplayMove' => [
                'type' => 'object',
                'required' => ['plyNumber', 'from', 'to', 'coordinate', 'san', 'fen', 'createdAt'],
                'properties' => [
                    'plyNumber' => ['type' => 'integer', 'minimum' => 1],
                    'from' => ['type' => 'string', 'pattern' => '^[a-h][1-8]$'],
                    'to' => ['type' => 'string', 'pattern' => '^[a-h][1-8]$'],
                    'promotion' => ['type' => ['string', 'null'], 'enum' => ['queen', 'rook', 'bishop', 'knight', null]],
                    'coordinate' => ['type' => 'string'],
                    'san' => ['type' => 'string'],
                    'fen' => ['type' => 'string'],
                    'whiteClockMilliseconds' => ['type' => ['integer', 'null'], 'minimum' => 0],
                    'blackClockMilliseconds' => ['type' => ['integer', 'null'], 'minimum' => 0],
                    'createdAt' => ['type' => 'string'],
                ],
                'additionalProperties' => false,
            ],
            'ReplayData' => [
                'type' => 'object',
                'required' => ['positions', 'moves'],
                'properties' => [
                    'positions' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/ReplayPosition']],
                    'moves' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/ReplayMove']],
                ],
                'additionalProperties' => false,
            ],
            'GameHistoryResponse' => [
                'type' => 'object',
                'required' => ['success', 'message', 'state'],
                'properties' => [
                    'success' => ['type' => 'boolean'],
                    'message' => ['type' => 'string'],
                    'state' => [
                        'type' => 'object',
                        'required' => ['games'],
                        'properties' => [
                            'games' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/GameSummary']],
                        ],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'SavedGameResponse' => [
                'type' => 'object',
                'required' => ['success', 'message', 'state'],
                'properties' => [
                    'success' => ['type' => 'boolean'],
                    'message' => ['type' => 'string'],
                    'state' => [
                        'type' => 'object',
                        'required' => ['game', 'gameState', 'replay'],
                        'properties' => [
                            'game' => ['$ref' => '#/components/schemas/GameSummary'],
                            'gameState' => ['$ref' => '#/components/schemas/GameState'],
                            'replay' => ['$ref' => '#/components/schemas/ReplayData'],
                        ],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'ReplayResponse' => [
                'type' => 'object',
                'required' => ['success', 'message', 'state'],
                'properties' => [
                    'success' => ['type' => 'boolean'],
                    'message' => ['type' => 'string'],
                    'state' => [
                        'type' => 'object',
                        'required' => ['game', 'replay'],
                        'properties' => [
                            'game' => ['$ref' => '#/components/schemas/GameSummary'],
                            'replay' => ['$ref' => '#/components/schemas/ReplayData'],
                        ],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'PgnDownload' => [
                'type' => 'string',
                'format' => 'binary',
                'description' => 'PGN text downloaded as an attachment.',
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
    'All endpoints are same-origin and use the PHP session cookie described in `docs/ARCHITECTURE.md`.',
    '',
    'Successful responses keep the stable envelope `success`, `message`, and `state`. Game state',
    'contains the board, participants, time control, server-owned clock state, move history, active',
    'color, legal moves, FEN, castling/en-passant fields, terminal fields, draw offers, and',
    'draw-claim actions. Accepted move-history records include coordinate notation, SAN, post-move',
    'FEN, and clock snapshots. Promotion requests use `queen`, `rook`, `bishop`, or `knight`.',
    'Auth state contains only the current safe user identity or `null`, never password material.',
    'History, saved-game open, replay, and saved-game PGN export endpoints require an authenticated',
    'owner session and never mutate the active game while returning saved replay positions. PGN export',
    'returns `application/x-chess-pgn; charset=UTF-8` on success and JSON error envelopes on failure.',
    '',
    '| Method | Path | Purpose | Success | Content Type |',
    '|---|---|---|---:|---|',
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
