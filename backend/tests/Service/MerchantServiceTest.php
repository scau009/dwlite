<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Merchant;
use App\Entity\User;
use App\Entity\Wallet;
use App\Repository\MerchantRepository;
use App\Service\MerchantService;
use App\Service\WalletService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MerchantServiceTest extends TestCase
{
    private MerchantRepository&MockObject $merchantRepository;
    private WalletService&MockObject $walletService;
    private EntityManagerInterface&MockObject $entityManager;
    private TranslatorInterface&MockObject $translator;
    private LoggerInterface&MockObject $logger;
    private MerchantService $service;

    protected function setUp(): void
    {
        $this->merchantRepository = $this->createMock(MerchantRepository::class);
        $this->walletService = $this->createMock(WalletService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->translator->method('trans')->willReturnCallback(fn(string $key) => $key);

        $this->service = new MerchantService(
            $this->merchantRepository,
            $this->walletService,
            $this->entityManager,
            $this->translator,
            $this->logger,
        );
    }

    public function testGetMerchantByUser(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-123');

        $merchant = $this->createMock(Merchant::class);

        $this->merchantRepository->expects($this->once())
            ->method('findByUser')
            ->with($user)
            ->willReturn($merchant);

        $result = $this->service->getMerchantByUser($user);

        $this->assertSame($merchant, $result);
    }

    public function testGetMerchantByUserNotFound(): void
    {
        $user = $this->createMock(User::class);

        $this->merchantRepository->expects($this->once())
            ->method('findByUser')
            ->with($user)
            ->willReturn(null);

        $result = $this->service->getMerchantByUser($user);

        $this->assertNull($result);
    }

    public function testCreateMerchantForUser(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-123');
        $user->method('getEmail')->willReturn('test@example.com');

        $this->merchantRepository->expects($this->once())
            ->method('findByUser')
            ->with($user)
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Merchant::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $depositWallet = $this->createMock(Wallet::class);
        $balanceWallet = $this->createMock(Wallet::class);

        $this->walletService->expects($this->once())
            ->method('initWallets')
            ->with($this->isInstanceOf(Merchant::class))
            ->willReturn([$depositWallet, $balanceWallet]);

        $result = $this->service->createMerchantForUser($user);

        $this->assertInstanceOf(Merchant::class, $result);
    }

    public function testCreateMerchantForUserAlreadyExists(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-123');

        $existingMerchant = $this->createMock(Merchant::class);

        $this->merchantRepository->expects($this->once())
            ->method('findByUser')
            ->with($user)
            ->willReturn($existingMerchant);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('merchant.already_exists');

        $this->service->createMerchantForUser($user);
    }
}
