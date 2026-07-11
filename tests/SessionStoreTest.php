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
};
