<?php

namespace App\Controller;

use App\Dto\Admin\Query\PaginationQuery;
use App\Dto\Merchant\UpdateMerchantProfileRequest;
use App\Entity\Merchant;
use App\Entity\User;
use App\Entity\Wallet;
use App\Repository\MerchantRepository;
use App\Repository\WalletTransactionRepository;
use App\Service\WalletService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * 商户自助服务 - 商户角色用户管理自己的商户信息和钱包
 */
#[Route('/api/merchant')]
class MerchantProfileController extends AbstractController
{
    public function __construct(
        private MerchantRepository $merchantRepository,
        private WalletService $walletService,
        private WalletTransactionRepository $transactionRepository,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * 获取当前商户信息
     */
    #[Route('/profile', name: 'merchant_profile', methods: ['GET'])]
    public function getProfile(#[CurrentUser] User $user): JsonResponse
    {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeMerchant($merchant));
    }

    /**
     * 更新商户信息
     */
    #[Route('/profile', name: 'merchant_profile_update', methods: ['PUT'])]
    public function updateProfile(
        #[CurrentUser] User $user,
        #[MapRequestPayload] UpdateMerchantProfileRequest $dto
    ): JsonResponse {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $merchant->setName($dto->name);
        $merchant->setDescription($dto->description);
        $merchant->setContactName($dto->contactName);
        $merchant->setContactPhone($dto->contactPhone);
        $merchant->setProvince($dto->province);
        $merchant->setCity($dto->city);
        $merchant->setDistrict($dto->district);
        $merchant->setAddress($dto->address);

        $this->merchantRepository->save($merchant, true);

        return $this->json([
            'message' => $this->translator->trans('merchant.profile_updated'),
            'merchant' => $this->serializeMerchant($merchant),
        ]);
    }

    /**
     * 获取钱包列表
     */
    #[Route('/wallets', name: 'merchant_wallets', methods: ['GET'])]
    public function getWallets(#[CurrentUser] User $user): JsonResponse
    {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $depositWallet = $merchant->getDepositWallet();
        $balanceWallet = $merchant->getBalanceWallet();

        return $this->json([
            'deposit' => $depositWallet ? $this->serializeWallet($depositWallet) : null,
            'balance' => $balanceWallet ? $this->serializeWallet($balanceWallet) : null,
        ]);
    }

    /**
     * 获取保证金钱包交易明细
     */
    #[Route('/wallets/deposit/transactions', name: 'merchant_deposit_transactions', methods: ['GET'])]
    public function getDepositTransactions(
        #[CurrentUser] User $user,
        #[MapQueryString] PaginationQuery $query = new PaginationQuery()
    ): JsonResponse {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $wallet = $merchant->getDepositWallet();
        if (!$wallet) {
            return $this->json(['error' => $this->translator->trans('wallet.deposit_wallet_not_found')], Response::HTTP_NOT_FOUND);
        }

        $result = $this->transactionRepository->findByWalletPaginated(
            $wallet,
            $query->getPage(),
            $query->getLimit()
        );

        return $this->json([
            'data' => array_map(fn($t) => [
                'id' => $t->getId(),
                'transactionNo' => $t->getTransactionNo(),
                'type' => $t->getType(),
                'amount' => $t->getAmount(),
                'balanceBefore' => $t->getBalanceBefore(),
                'balanceAfter' => $t->getBalanceAfter(),
                'bizType' => $t->getBizType(),
                'remark' => $t->getRemark(),
                'createdAt' => $t->getCreatedAt()->format('c'),
            ], $result['data']),
            'total' => $result['total'],
            'page' => $query->getPage(),
            'limit' => $query->getLimit(),
            'wallet' => $this->serializeWallet($wallet),
        ]);
    }

    /**
     * 获取余额钱包交易明细
     */
    #[Route('/wallets/balance/transactions', name: 'merchant_balance_transactions', methods: ['GET'])]
    public function getBalanceTransactions(
        #[CurrentUser] User $user,
        #[MapQueryString] PaginationQuery $query = new PaginationQuery()
    ): JsonResponse {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $wallet = $merchant->getBalanceWallet();
        if (!$wallet) {
            return $this->json(['error' => $this->translator->trans('wallet.balance_wallet_not_found')], Response::HTTP_NOT_FOUND);
        }

        $result = $this->transactionRepository->findByWalletPaginated(
            $wallet,
            $query->getPage(),
            $query->getLimit()
        );

        return $this->json([
            'data' => array_map(fn($t) => [
                'id' => $t->getId(),
                'transactionNo' => $t->getTransactionNo(),
                'type' => $t->getType(),
                'amount' => $t->getAmount(),
                'balanceBefore' => $t->getBalanceBefore(),
                'balanceAfter' => $t->getBalanceAfter(),
                'bizType' => $t->getBizType(),
                'remark' => $t->getRemark(),
                'createdAt' => $t->getCreatedAt()->format('c'),
            ], $result['data']),
            'total' => $result['total'],
            'page' => $query->getPage(),
            'limit' => $query->getLimit(),
            'wallet' => $this->serializeWallet($wallet),
        ]);
    }

    private function serializeMerchant(Merchant $merchant): array
    {
        $user = $merchant->getUser();
        $depositWallet = $merchant->getDepositWallet();
        $balanceWallet = $merchant->getBalanceWallet();

        return [
            'id' => $merchant->getId(),
            'name' => $merchant->getName(),
            'email' => $user->getEmail(),
            'status' => $merchant->getStatus(),
            'description' => $merchant->getDescription(),
            'contactName' => $merchant->getContactName(),
            'contactPhone' => $merchant->getContactPhone(),
            'province' => $merchant->getProvince(),
            'city' => $merchant->getCity(),
            'district' => $merchant->getDistrict(),
            'address' => $merchant->getAddress(),
            'fullAddress' => $merchant->getFullAddress(),
            'businessLicense' => $merchant->getBusinessLicense(),
            'approvedAt' => $merchant->getApprovedAt()?->format('c'),
            'rejectedReason' => $merchant->getRejectedReason(),
            'createdAt' => $merchant->getCreatedAt()->format('c'),
            'updatedAt' => $merchant->getUpdatedAt()->format('c'),
            'depositBalance' => $depositWallet?->getBalance() ?? '0.00',
            'depositFrozen' => $depositWallet?->getFrozenAmount() ?? '0.00',
            'balanceAmount' => $balanceWallet?->getBalance() ?? '0.00',
            'balanceFrozen' => $balanceWallet?->getFrozenAmount() ?? '0.00',
        ];
    }

    private function serializeWallet(Wallet $wallet): array
    {
        return [
            'id' => $wallet->getId(),
            'type' => $wallet->getType(),
            'balance' => $wallet->getBalance(),
            'frozenAmount' => $wallet->getFrozenAmount(),
            'availableBalance' => $wallet->getAvailableBalance(),
            'status' => $wallet->getStatus(),
        ];
    }
}
