<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use App\Service\Auth\RefreshTokenService;
use App\Service\Auth\TokenBlacklistService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RefreshTokenServiceTest extends TestCase
{
    private RefreshTokenRepository&MockObject $refreshTokenRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private JWTTokenManagerInterface&MockObject $jwtManager;
    private TokenBlacklistService&MockObject $tokenBlacklistService;
    private TranslatorInterface&MockObject $translator;
    private LoggerInterface&MockObject $logger;
    private RefreshTokenService $service;

    protected function setUp(): void
    {
        $this->refreshTokenRepository = $this->createMock(RefreshTokenRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $this->tokenBlacklistService = $this->createMock(TokenBlacklistService::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->translator->method('trans')->willReturnCallback(fn(string $key) => $key);

        $this->service = new RefreshTokenService(
            $this->refreshTokenRepository,
            $this->entityManager,
            $this->jwtManager,
            $this->tokenBlacklistService,
            $this->translator,
            $this->logger,
            2592000, // 30 days
        );
    }

    public function testCreateRefreshToken(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-123');

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(RefreshToken::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->createRefreshToken($user);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testRefreshValidToken(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-123');

        $refreshToken = $this->createMock(RefreshToken::class);
        $refreshToken->method('getUser')->willReturn($user);
        $refreshToken->method('isExpired')->willReturn(false);
        $refreshToken->method('isRevoked')->willReturn(false);

        $this->refreshTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with('valid-refresh-token')
            ->willReturn($refreshToken);

        $this->jwtManager->expects($this->once())
            ->method('create')
            ->with($user)
            ->willReturn('new.jwt.token');

        // Old token should be revoked
        $refreshToken->expects($this->once())
            ->method('revoke');

        // New token should be created
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(RefreshToken::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->refresh('valid-refresh-token');

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertEquals('new.jwt.token', $result['token']);
    }

    public function testRefreshExpiredToken(): void
    {
        $refreshToken = $this->createMock(RefreshToken::class);
        $refreshToken->method('isExpired')->willReturn(true);

        $this->refreshTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with('expired-refresh-token')
            ->willReturn($refreshToken);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('auth.refresh.token_expired');

        $this->service->refresh('expired-refresh-token');
    }

    public function testRefreshRevokedToken(): void
    {
        $refreshToken = $this->createMock(RefreshToken::class);
        $refreshToken->method('isExpired')->willReturn(false);
        $refreshToken->method('isRevoked')->willReturn(true);

        $this->refreshTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with('revoked-refresh-token')
            ->willReturn($refreshToken);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('auth.refresh.token_revoked');

        $this->service->refresh('revoked-refresh-token');
    }

    public function testRefreshInvalidToken(): void
    {
        $this->refreshTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with('invalid-refresh-token')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('auth.refresh.invalid_token');

        $this->service->refresh('invalid-refresh-token');
    }

    public function testRevokeAllUserTokens(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-123');

        $this->refreshTokenRepository->expects($this->once())
            ->method('revokeAllForUser')
            ->with($user);

        $this->service->revokeAllForUser($user);
    }
}
