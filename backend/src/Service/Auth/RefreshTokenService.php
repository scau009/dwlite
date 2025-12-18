<?php

namespace App\Service\Auth;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RefreshTokenService
{
    private const DEFAULT_TTL = 2592000; // 30 days
    private const MAX_ACTIVE_TOKENS = 5; // Maximum active refresh tokens per user

    public function __construct(
        private RefreshTokenRepository $refreshTokenRepository,
        private JWTTokenManagerInterface $jwtManager,
        private TranslatorInterface $translator,
        private int $ttl = self::DEFAULT_TTL,
    ) {
    }

    public function createForUser(User $user): RefreshToken
    {
        // Limit active tokens per user
        $activeCount = $this->refreshTokenRepository->countActiveForUser($user);
        if ($activeCount >= self::MAX_ACTIVE_TOKENS) {
            // Revoke all existing tokens when limit reached
            $this->refreshTokenRepository->revokeAllForUser($user);
        }

        $refreshToken = new RefreshToken($user, $this->ttl);
        $this->refreshTokenRepository->save($refreshToken, true);

        return $refreshToken;
    }

    public function refresh(string $token): array
    {
        $refreshToken = $this->refreshTokenRepository->findValidByToken($token);

        if ($refreshToken === null) {
            throw new \InvalidArgumentException($this->translator->trans('auth.refresh.invalid_token'));
        }

        $user = $refreshToken->getUser();

        // Check if user is still valid
        if (!$user->isVerified()) {
            throw new \InvalidArgumentException($this->translator->trans('auth.refresh.email_not_verified'));
        }

        // Revoke the old refresh token (single use)
        $refreshToken->revoke();
        $this->refreshTokenRepository->save($refreshToken, true);

        // Create new tokens
        $newRefreshToken = $this->createForUser($user);
        $accessToken = $this->jwtManager->create($user);

        return [
            'token' => $accessToken,
            'refresh_token' => $newRefreshToken->getToken(),
        ];
    }

    public function revokeToken(string $token): bool
    {
        $refreshToken = $this->refreshTokenRepository->findByToken($token);

        if ($refreshToken === null) {
            return false;
        }

        $refreshToken->revoke();
        $this->refreshTokenRepository->save($refreshToken, true);

        return true;
    }

    public function revokeAllForUser(User $user): int
    {
        return $this->refreshTokenRepository->revokeAllForUser($user);
    }

    public function cleanupExpired(): int
    {
        return $this->refreshTokenRepository->deleteExpired();
    }
}
