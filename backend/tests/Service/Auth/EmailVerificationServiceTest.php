<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Entity\EmailVerificationToken;
use App\Entity\User;
use App\Repository\EmailVerificationTokenRepository;
use App\Repository\UserRepository;
use App\Service\Auth\EmailVerificationService;
use App\Service\Mail\MailServiceInterface;
use App\Service\MerchantService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class EmailVerificationServiceTest extends TestCase
{
    private EmailVerificationTokenRepository&MockObject $tokenRepository;
    private UserRepository&MockObject $userRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private MailServiceInterface&MockObject $mailService;
    private MerchantService&MockObject $merchantService;
    private TranslatorInterface&MockObject $translator;
    private LoggerInterface&MockObject $logger;
    private EmailVerificationService $service;

    protected function setUp(): void
    {
        $this->tokenRepository = $this->createMock(EmailVerificationTokenRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->mailService = $this->createMock(MailServiceInterface::class);
        $this->merchantService = $this->createMock(MerchantService::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->translator->method('trans')->willReturnCallback(fn(string $key) => $key);

        $this->service = new EmailVerificationService(
            $this->tokenRepository,
            $this->userRepository,
            $this->entityManager,
            $this->mailService,
            $this->merchantService,
            $this->translator,
            $this->logger,
            'http://localhost:3000',
        );
    }

    public function testSendVerificationEmail(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-123');
        $user->method('getEmail')->willReturn('test@example.com');

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(EmailVerificationToken::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->mailService->expects($this->once())
            ->method('sendVerificationEmail')
            ->with(
                'test@example.com',
                $this->stringContains('http://localhost:3000')
            );

        $this->service->sendVerificationEmail($user);
    }

    public function testVerifyValidToken(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-123');
        $user->method('getAccountType')->willReturn(User::ACCOUNT_TYPE_MERCHANT);

        $token = $this->createMock(EmailVerificationToken::class);
        $token->method('getUser')->willReturn($user);
        $token->method('isExpired')->willReturn(false);
        $token->method('isUsed')->willReturn(false);

        $this->tokenRepository->expects($this->once())
            ->method('findByToken')
            ->with('valid-token')
            ->willReturn($token);

        $user->expects($this->once())
            ->method('setIsVerified')
            ->with(true);

        $token->expects($this->once())
            ->method('markAsUsed');

        $this->merchantService->expects($this->once())
            ->method('createMerchantForUser')
            ->with($user);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->verifyEmail('valid-token');

        $this->assertSame($user, $result);
    }

    public function testVerifyExpiredToken(): void
    {
        $token = $this->createMock(EmailVerificationToken::class);
        $token->method('isExpired')->willReturn(true);

        $this->tokenRepository->expects($this->once())
            ->method('findByToken')
            ->with('expired-token')
            ->willReturn($token);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('auth.verification.token_expired');

        $this->service->verifyEmail('expired-token');
    }

    public function testVerifyUsedToken(): void
    {
        $token = $this->createMock(EmailVerificationToken::class);
        $token->method('isExpired')->willReturn(false);
        $token->method('isUsed')->willReturn(true);

        $this->tokenRepository->expects($this->once())
            ->method('findByToken')
            ->with('used-token')
            ->willReturn($token);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('auth.verification.token_already_used');

        $this->service->verifyEmail('used-token');
    }

    public function testVerifyTokenNotFound(): void
    {
        $this->tokenRepository->expects($this->once())
            ->method('findByToken')
            ->with('invalid-token')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('auth.verification.invalid_token');

        $this->service->verifyEmail('invalid-token');
    }
}
