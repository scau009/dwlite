<?php

namespace App\Controller\Admin;

use App\Attribute\AdminOnly;
use App\Dto\Admin\ChargeDepositRequest;
use App\Dto\Admin\Query\MerchantListQuery;
use App\Dto\Admin\Query\PaginationQuery;
use App\Dto\Admin\UpdateMerchantStatusRequest;
use App\Entity\Merchant;
use App\Entity\User;
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

#[Route('/api/admin/merchants')]
#[AdminOnly]
class MerchantController extends AbstractController
{
    public function __construct(
        private MerchantRepository $merchantRepository,
        private WalletService $walletService,
        private WalletTransactionRepository $transactionRepository,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'admin_merchant_list', methods: ['GET'])]
    public function list(#[MapQueryString] MerchantListQuery $query = new MerchantListQuery()): JsonResponse
    {
        $result = $this->merchantRepository->findPaginated(
            $query->getPage(),
            $query->getLimit(),
            $query->toFilters()
        );

        return $this->json([
            'data' => array_map(fn(Merchant $m) => $this->serializeMerchant($m), $result['data']),
            'total' => $result['total'],
            'page' => $query->getPage(),
            'limit' => $query->getLimit(),
        ]);
    }

    #[Route('/{id}', name: 'admin_merchant_detail', methods: ['GET'])]
    public function detail(string $id): JsonResponse
    {
        $merchant = $this->merchantRepository->find($id);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('admin.merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeMerchant($merchant, true));
    }

    #[Route('/{id}/status', name: 'admin_merchant_status', methods: ['PUT'])]
    public function updateStatus(string $id, #[MapRequestPayload] UpdateMerchantStatusRequest $dto): JsonResponse
    {
        $merchant = $this->merchantRepository->find($id);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('admin.merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        if ($dto->enabled) {
            $merchant->enable();
        } else {
            $merchant->disable();
        }

        $this->merchantRepository->save($merchant, true);

        return $this->json([
            'message' => $dto->enabled ? $this->translator->trans('admin.merchant.enabled') : $this->translator->trans('admin.merchant.disabled'),
            'merchant' => $this->serializeMerchant($merchant),
        ]);
    }

    #[Route('/{id}/wallets/init', name: 'admin_merchant_init_wallets', methods: ['POST'])]
    public function initWallets(string $id): JsonResponse
    {
        $merchant = $this->merchantRepository->find($id);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('admin.merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        try {
            $wallets = $this->walletService->initWallets($merchant);
            return $this->json([
                'message' => $this->translator->trans('wallet.init_success'),
                'wallets' => array_map(fn($w) => [
                    'id' => $w->getId(),
                    'type' => $w->getType(),
                    'balance' => $w->getBalance(),
                    'status' => $w->getStatus(),
                ], $wallets),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/wallets/deposit/charge', name: 'admin_merchant_charge_deposit', methods: ['POST'])]
    public function chargeDeposit(
        string $id,
        #[MapRequestPayload] ChargeDepositRequest $dto,
        #[CurrentUser] User $user
    ): JsonResponse {
        $merchant = $this->merchantRepository->find($id);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('admin.merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        try {
            $transaction = $this->walletService->chargeDeposit(
                $merchant,
                $dto->amount,
                $dto->remark,
                $user->getId()
            );

            $wallet = $this->walletService->getDepositWallet($merchant);

            return $this->json([
                'message' => $this->translator->trans('wallet.deposit_charged'),
                'transaction' => [
                    'id' => $transaction->getId(),
                    'amount' => $transaction->getAmount(),
                    'balanceBefore' => $transaction->getBalanceBefore(),
                    'balanceAfter' => $transaction->getBalanceAfter(),
                    'createdAt' => $transaction->getCreatedAt()->format('c'),
                ],
                'wallet' => [
                    'id' => $wallet->getId(),
                    'balance' => $wallet->getBalance(),
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/wallets/deposit/transactions', name: 'admin_merchant_deposit_transactions', methods: ['GET'])]
    public function depositTransactions(
        string $id,
        #[MapQueryString] PaginationQuery $query = new PaginationQuery()
    ): JsonResponse {
        $merchant = $this->merchantRepository->find($id);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('admin.merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $wallet = $this->walletService->getDepositWallet($merchant);
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
                'type' => $t->getType(),
                'amount' => $t->getAmount(),
                'balanceBefore' => $t->getBalanceBefore(),
                'balanceAfter' => $t->getBalanceAfter(),
                'bizType' => $t->getBizType(),
                'remark' => $t->getRemark(),
                'operatorId' => $t->getOperatorId(),
                'createdAt' => $t->getCreatedAt()->format('c'),
            ], $result['data']),
            'total' => $result['total'],
            'page' => $query->getPage(),
            'limit' => $query->getLimit(),
            'wallet' => [
                'id' => $wallet->getId(),
                'balance' => $wallet->getBalance(),
                'frozenAmount' => $wallet->getFrozenAmount(),
                'status' => $wallet->getStatus(),
            ],
        ]);
    }

    private function serializeMerchant(Merchant $merchant, bool $detail = false): array
    {
        $data = [
            'id' => $merchant->getId(),
            'name' => $merchant->getName(),
            'shortName' => $merchant->getShortName(),
            'status' => $merchant->getStatus(),
            'contactName' => $merchant->getContactName(),
            'contactPhone' => $merchant->getContactPhone(),
            'createdAt' => $merchant->getCreatedAt()->format('c'),
            'updatedAt' => $merchant->getUpdatedAt()->format('c'),
        ];

        // 钱包信息
        $depositWallet = $merchant->getDepositWallet();
        $balanceWallet = $merchant->getBalanceWallet();

        $data['hasWallets'] = $depositWallet !== null;
        $data['depositBalance'] = $depositWallet?->getBalance() ?? '0.00';
        $data['balanceAmount'] = $balanceWallet?->getBalance() ?? '0.00';

        if ($detail) {
            $data['logo'] = $merchant->getLogo();
            $data['description'] = $merchant->getDescription();
            $data['province'] = $merchant->getProvince();
            $data['city'] = $merchant->getCity();
            $data['district'] = $merchant->getDistrict();
            $data['address'] = $merchant->getAddress();
            $data['fullAddress'] = $merchant->getFullAddress();
            $data['businessLicense'] = $merchant->getBusinessLicense();
            $data['approvedAt'] = $merchant->getApprovedAt()?->format('c');
            $data['rejectedReason'] = $merchant->getRejectedReason();
            $data['user'] = [
                'id' => $merchant->getUser()->getId(),
                'email' => $merchant->getUser()->getEmail(),
            ];
        }

        return $data;
    }
}
