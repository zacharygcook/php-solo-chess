<?php

declare(strict_types=1);

namespace SoloChess\Services;

use Closure;
use RuntimeException;
use SoloChess\Repositories\RepositoryFactory;
use SoloChess\Repositories\UserRepository;

final class AuthSessionService
{
    /** @var Closure(): void */
    private Closure $rotateSessionId;

    /** @param (Closure(): void)|null $rotateSessionId */
    public function __construct(
        private AuthService $auth,
        private UserRepository $users,
        private SessionStore $sessions,
        ?Closure $rotateSessionId = null,
    ) {
        $this->rotateSessionId = $rotateSessionId ?? self::defaultSessionRotator();
    }

    public static function default(): self
    {
        $repositories = RepositoryFactory::default();
        $users = $repositories->userRepository();

        return new self(new AuthService($users), $users, new SessionStore());
    }

    public function register(string $username, string $displayName, string $password): AuthenticatedUser
    {
        $user = $this->auth->register($username, $displayName, $password);
        $this->authenticateSessionAs($user);

        return $user;
    }

    public function login(string $username, string $password): ?AuthenticatedUser
    {
        $user = $this->auth->authenticate($username, $password);
        if ($user === null) {
            return null;
        }

        $this->authenticateSessionAs($user);

        return $user;
    }

    public function logout(): void
    {
        $this->sessions->clearAuthenticatedUser();
    }

    public function currentUser(): ?AuthenticatedUser
    {
        $userId = $this->sessions->getAuthenticatedUserId();
        if ($userId === null) {
            return null;
        }

        $user = $this->users->findById($userId);
        if ($user === null) {
            $this->sessions->clearAuthenticatedUser();

            return null;
        }

        return AuthenticatedUser::fromUserRecord($user);
    }

    private function authenticateSessionAs(AuthenticatedUser $user): void
    {
        ($this->rotateSessionId)();
        $this->sessions->saveAuthenticatedUserId($user->id);
    }

    /** @return Closure(): void */
    private static function defaultSessionRotator(): Closure
    {
        return static function (): void {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                return;
            }

            if (!session_regenerate_id(true)) {
                throw new RuntimeException('Unable to rotate the PHP session identifier.');
            }
        };
    }
}
