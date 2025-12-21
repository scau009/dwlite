<?php

namespace App\Service;

use App\Entity\Merchant;
use App\Entity\User;
use App\Repository\MerchantRepository;
use Doctrine\ORM\EntityManagerInterface;

class MerchantService
{
    public function __construct(
        private MerchantRepository $merchantRepository,
        private WalletService $walletService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * 为用户创建商户信息
     * 在邮箱验证通过后调用
     */
    public function createMerchantForUser(User $user): Merchant
    {
        // 检查用户是否已有商户
        $existingMerchant = $this->merchantRepository->findByUser($user);
        if ($existingMerchant !== null) {
            return $existingMerchant;
        }

        // 从邮箱提取默认商户名称
        $emailParts = explode('@', $user->getEmail());
        $defaultName = $emailParts[0];

        // 创建商户
        $merchant = new Merchant();
        $merchant->setUser($user);
        $merchant->setName($defaultName);
        $merchant->setContactName('');
        $merchant->setContactPhone('');

        $this->entityManager->persist($merchant);
        $this->entityManager->flush();

        // 初始化钱包
        $this->walletService->initWallets($merchant);

        return $merchant;
    }
}