<?php

declare(strict_types=1);

use SoloChess\Services\SessionStore;

return static function (TestHarness $tests): void {
    $tests->test('session store can clear saved game state', function () use ($tests): void {
        $_SESSION = [];
        $store = new SessionStore();

        $store->saveState(['activeColor' => 'black']);
        $tests->assertSame(['activeColor' => 'black'], $store->getState());

        $store->clear();
        $tests->assertSame([], $store->getState());
    });

    $tests->test('session store clears authentication without clearing game state', function () use ($tests): void {
        $_SESSION = [];
        $store = new SessionStore();

        $store->saveState(['activeColor' => 'black']);
        $store->saveAuthenticatedUserId(12);

        $store->clearAuthenticatedUser();

        $tests->assertSame(null, $store->getAuthenticatedUserId());
        $tests->assertSame(['activeColor' => 'black'], $store->getState());
    });
};
