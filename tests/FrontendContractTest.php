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
            'downloadPgnButton',
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
            'games/export.php',
            'games/abandon.php',
            'games/resign.php',
            'games/draw-offer.php',
            'games/draw-accept.php',
            'games/draw-claim.php',
        ] as $endpoint) {
            $tests->assertTrue(str_contains($api, $endpoint), "Missing API endpoint: {$endpoint}.");
        }

        $tests->assertTrue(str_contains($api, "credentials: 'same-origin'"), 'API requests must include same-origin cookies.');
        $tests->assertTrue(str_contains($api, 'httpStatus: response.status'), 'Non-2xx validation messages must remain visible.');
        $tests->assertTrue(str_contains($api, 'pgnExportUrl'), 'PGN downloads must use the same-origin API base.');
    });

    $tests->test('frontend auth validation failures preserve current account state', function () use ($tests, $root): void {
        $app = file_get_contents($root . '/frontend/assets/js/app.js');
        if ($app === false) {
            throw new RuntimeException('Unable to read frontend app module.');
        }

        foreach (['submitLogin', 'submitRegistration'] as $functionName) {
            $start = strpos($app, "async function {$functionName}()");
            if ($start === false) {
                throw new RuntimeException("Unable to locate {$functionName}.");
            }

            $functionBody = substr($app, $start, 900);
            $responsePosition = strpos($functionBody, 'const response = await api.');
            $successPosition = strpos($functionBody, 'if (response.success) {');
            $applyPosition = strpos($functionBody, 'applyUser(response);');

            $tests->assertTrue($responsePosition !== false, "{$functionName} must read the auth response.");
            $tests->assertTrue($successPosition !== false, "{$functionName} must branch on success.");
            $tests->assertTrue(
                $applyPosition !== false && $successPosition !== false && $applyPosition > $successPosition,
                "{$functionName} must not clear the visible account on failed auth validation.",
            );
        }
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

    $tests->test('frontend exposes accessible board movement cues without deciding legality', function () use ($tests, $root): void {
        $html = file_get_contents($root . '/frontend/index.html');
        $app = file_get_contents($root . '/frontend/assets/js/app.js');
        $board = file_get_contents($root . '/frontend/assets/js/board.js');
        $state = file_get_contents($root . '/frontend/assets/js/state.js');
        $css = file_get_contents($root . '/frontend/assets/css/styles.css');
        if ($html === false || $app === false || $board === false || $state === false || $css === false) {
            throw new RuntimeException('Unable to read frontend board movement files.');
        }

        foreach (['flipBoardButton', 'promotionPanel', 'capturedWhite', 'capturedBlack'] as $id) {
            $tests->assertTrue(str_contains($html, 'id="' . $id . '"'), "Missing board interaction control: {$id}.");
        }

        foreach (['queen', 'rook', 'bishop', 'knight'] as $promotion) {
            $tests->assertTrue(str_contains($html, 'data-promotion="' . $promotion . '"'), "Missing promotion choice: {$promotion}.");
        }

        foreach (['dragstart', 'dragover', 'drop', 'aria-label', 'legalMoves', 'checkedKing', 'lastMove'] as $needle) {
            $tests->assertTrue(str_contains($board, $needle), "Missing board cue wiring: {$needle}.");
        }

        $tests->assertTrue(str_contains($app, 'promotion: button.dataset.promotion'), 'Promotion choice must be submitted as move intent.');
        $tests->assertTrue(str_contains($app, 'No server legal moves are available'), 'Illegal selection feedback must stay non-disruptive.');
        $tests->assertTrue(str_contains($state, 'flipOrientation'), 'UI state must track board orientation locally.');

        foreach (['.legal-source', '.target', '.last-move', '.checked-king', '.capture-target', '.final-position'] as $selector) {
            $tests->assertTrue(str_contains($css, $selector), "Missing visual state selector: {$selector}.");
        }

        $tests->assertTrue(!str_contains($app, 'alert('), 'Illegal move feedback must not use page-level alerts.');
    });

    $tests->test('frontend presents server clocks actions and terminal feedback responsively', function () use ($tests, $root): void {
        $html = file_get_contents($root . '/frontend/index.html');
        $app = file_get_contents($root . '/frontend/assets/js/app.js');
        $css = file_get_contents($root . '/frontend/assets/css/styles.css');
        if ($html === false || $app === false || $css === false) {
            throw new RuntimeException('Unable to read frontend lifecycle files.');
        }

        foreach ([
            'whiteClock',
            'blackClock',
            'terminalSummary',
            'drawOfferNotice',
            'abandonButton',
            'resignButton',
            'offerDrawButton',
            'acceptDrawButton',
            'claimDrawButton',
            'soundToggleButton',
            'actionMessage',
        ] as $id) {
            $tests->assertTrue(str_contains($html, 'id="' . $id . '"'), "Missing lifecycle control: {$id}.");
        }

        foreach ([
            'projectRemaining',
            'turnStartedAtMilliseconds',
            'Date.now()',
            'api.resignGame(payload)',
            'api.offerDraw(payload)',
            'api.acceptDraw(payload)',
            'api.claimDraw(payload)',
            'api.abandonGame(payload)',
            'drawOffer?.offeredBy',
            'availableActions.includes(\'claimDraw\')',
        ] as $needle) {
            $tests->assertTrue(str_contains($app, $needle), "Missing lifecycle rendering behavior: {$needle}.");
        }

        foreach ([
            'checkmate',
            'stalemate',
            'timeout',
            'resignation',
            'agreedDraw',
            'deadPosition',
            'fiftyMoveRule',
            'threefoldRepetition',
        ] as $reason) {
            $tests->assertTrue(str_contains($app, $reason), "Missing terminal copy for: {$reason}.");
        }

        foreach (['.clock-row', '.clock-face.active', '.terminal-summary[data-reason=', '.button-row.lifecycle-row'] as $selector) {
            $tests->assertTrue(str_contains($css, $selector), "Missing lifecycle style selector: {$selector}.");
        }

        $tests->assertTrue(str_contains($css, '.clock-row,'), 'Mobile layout must stack the clock row.');
        $tests->assertTrue(!str_contains($app, 'alert('), 'Lifecycle feedback must not use page-level alerts.');
    });

    $tests->test('frontend sound feedback uses local optional assets and browser smoke coverage', function () use ($tests, $root): void {
        $html = file_get_contents($root . '/frontend/index.html');
        $app = file_get_contents($root . '/frontend/assets/js/app.js');
        $audio = file_get_contents($root . '/frontend/assets/js/audio.js');
        $css = file_get_contents($root . '/frontend/assets/css/styles.css');
        $smoke = file_get_contents($root . '/scripts/browser-smoke.sh');
        $check = file_get_contents($root . '/scripts/check.sh');
        if ($html === false || $app === false || $audio === false || $css === false || $smoke === false || $check === false) {
            throw new RuntimeException('Unable to read frontend sound or smoke files.');
        }

        foreach (['move.wav', 'capture.wav', 'check.wav', 'game-end.wav'] as $asset) {
            $path = $root . '/frontend/assets/audio/' . $asset;
            $tests->assertTrue(is_file($path), "Missing local sound asset: {$asset}.");
            $tests->assertTrue(filesize($path) !== false && filesize($path) < 12_000, "Sound asset is too large: {$asset}.");
        }

        foreach (['./audio.js', 'createAudioFeedback', 'playStateFeedback', 'feedbackKind', 'capturedPieceCount'] as $needle) {
            $tests->assertTrue(str_contains($app, $needle), "Missing sound feedback wiring: {$needle}.");
        }

        foreach (['soloChess.soundEnabled', 'localStorage', 'AudioCtor', 'result.catch', 'sound is optional feedback'] as $needle) {
            $tests->assertTrue(str_contains($audio, $needle), "Missing optional audio behavior: {$needle}.");
        }

        foreach (['.feedback-move', '.feedback-capture', '.feedback-check', '.feedback-game-end', 'prefers-reduced-motion'] as $selector) {
            $tests->assertTrue(str_contains($css, $selector), "Missing sound feedback style: {$selector}.");
        }

        foreach (['#quickGuestButton', '#registerForm', '#loginForm', '#replayControls', '#soundToggleButton'] as $selector) {
            $tests->assertTrue(str_contains($smoke, $selector), "Browser smoke must exercise selector: {$selector}.");
        }

        foreach (['timed game status', 'saved replay mode', 'mobile layout without horizontal overflow'] as $coverage) {
            $tests->assertTrue(str_contains($smoke, $coverage), "Browser smoke must cover: {$coverage}.");
        }

        $tests->assertTrue(str_contains($check, 'scripts/browser-smoke.sh'), 'Canonical check must run browser smoke coverage.');
        $tests->assertTrue(str_contains($html, 'aria-pressed="false">Sound off'), 'Sound control must remain obvious and accessible.');
    });
};
