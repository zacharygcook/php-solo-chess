<?php

declare(strict_types=1);

return static function (TestHarness $tests): void {
    $root = dirname(__DIR__);

    $tests->test('frontend exposes account setup game history and replay controls', function () use ($tests, $root): void {
        $html = file_get_contents($root . '/frontend/index.html');
        if ($html === false) {
            throw new RuntimeException('Unable to read frontend/index.html.');
        }

        foreach ([
            'quickGuestButton',
            'newGameForm',
            'loginForm',
            'registerForm',
            'logoutButton',
            'refreshHistoryButton',
            'savedGames',
            'replayControls',
            'returnLiveButton',
            'reviewBanner',
        ] as $id) {
            $tests->assertTrue(str_contains($html, 'id="' . $id . '"'), "Missing frontend control: {$id}.");
        }

        $tests->assertTrue(!str_contains(strtolower($html), 'jquery'), 'The frontend must not depend on jQuery.');
        $tests->assertTrue(!str_contains($html, 'https://'), 'Runtime frontend assets must stay local.');
    });

    $tests->test('frontend api client uses same origin account lifecycle and saved game endpoints', function () use ($tests, $root): void {
        $api = file_get_contents($root . '/frontend/assets/js/api.js');
        if ($api === false) {
            throw new RuntimeException('Unable to read frontend API client.');
        }

        foreach ([
            'auth/user.php',
            'auth/register.php',
            'auth/login.php',
            'auth/logout.php',
            'games/new.php',
            'games/history.php',
            'games/open.php?id=',
            'games/replay.php?id=',
        ] as $endpoint) {
            $tests->assertTrue(str_contains($api, $endpoint), "Missing API endpoint: {$endpoint}.");
        }

        $tests->assertTrue(str_contains($api, "credentials: 'same-origin'"), 'API requests must include same-origin cookies.');
        $tests->assertTrue(str_contains($api, 'httpStatus: response.status'), 'Non-2xx validation messages must remain visible.');
    });

    $tests->test('frontend keeps replay read only and renders server supplied fen positions', function () use ($tests, $root): void {
        $app = file_get_contents($root . '/frontend/assets/js/app.js');
        $board = file_get_contents($root . '/frontend/assets/js/board.js');
        $state = file_get_contents($root . '/frontend/assets/js/state.js');
        if ($app === false || $board === false || $state === false) {
            throw new RuntimeException('Unable to read frontend modules.');
        }

        $tests->assertTrue(str_contains($app, 'Review mode is read-only'), 'Review mode must not submit moves.');
        $tests->assertTrue(str_contains($app, 'boardFromFen(position?.fen)'), 'Replay board must render server supplied FEN.');
        $tests->assertTrue(str_contains($state, 'startReplay'), 'UI state must track immutable replay separately.');
        $tests->assertTrue(str_contains($board, 'export function boardFromFen'), 'Board renderer must support replay FEN positions.');
        $tests->assertTrue(!str_contains($app, 'alert('), 'Validation feedback must not use page-level alerts.');
    });
};
