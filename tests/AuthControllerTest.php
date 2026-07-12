<?php

declare(strict_types=1);

use SoloChess\Controllers\AuthController;
use SoloChess\Persistence\DatabaseSchema;
use SoloChess\Persistence\SqliteConnectionFactory;
use SoloChess\Repositories\UserRepository;
use SoloChess\Services\AuthService;
use SoloChess\Services\AuthSessionService;
use SoloChess\Services\SessionStore;

return static function (TestHarness $tests): void {
    $tests->test('registration endpoint payload logs in safe identity and rotates session id', function () use ($tests): void {
        $rotations = 0;
        [$controller, $users, $sessions] = authControllerTestSubject($rotations);

        $result = $controller->register([
            'username' => ' PlayerOne ',
            'displayName' => ' Player One ',
            'password' => 'correct horse',
        ]);

        $stored = $users->findByNormalizedUsername('playerone');
        $user = $result['payload']['state']['user'];

        $tests->assertSame(201, $result['status']);
        $tests->assertSame(true, $result['payload']['success']);
        $tests->assertTrue(is_array($user), 'Registered user payload should be present.');
        $tests->assertSame('PlayerOne', $user['username']);
        $tests->assertSame('Player One', $user['displayName']);
        $tests->assertSame(false, array_key_exists('passwordHash', $user));
        $tests->assertTrue($stored !== null, 'User should be stored.');
        $tests->assertSame($stored->id, $sessions->getAuthenticatedUserId());
        $tests->assertSame(1, $rotations);
    });

    $tests->test('login rejects bad credentials without rotating or leaking details', function () use ($tests): void {
        $rotations = 0;
        [$controller] = authControllerTestSubject($rotations);
        $controller->register([
            'username' => 'PlayerOne',
            'displayName' => 'Player One',
            'password' => 'correct horse',
        ]);
        $controller->logout();
        $rotations = 0;

        $result = $controller->login(['username' => 'PlayerOne', 'password' => 'wrong password']);

        $tests->assertSame(401, $result['status']);
        $tests->assertSame(false, $result['payload']['success']);
        $tests->assertSame('Invalid username or password.', $result['payload']['message']);
        $tests->assertSame(null, $result['payload']['state']['user']);
        $tests->assertSame(0, $rotations);
    });

    $tests->test('login and current user return the active safe identity', function () use ($tests): void {
        $rotations = 0;
        [$controller] = authControllerTestSubject($rotations);
        $controller->register([
            'username' => 'PlayerOne',
            'displayName' => 'Player One',
            'password' => 'correct horse',
        ]);
        $controller->logout();

        $login = $controller->login(['username' => ' playerone ', 'password' => 'correct horse']);
        $current = $controller->currentUser();

        $tests->assertSame(200, $login['status']);
        $tests->assertSame(200, $current['status']);
        $tests->assertSame('PlayerOne', $current['payload']['state']['user']['username']);
        $tests->assertSame(2, $rotations);
    });

    $tests->test('logout clears only authentication context', function () use ($tests): void {
        $rotations = 0;
        [$controller, , $sessions] = authControllerTestSubject($rotations);
        $sessions->saveState(['activeColor' => 'black']);
        $controller->register([
            'username' => 'PlayerOne',
            'displayName' => 'Player One',
            'password' => 'correct horse',
        ]);

        $result = $controller->logout();

        $tests->assertSame(200, $result['status']);
        $tests->assertSame(null, $sessions->getAuthenticatedUserId());
        $tests->assertSame(['activeColor' => 'black'], $sessions->getState());
    });

    $tests->test('current user clears stale authentication ids', function () use ($tests): void {
        $rotations = 0;
        [$controller, , $sessions] = authControllerTestSubject($rotations);
        $sessions->saveAuthenticatedUserId(999);

        $result = $controller->currentUser();

        $tests->assertSame(200, $result['status']);
        $tests->assertSame(null, $result['payload']['state']['user']);
        $tests->assertSame(null, $sessions->getAuthenticatedUserId());
    });

    $tests->test('registration and login reject malformed field shapes', function () use ($tests): void {
        $rotations = 0;
        [$controller, $users] = authControllerTestSubject($rotations);

        $register = $controller->register([
            'username' => 'PlayerOne',
            'displayName' => 'Player One',
            'password' => 123,
        ]);
        $login = $controller->login(['username' => 'PlayerOne']);

        $tests->assertSame(422, $register['status']);
        $tests->assertSame(422, $login['status']);
        $tests->assertSame([], $users->listAll());
        $tests->assertSame(0, $rotations);
    });
};

/** @return array{0: AuthController, 1: UserRepository, 2: SessionStore} */
function authControllerTestSubject(int &$rotations): array
{
    $_SESSION = [];
    $pdo = SqliteConnectionFactory::inMemory();
    DatabaseSchema::initialize($pdo);
    $users = new UserRepository($pdo);
    $sessions = new SessionStore();
    $auth = new AuthSessionService(
        new AuthService($users, ['cost' => 4]),
        $users,
        $sessions,
        static function () use (&$rotations): void {
            $rotations++;
        },
    );

    return [new AuthController($auth), $users, $sessions];
}
