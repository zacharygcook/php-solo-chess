<?php

declare(strict_types=1);

namespace SoloChess\Services;

use InvalidArgumentException;
use PDOException;
use SoloChess\Repositories\UserRepository;

final class AuthService
{
    private const USERNAME_PATTERN = '/\A[a-z0-9_-]{3,32}\z/';
    private const MIN_PASSWORD_LENGTH = 8;
    private const MAX_DISPLAY_NAME_LENGTH = 80;

    public function __construct(private UserRepository $users) {}

    public function register(string $username, string $displayName, string $password): AuthenticatedUser
    {
        $username = trim($username);
        $normalizedUsername = self::normalizeUsername($username);
        $displayName = trim($displayName);

        self::validateUsername($normalizedUsername);
        self::validateDisplayName($displayName);
        self::validatePassword($password);

        if ($this->users->findByNormalizedUsername($normalizedUsername) !== null) {
            throw new InvalidArgumentException('Username is already taken.');
        }

        try {
            $user = $this->users->create(
                $username,
                $normalizedUsername,
                $displayName,
                password_hash($password, PASSWORD_DEFAULT),
            );
        } catch (PDOException $error) {
            if ((string) $error->getCode() === '23000') {
                throw new InvalidArgumentException('Username is already taken.', previous: $error);
            }

            throw $error;
        }

        return AuthenticatedUser::fromUserRecord($user);
    }

    public function authenticate(string $username, string $password): ?AuthenticatedUser
    {
        $normalizedUsername = self::normalizeUsername($username);
        if (!self::isValidUsername($normalizedUsername)) {
            return null;
        }

        $user = $this->users->findByNormalizedUsername($normalizedUsername);
        if ($user === null || !password_verify($password, $user->passwordHash)) {
            return null;
        }

        return AuthenticatedUser::fromUserRecord($user);
    }

    public static function normalizeUsername(string $username): string
    {
        return strtolower(trim($username));
    }

    private static function validateUsername(string $normalizedUsername): void
    {
        if (!self::isValidUsername($normalizedUsername)) {
            throw new InvalidArgumentException(
                'Username must be 3 to 32 characters and contain only letters, numbers, hyphens, or underscores.',
            );
        }
    }

    private static function validateDisplayName(string $displayName): void
    {
        if ($displayName === '' || strlen($displayName) > self::MAX_DISPLAY_NAME_LENGTH) {
            throw new InvalidArgumentException('Display name must be 1 to 80 characters.');
        }
    }

    private static function validatePassword(string $password): void
    {
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new InvalidArgumentException('Password must be at least 8 characters.');
        }
    }

    private static function isValidUsername(string $normalizedUsername): bool
    {
        return preg_match(self::USERNAME_PATTERN, $normalizedUsername) === 1;
    }
}
