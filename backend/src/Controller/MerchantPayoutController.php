<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Merchant\CreatePayoutRequest;
use App\Dto\Merchant\Query\MerchantPayoutListQuery;
use App\Entity\Payout;
use App\Entity\User;
use App\Repository\MerchantBankAccountRepository;
use App\Repository\MerchantRepository;
use App\Repository\PayoutRepository;
use App\Service\PayoutService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * 商户提现申请.
 */
#[Route('/api/merchant/payouts')]
class MerchantPayoutController extends AbstractController
{
    public function __construct(
        private readonly MerchantRepository $merchantRepository,
        private readonly PayoutRepository $payoutRepository,
        private readonly MerchantBankAccountRepository $bankAccountRepository,
        private readonly PayoutService $payoutService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * 获取我的提现列表.
     */
    #[Route('', name: 'merchant_payouts_list', methods: ['GET'])]
    public function list(
        #[CurrentUser] User $user,
        #[MapQueryString] MerchantPayoutListQuery $query = new MerchantPayoutListQuery()
    ): JsonResponse {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $result = $this->payoutRepository->findByMerchantPaginated(
            $merchant,
            $query->getPage(),
            $query->getLimit(),
            $query->toFilters()
        );

        return $this->json([
            'data' => array_map(fn (Payout $p) => $this->serializePayout($p), $result['data']),
            'total' => $result['total'],
            'page' => $query->getPage(),
            'limit' => $query->getLimit(),
        ]);
    }

    /**
     * 获取提现详情.
     */
    #[Route('/{id}', name: 'merchant_payout_detail', methods: ['GET'])]
    public function detail(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $payout = $this->payoutRepository->find($id);
        if (!$payout || $payout->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => $this->translator->trans('payout.not_found')], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializePayoutDetail($payout));
    }

    /**
     * 申请提现.
     */
    #[Route('', name: 'merchant_payout_create', methods: ['POST'])]
    public function create(
        #[CurrentUser] User $user,
        #[MapRequestPayload] CreatePayoutRequest $dto
    ): JsonResponse {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        try {
            $payout = $this->payoutService->createPayout(
                $merchant,
                $dto->bankAccountId,
                $dto->amount,
                $dto->remark
            );

            return $this->json([
                'message' => $this->translator->trans('payout.created'),
                'data' => $this->serializePayoutDetail($payout),
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'error' => $this->translator->trans($e->getMessage()),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * 获取提现统计摘要.
     */
    #[Route('/summary', name: 'merchant_payouts_summary', methods: ['GET'], priority: 10)]
    public function summary(#[CurrentUser] User $user): JsonResponse
    {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $summary = $this->payoutService->getPayoutSummary($merchant);

        // 获取可用的银行账户
        $bankAccounts = $this->bankAccountRepository->findActiveByMerchant($merchant);

        return $this->json([
            'availableBalance' => $summary['availableBalance'],
            'processingAmount' => $summary['processingAmount'],
            'pendingAmount' => $summary['pendingAmount'],
            'bankAccounts' => array_map(fn ($a) => [
                'id' => $a->getId(),
                'bankName' => $a->getBankName(),
                'maskedAccountNumber' => $a->getMaskedAccountNumber(),
                'accountHolder' => $a->getAccountHolder(),
                'isDefault' => $a->isDefault(),
            ], $bankAccounts),
        ]);
    }

    private function serializePayout(Payout $payout): array
    {
        return [
            'id' => $payout->getId(),
            'payoutNo' => $payout->getPayoutNo(),
            'amount' => $payout->getAmount(),
            'fee' => $payout->getFee(),
            'actualAmount' => $payout->getActualAmount(),
            'currency' => $payout->getCurrency(),
            'status' => $payout->getStatus(),
            'bankName' => $payout->getBankName(),
            'maskedAccountNumber' => $payout->getMaskedAccountNumber(),
            'accountHolder' => $payout->getAccountHolder(),
            'createdAt' => $payout->getCreatedAt()->format('c'),
        ];
    }

    private function serializePayoutDetail(Payout $payout): array
    {
        $data = $this->serializePayout($payout);

        // 添加时间线信息
        $data['approvedAt'] = $payout->getApprovedAt()?->format('c');
        $data['processingAt'] = $payout->getProcessingAt()?->format('c');
        $data['completedAt'] = $payout->getCompletedAt()?->format('c');
        $data['rejectedAt'] = $payout->getRejectedAt()?->format('c');
        $data['failedAt'] = $payout->getFailedAt()?->format('c');

        // 添加原因
        $data['rejectReason'] = $payout->getRejectReason();
        $data['failReason'] = $payout->getFailReason();
        $data['remark'] = $payout->getRemark();

        return $data;
    }
}
