<?php

declare(strict_types=1);

namespace SoloChess\Controllers;

use InvalidArgumentException;
use SoloChess\Services\AuthenticatedUser;
use SoloChess\Services\AuthSessionService;

final class AuthController
{
    public function __construct(private AuthSessionService $auth) {}

    public static function default(): self
    {
        return new self(AuthSessionService::default());
    }

    /**
     * @param array<string, mixed> $input
     * @return array{payload: array<string, mixed>, status: int}
     */
    public function register(array $input): array
    {
        $username = self::stringField($input, 'username');
        $displayName = self::stringField($input, 'displayName');
        $password = self::stringField($input, 'password');

        if ($username === null || $displayName === null || $password === null) {
            return self::result(false, 'Provide username, displayName, and password.', null, 422);
        }

        try {
            $user = $this->auth->register($username, $displayName, $password);
        } catch (InvalidArgumentException $error) {
            return self::result(false, $error->getMessage(), null, 422);
        }

        return self::result(true, 'Registration successful.', $user, 201);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{payload: array<string, mixed>, status: int}
     */
    public function login(array $input): array
    {
        $username = self::stringField($input, 'username');
        $password = self::stringField($input, 'password');

        if ($username === null || $password === null) {
            return self::result(false, 'Provide username and password.', null, 422);
        }

        $user = $this->auth->login($username, $password);
        if ($user === null) {
            return self::result(false, 'Invalid username or password.', null, 401);
        }

        return self::result(true, 'Login successful.', $user, 200);
    }

    /** @return array{payload: array<string, mixed>, status: int} */
    public function logout(): array
    {
        $this->auth->logout();

        return self::result(true, 'Logged out.', null, 200);
    }

    /** @return array{payload: array<string, mixed>, status: int} */
    public function currentUser(): array
    {
        $user = $this->auth->currentUser();

        return self::result(
            true,
            $user === null ? 'No user is logged in.' : 'Authenticated user loaded.',
            $user,
            200,
        );
    }

    /**
     * @return array{payload: array<string, mixed>, status: int}
     */
    private static function result(bool $success, string $message, ?AuthenticatedUser $user, int $status): array
    {
        return [
            'payload' => [
                'success' => $success,
                'message' => $message,
                'state' => [
                    'user' => $user?->toPublicArray(),
                ],
            ],
            'status' => $status,
        ];
    }

    /** @param array<string, mixed> $input */
    private static function stringField(array $input, string $key): ?string
    {
        $value = $input[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
