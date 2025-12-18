<?php

namespace App\Controller\Admin;

use App\Entity\Merchant;
use App\Entity\User;
use App\Repository\MerchantRepository;
use App\Repository\WalletTransactionRepository;
use App\Service\WalletService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/admin/merchants')]
class MerchantController extends AbstractController
{
    public function __construct(
        private MerchantRepository $merchantRepository,
        private WalletService $walletService,
        private WalletTransactionRepository $transactionRepository,
    ) {
    }

    /**
     * 检查管理员权限
     */
    private function checkAdmin(User $user): ?JsonResponse
    {
        if (!$user->isAdmin()) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }
        return null;
    }

    #[Route('', name: 'admin_merchant_list', methods: ['GET'])]
    public function list(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        if ($error = $this->checkAdmin($user)) {
            return $error;
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));

        $filters = [];
        if ($status = $request->query->get('status')) {
            $filters['status'] = $status;
        }
        if ($name = $request->query->get('name')) {
            $filters['name'] = $name;
        }

        $result = $this->merchantRepository->findPaginated($page, $limit, $filters);

        return $this->json([
            'data' => array_map(fn(Merchant $m) => $this->serializeMerchant($m), $result['data']),
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    #[Route('/{id}', name: 'admin_merchant_detail', methods: ['GET'])]
    public function detail(string $id, #[CurrentUser] User $user): JsonResponse
    {
        if ($error = $this->checkAdmin($user)) {
            return $error;
        }

        $merchant = $this->merchantRepository->find($id);
        if (!$merchant) {
            return $this->json(['error' => 'Merchant not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeMerchant($merchant, true));
    }

    #[Route('/{id}/status', name: 'admin_merchant_status', methods: ['PUT'])]
    public function updateStatus(string $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        if ($error = $this->checkAdmin($user)) {
            return $error;
        }

        $merchant = $this->merchantRepository->find($id);
        if (!$merchant) {
            return $this->json(['error' => 'Merchant not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $enabled = $data['enabled'] ?? null;

        if ($enabled === null) {
            return $this->json(['error' => 'enabled field is required'], Response::HTTP_BAD_REQUEST);
        }

        if ($enabled) {
            $merchant->enable();
        } else {
            $merchant->disable();
        }

        $this->merchantRepository->save($merchant, true);

        return $this->json([
            'message' => $enabled ? 'Merchant enabled' : 'Merchant disabled',
            'merchant' => $this->serializeMerchant($merchant),
        ]);
    }

    #[Route('/{id}/wallets/init', name: 'admin_merchant_init_wallets', methods: ['POST'])]
    public function initWallets(string $id, #[CurrentUser] User $user): JsonResponse
    {
        if ($error = $this->checkAdmin($user)) {
            return $error;
        }

        $merchant = $this->merchantRepository->find($id);
        if (!$merchant) {
            return $this->json(['error' => 'Merchant not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $wallets = $this->walletService->initWallets($merchant);
            return $this->json([
                'message' => 'Wallets initialized successfully',
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
    public function chargeDeposit(string $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        if ($error = $this->checkAdmin($user)) {
            return $error;
        }

        $merchant = $this->merchantRepository->find($id);
        if (!$merchant) {
            return $this->json(['error' => 'Merchant not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $amount = $data['amount'] ?? null;
        $remark = $data['remark'] ?? null;

        if ($amount === null) {
            return $this->json(['error' => 'amount field is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $transaction = $this->walletService->chargeDeposit(
                $merchant,
                (string) $amount,
                $remark,
                $user->getId()
            );

            $wallet = $this->walletService->getDepositWallet($merchant);

            return $this->json([
                'message' => 'Deposit charged successfully',
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
    public function depositTransactions(string $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        if ($error = $this->checkAdmin($user)) {
            return $error;
        }

        $merchant = $this->merchantRepository->find($id);
        if (!$merchant) {
            return $this->json(['error' => 'Merchant not found'], Response::HTTP_NOT_FOUND);
        }

        $wallet = $this->walletService->getDepositWallet($merchant);
        if (!$wallet) {
            return $this->json(['error' => 'Deposit wallet not found'], Response::HTTP_NOT_FOUND);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));

        $result = $this->transactionRepository->findByWalletPaginated($wallet, $page, $limit);

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
            'page' => $page,
            'limit' => $limit,
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
