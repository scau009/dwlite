<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Merchant\CreateBankAccountRequest;
use App\Dto\Merchant\UpdateBankAccountRequest;
use App\Entity\MerchantBankAccount;
use App\Entity\User;
use App\Repository\MerchantBankAccountRepository;
use App\Repository\MerchantRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * 商户银行账户管理.
 */
#[Route('/api/merchant/bank-accounts')]
class MerchantBankAccountController extends AbstractController
{
    public function __construct(
        private readonly MerchantRepository $merchantRepository,
        private readonly MerchantBankAccountRepository $bankAccountRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * 获取银行账户列表.
     */
    #[Route('', name: 'merchant_bank_accounts_list', methods: ['GET'])]
    public function list(#[CurrentUser] User $user): JsonResponse
    {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $accounts = $this->bankAccountRepository->findByMerchant($merchant);

        return $this->json([
            'data' => array_map(fn (MerchantBankAccount $a) => $this->serializeBankAccount($a), $accounts),
            'total' => count($accounts),
        ]);
    }

    /**
     * 获取银行账户详情.
     */
    #[Route('/{id}', name: 'merchant_bank_account_detail', methods: ['GET'])]
    public function detail(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $account = $this->bankAccountRepository->find($id);
        if (!$account || $account->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => $this->translator->trans('bank_account.not_found')], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeBankAccount($account, true));
    }

    /**
     * 添加银行账户.
     */
    #[Route('', name: 'merchant_bank_account_create', methods: ['POST'])]
    public function create(
        #[CurrentUser] User $user,
        #[MapRequestPayload] CreateBankAccountRequest $dto
    ): JsonResponse {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $account = new MerchantBankAccount();
        $account->setMerchant($merchant);
        $account->setBankName($dto->bankName);
        $account->setBankCode($dto->bankCode);
        $account->setBranchName($dto->branchName);
        $account->setAccountNumber($dto->accountNumber);
        $account->setAccountHolder($dto->accountHolder);
        $account->setAccountType($dto->accountType);
        $account->setCurrency($dto->currency);
        $account->setStatus(MerchantBankAccount::STATUS_ACTIVE); // 默认启用

        // 如果是第一个账户，设为默认
        $existingCount = $this->bankAccountRepository->countByMerchant($merchant);
        if ($existingCount === 0) {
            $account->setIsDefault(true);
        }

        $this->bankAccountRepository->save($account, true);

        return $this->json([
            'message' => $this->translator->trans('bank_account.created'),
            'data' => $this->serializeBankAccount($account, true),
        ], Response::HTTP_CREATED);
    }

    /**
     * 更新银行账户.
     */
    #[Route('/{id}', name: 'merchant_bank_account_update', methods: ['PUT'])]
    public function update(
        string $id,
        #[CurrentUser] User $user,
        #[MapRequestPayload] UpdateBankAccountRequest $dto
    ): JsonResponse {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $account = $this->bankAccountRepository->find($id);
        if (!$account || $account->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => $this->translator->trans('bank_account.not_found')], Response::HTTP_NOT_FOUND);
        }

        $account->setBankName($dto->bankName);
        $account->setBankCode($dto->bankCode);
        $account->setBranchName($dto->branchName);
        $account->setAccountNumber($dto->accountNumber);
        $account->setAccountHolder($dto->accountHolder);
        $account->setAccountType($dto->accountType);
        $account->setCurrency($dto->currency);

        $this->bankAccountRepository->save($account, true);

        return $this->json([
            'message' => $this->translator->trans('bank_account.updated'),
            'data' => $this->serializeBankAccount($account, true),
        ]);
    }

    /**
     * 删除银行账户.
     */
    #[Route('/{id}', name: 'merchant_bank_account_delete', methods: ['DELETE'])]
    public function delete(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $account = $this->bankAccountRepository->find($id);
        if (!$account || $account->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => $this->translator->trans('bank_account.not_found')], Response::HTTP_NOT_FOUND);
        }

        // 如果删除的是默认账户，尝试设置另一个为默认
        $wasDefault = $account->isDefault();
        $this->bankAccountRepository->remove($account, true);

        if ($wasDefault) {
            $remainingAccounts = $this->bankAccountRepository->findByMerchant($merchant);
            if (count($remainingAccounts) > 0) {
                $remainingAccounts[0]->setIsDefault(true);
                $this->bankAccountRepository->save($remainingAccounts[0], true);
            }
        }

        return $this->json(['message' => $this->translator->trans('bank_account.deleted')]);
    }

    /**
     * 设为默认账户.
     */
    #[Route('/{id}/default', name: 'merchant_bank_account_set_default', methods: ['PUT'])]
    public function setDefault(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $account = $this->bankAccountRepository->find($id);
        if (!$account || $account->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => $this->translator->trans('bank_account.not_found')], Response::HTTP_NOT_FOUND);
        }

        // 清除其他账户的默认标记
        $this->bankAccountRepository->clearDefaultByMerchant($merchant);

        // 设置当前账户为默认
        $account->setIsDefault(true);
        $this->bankAccountRepository->save($account, true);

        return $this->json([
            'message' => $this->translator->trans('bank_account.set_default'),
            'data' => $this->serializeBankAccount($account),
        ]);
    }

    /**
     * @param bool $includeFull 是否包含完整账号
     */
    private function serializeBankAccount(MerchantBankAccount $account, bool $includeFull = false): array
    {
        $data = [
            'id' => $account->getId(),
            'bankName' => $account->getBankName(),
            'bankCode' => $account->getBankCode(),
            'branchName' => $account->getBranchName(),
            'maskedAccountNumber' => $account->getMaskedAccountNumber(),
            'accountHolder' => $account->getAccountHolder(),
            'accountType' => $account->getAccountType(),
            'currency' => $account->getCurrency(),
            'status' => $account->getStatus(),
            'isDefault' => $account->isDefault(),
            'createdAt' => $account->getCreatedAt()->format('c'),
            'updatedAt' => $account->getUpdatedAt()->format('c'),
        ];

        if ($includeFull) {
            $data['accountNumber'] = $account->getAccountNumber();
        }

        return $data;
    }
}
