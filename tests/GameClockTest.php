<?php

declare(strict_types=1);

use SoloChess\Services\GameService;
use SoloChess\Services\SessionStore;

return static function (TestHarness $tests): void {
    $tests->test('accepted moves debit active clock and apply increment exactly once for both colors', function () use ($tests): void {
        $_SESSION = [];
        $time = new DeterministicClock(1_000_000);
        $game = new GameService(new SessionStore(), currentTimeMilliseconds: $time);

        $game->createGame(['timeControl' => ['kind' => 'preset', 'preset' => '3+2']]);

        $time->advance(5_000);
        $afterWhite = $game->submitMove(['from' => 'e2', 'to' => 'e4']);

        $tests->assertSame(177_000, $afterWhite['clockState']['whiteRemainingMilliseconds']);
        $tests->assertSame(180_000, $afterWhite['clockState']['blackRemainingMilliseconds']);
        $tests->assertSame('black', $afterWhite['clockState']['activeColor']);
        $tests->assertSame(1_005_000, $afterWhite['clockState']['turnStartedAtMilliseconds']);
        $tests->assertSame(177_000, $afterWhite['moveHistory'][0]['whiteClockMilliseconds']);
        $tests->assertSame(180_000, $afterWhite['moveHistory'][0]['blackClockMilliseconds']);

        $time->advance(8_000);
        $afterBlack = $game->submitMove(['from' => 'g8', 'to' => 'f6']);

        $tests->assertSame(177_000, $afterBlack['clockState']['whiteRemainingMilliseconds']);
        $tests->assertSame(174_000, $afterBlack['clockState']['blackRemainingMilliseconds']);
        $tests->assertSame('white', $afterBlack['clockState']['activeColor']);
        $tests->assertSame(1_013_000, $afterBlack['clockState']['turnStartedAtMilliseconds']);
        $tests->assertSame(177_000, $afterBlack['moveHistory'][1]['whiteClockMilliseconds']);
        $tests->assertSame(174_000, $afterBlack['moveHistory'][1]['blackClockMilliseconds']);
    });

    $tests->test('rejected moves do not debit or increment the canonical clock', function () use ($tests): void {
        $_SESSION = [];
        $time = new DeterministicClock(2_000_000);
        $store = new SessionStore();
        $game = new GameService($store, currentTimeMilliseconds: $time);

        $game->createGame(['timeControl' => ['kind' => 'preset', 'preset' => '1+0']]);
        $time->advance(10_000);
        $rejected = $game->submitMove(['from' => 'z9', 'to' => 'e4']);
        $storedAfterReject = $store->getState();

        $tests->assertSame(false, $rejected['isValidMove']);
        $tests->assertSame(50_000, $rejected['clockState']['whiteRemainingMilliseconds']);
        $tests->assertSame(60_000, $storedAfterReject['clockState']['whiteRemainingMilliseconds']);
        $tests->assertSame(2_000_000, $storedAfterReject['clockState']['turnStartedAtMilliseconds']);
        $tests->assertSame([], $storedAfterReject['moveHistory']);

        $time->advance(5_000);
        $accepted = $game->submitMove(['from' => 'e2', 'to' => 'e4']);

        $tests->assertSame(45_000, $accepted['clockState']['whiteRemainingMilliseconds']);
        $tests->assertSame(60_000, $accepted['clockState']['blackRemainingMilliseconds']);
        $tests->assertSame(1, count($accepted['moveHistory']));
    });

    $tests->test('refresh projects the active clock without pausing resetting or double debiting it', function () use ($tests): void {
        $_SESSION = [];
        $time = new DeterministicClock(3_000_000);
        $store = new SessionStore();
        $game = new GameService($store, currentTimeMilliseconds: $time);

        $game->createGame(['timeControl' => ['kind' => 'preset', 'preset' => '5+0']]);

        $time->advance(10_000);
        $firstView = $game->getSessionState();
        $storedAfterFirstView = $store->getState();

        $tests->assertSame(290_000, $firstView['clockState']['whiteRemainingMilliseconds']);
        $tests->assertSame(3_010_000, $firstView['clockState']['turnStartedAtMilliseconds']);
        $tests->assertSame(300_000, $storedAfterFirstView['clockState']['whiteRemainingMilliseconds']);
        $tests->assertSame(3_000_000, $storedAfterFirstView['clockState']['turnStartedAtMilliseconds']);

        $time->advance(10_000);
        $secondView = $game->getSessionState();

        $tests->assertSame(280_000, $secondView['clockState']['whiteRemainingMilliseconds']);
        $tests->assertSame(300_000, $store->getState()['clockState']['whiteRemainingMilliseconds']);

        $time->advance(10_000);
        $accepted = $game->submitMove(['from' => 'e2', 'to' => 'e4']);

        $tests->assertSame(270_000, $accepted['clockState']['whiteRemainingMilliseconds']);
        $tests->assertSame('black', $accepted['clockState']['activeColor']);
    });

    $tests->test('clock views clamp elapsed time at zero until timeout termination is implemented', function () use ($tests): void {
        $_SESSION = [];
        $time = new DeterministicClock(4_000_000);
        $game = new GameService(new SessionStore(), currentTimeMilliseconds: $time);

        $game->createGame(['timeControl' => ['kind' => 'custom', 'baseMinutes' => 1, 'incrementSeconds' => 3]]);
        $time->advance(70_000);

        $view = $game->getSessionState();
        $accepted = $game->submitMove(['from' => 'e2', 'to' => 'e4']);

        $tests->assertSame(0, $view['clockState']['whiteRemainingMilliseconds']);
        $tests->assertSame(3_000, $accepted['clockState']['whiteRemainingMilliseconds']);
        $tests->assertSame('active', $accepted['gameStatus']);
        $tests->assertSame(null, $accepted['terminationReason']);
    });
};

final class DeterministicClock
{
    public function __construct(private int $milliseconds) {}

    public function __invoke(): int
    {
        return $this->milliseconds;
    }

    public function advance(int $milliseconds): void
    {
        $this->milliseconds += $milliseconds;
    }
}
