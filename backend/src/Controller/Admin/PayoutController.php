<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Attribute\AdminOnly;
use App\Dto\Admin\Query\PayoutListQuery;
use App\Dto\Admin\RejectPayoutRequest;
use App\Entity\Payout;
use App\Entity\User;
use App\Repository\PayoutRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/admin/payouts')]
#[AdminOnly]
class PayoutController extends AbstractController
{
    public function __construct(
        private PayoutRepository $payoutRepository,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'admin_payout_list', methods: ['GET'])]
    public function list(#[MapQueryString] PayoutListQuery $query = new PayoutListQuery()): JsonResponse
    {
        $result = $this->payoutRepository->findPaginated(
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

    #[Route('/{id}', name: 'admin_payout_detail', methods: ['GET'])]
    public function detail(string $id): JsonResponse
    {
        $payout = $this->payoutRepository->find($id);
        if (!$payout) {
            return $this->json(['error' => $this->translator->trans('admin.payout.not_found')], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializePayout($payout, true));
    }

    #[Route('/{id}/approve', name: 'admin_payout_approve', methods: ['POST'])]
    public function approve(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $payout = $this->payoutRepository->find($id);
        if (!$payout) {
            return $this->json(['error' => $this->translator->trans('admin.payout.not_found')], Response::HTTP_NOT_FOUND);
        }

        if (!$payout->canApprove()) {
            return $this->json(['error' => $this->translator->trans('admin.payout.cannot_approve')], Response::HTTP_BAD_REQUEST);
        }

        $payout->markApproved($user->getId());
        $this->payoutRepository->save($payout, true);

        return $this->json([
            'message' => $this->translator->trans('admin.payout.approved'),
            'payout' => $this->serializePayout($payout),
        ]);
    }

    #[Route('/{id}/reject', name: 'admin_payout_reject', methods: ['POST'])]
    public function reject(
        string $id,
        #[MapRequestPayload] RejectPayoutRequest $dto,
        #[CurrentUser] User $user
    ): JsonResponse {
        $payout = $this->payoutRepository->find($id);
        if (!$payout) {
            return $this->json(['error' => $this->translator->trans('admin.payout.not_found')], Response::HTTP_NOT_FOUND);
        }

        if (!$payout->canReject()) {
            return $this->json(['error' => $this->translator->trans('admin.payout.cannot_reject')], Response::HTTP_BAD_REQUEST);
        }

        $payout->markRejected($user->getId(), $dto->reason);
        $this->payoutRepository->save($payout, true);

        return $this->json([
            'message' => $this->translator->trans('admin.payout.rejected'),
            'payout' => $this->serializePayout($payout),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePayout(Payout $payout, bool $detail = false): array
    {
        $data = [
            'id' => $payout->getId(),
            'payoutNo' => $payout->getPayoutNo(),
            'merchantId' => $payout->getMerchant()->getId(),
            'merchantName' => $payout->getMerchant()->getName(),
            'amount' => $payout->getAmount(),
            'fee' => $payout->getFee(),
            'actualAmount' => $payout->getActualAmount(),
            'currency' => $payout->getCurrency(),
            'status' => $payout->getStatus(),
            'bankName' => $payout->getBankName(),
            'maskedAccountNumber' => $payout->getMaskedAccountNumber(),
            'accountHolder' => $payout->getAccountHolder(),
            'createdAt' => $payout->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];

        if ($detail) {
            $data['bankAccountId'] = $payout->getBankAccount()->getId();
            $data['accountNumber'] = $payout->getAccountNumber();
            $data['bankCode'] = $payout->getBankCode();
            $data['approvedAt'] = $payout->getApprovedAt()?->format(\DateTimeInterface::ATOM);
            $data['processingAt'] = $payout->getProcessingAt()?->format(\DateTimeInterface::ATOM);
            $data['completedAt'] = $payout->getCompletedAt()?->format(\DateTimeInterface::ATOM);
            $data['rejectedAt'] = $payout->getRejectedAt()?->format(\DateTimeInterface::ATOM);
            $data['failedAt'] = $payout->getFailedAt()?->format(\DateTimeInterface::ATOM);
            $data['reviewedBy'] = $payout->getReviewedBy();
            $data['rejectReason'] = $payout->getRejectReason();
            $data['failReason'] = $payout->getFailReason();
            $data['remark'] = $payout->getRemark();
            $data['externalTransactionId'] = $payout->getExternalTransactionId();
            $data['walletTransactionId'] = $payout->getWalletTransactionId();
        }

        return $data;
    }
}
