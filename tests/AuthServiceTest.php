<?php

declare(strict_types=1);

use SoloChess\Persistence\DatabaseSchema;
use SoloChess\Persistence\SqliteConnectionFactory;
use SoloChess\Repositories\UserRepository;
use SoloChess\Services\AuthService;

return static function (TestHarness $tests): void {
    $tests->test('registration stores hashed passwords and returns safe identity data', function () use ($tests): void {
        [$auth, $users] = authServiceTestSubject();

        $identity = $auth->register(' PlayerOne ', ' Player One ', 'correct horse');
        $stored = $users->findByNormalizedUsername('playerone');

        $tests->assertSame('PlayerOne', $identity->username);
        $tests->assertSame('playerone', $identity->normalizedUsername);
        $tests->assertSame('Player One', $identity->displayName);
        $tests->assertSame(false, array_key_exists('passwordHash', get_object_vars($identity)));
        $tests->assertTrue($stored !== null, 'Registered user should be stored.');
        $tests->assertTrue($stored->passwordHash !== 'correct horse', 'Password should not be stored as plaintext.');
        $tests->assertTrue(password_verify('correct horse', $stored->passwordHash), 'Stored password hash should verify.');
    });

    $tests->test('registration rejects duplicate normalized usernames', function () use ($tests): void {
        [$auth, $users] = authServiceTestSubject();
        $auth->register('PlayerOne', 'Player One', 'correct horse');

        $duplicateRejected = authServiceRejected(
            static fn(): mixed => $auth->register('playerone', 'Second Player', 'second password'),
        );

        $tests->assertTrue($duplicateRejected, 'Duplicate normalized username should be rejected.');
        $tests->assertSame(1, count($users->listAll()));
    });

    $tests->test('registration rejects invalid username display name and password', function () use ($tests): void {
        [$auth, $users] = authServiceTestSubject();

        $tests->assertTrue(authServiceRejected(
            static fn(): mixed => $auth->register('ab', 'Player One', 'correct horse'),
        ));
        $tests->assertTrue(authServiceRejected(
            static fn(): mixed => $auth->register('player one', 'Player One', 'correct horse'),
        ));
        $tests->assertTrue(authServiceRejected(
            static fn(): mixed => $auth->register('playerone', '', 'correct horse'),
        ));
        $tests->assertTrue(authServiceRejected(
            static fn(): mixed => $auth->register('playerone', 'Player One', 'short'),
        ));
        $tests->assertSame([], $users->listAll());
    });

    $tests->test('authentication accepts normalized usernames without exposing password hashes', function () use ($tests): void {
        [$auth] = authServiceTestSubject();
        $auth->register('PlayerOne', 'Player One', 'correct horse');

        $identity = $auth->authenticate(' playerone ', 'correct horse');

        $tests->assertTrue($identity !== null, 'Valid credentials should authenticate.');
        $tests->assertSame('PlayerOne', $identity->username);
        $tests->assertSame('Player One', $identity->displayName);
        $tests->assertSame(false, array_key_exists('passwordHash', get_object_vars($identity)));
    });

    $tests->test('authentication rejects missing users invalid usernames and wrong passwords', function () use ($tests): void {
        [$auth] = authServiceTestSubject();
        $auth->register('PlayerOne', 'Player One', 'correct horse');

        $tests->assertSame(null, $auth->authenticate('missing', 'correct horse'));
        $tests->assertSame(null, $auth->authenticate('bad username', 'correct horse'));
        $tests->assertSame(null, $auth->authenticate('PlayerOne', 'wrong password'));
    });
};

/** @return array{0: AuthService, 1: UserRepository} */
function authServiceTestSubject(): array
{
    $pdo = SqliteConnectionFactory::inMemory();
    DatabaseSchema::initialize($pdo);
    $users = new UserRepository($pdo);

    return [new AuthService($users), $users];
}

/** @param Closure(): mixed $action */
function authServiceRejected(Closure $action): bool
{
    try {
        $action();
    } catch (InvalidArgumentException) {
        return true;
    }

    return false;
}
