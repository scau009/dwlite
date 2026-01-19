<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use App\Service\Auth\PasswordResetService;
use App\Service\Mail\MailServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class PasswordResetServiceTest extends TestCase
{
    private PasswordResetTokenRepository&MockObject $tokenRepository;
    private UserRepository&MockObject $userRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private MailServiceInterface&MockObject $mailService;
    private TranslatorInterface&MockObject $translator;
    private LoggerInterface&MockObject $logger;
    private PasswordResetService $service;

    protected function setUp(): void
    {
        $this->tokenRepository = $this->createMock(PasswordResetTokenRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->mailService = $this->createMock(MailServiceInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->translator->method('trans')->willReturnCallback(fn(string $key) => $key);

        $this->service = new PasswordResetService(
            $this->tokenRepository,
            $this->userRepository,
            $this->entityManager,
            $this->passwordHasher,
            $this->mailService,
            $this->translator,
            $this->logger,
            'http://localhost:3000',
        );
    }

    public function testRequestPasswordReset(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-123');
        $user->method('getEmail')->willReturn('test@example.com');

        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->with('test@example.com')
            ->willReturn($user);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(PasswordResetToken::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->mailService->expects($this->once())
            ->method('sendPasswordResetEmail')
            ->with(
                'test@example.com',
                $this->stringContains('http://localhost:3000')
            );

        $this->service->requestPasswordReset('test@example.com');
    }

    public function testRequestPasswordResetUserNotFound(): void
    {
        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->with('notfound@example.com')
            ->willReturn(null);

        // Should not throw, just silently fail (security best practice)
        $this->mailService->expects($this->never())
            ->method('sendPasswordResetEmail');

        $this->service->requestPasswordReset('notfound@example.com');
    }

    public function testResetWithValidToken(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-123');

        $token = $this->createMock(PasswordResetToken::class);
        $token->method('getUser')->willReturn($user);
        $token->method('isExpired')->willReturn(false);
        $token->method('isUsed')->willReturn(false);

        $this->tokenRepository->expects($this->once())
            ->method('findByToken')
            ->with('valid-token')
            ->willReturn($token);

        $this->passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->with($user, 'newPassword123')
            ->willReturn('hashed_password');

        $user->expects($this->once())
            ->method('setPassword')
            ->with('hashed_password');

        $token->expects($this->once())
            ->method('markAsUsed');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->resetPassword('valid-token', 'newPassword123');

        $this->assertSame($user, $result);
    }

    public function testResetWithExpiredToken(): void
    {
        $token = $this->createMock(PasswordResetToken::class);
        $token->method('isExpired')->willReturn(true);

        $this->tokenRepository->expects($this->once())
            ->method('findByToken')
            ->with('expired-token')
            ->willReturn($token);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('auth.reset_password.token_expired');

        $this->service->resetPassword('expired-token', 'newPassword123');
    }

    public function testResetWithUsedToken(): void
    {
        $token = $this->createMock(PasswordResetToken::class);
        $token->method('isExpired')->willReturn(false);
        $token->method('isUsed')->willReturn(true);

        $this->tokenRepository->expects($this->once())
            ->method('findByToken')
            ->with('used-token')
            ->willReturn($token);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('auth.reset_password.token_already_used');

        $this->service->resetPassword('used-token', 'newPassword123');
    }

    public function testResetWithInvalidToken(): void
    {
        $this->tokenRepository->expects($this->once())
            ->method('findByToken')
            ->with('invalid-token')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('auth.reset_password.invalid_token');

        $this->service->resetPassword('invalid-token', 'newPassword123');
    }
}
