<?php

namespace App\Controller\Admin;

use App\Attribute\AdminOnly;
use App\Dto\Admin\Query\MerchantChannelListQuery;
use App\Dto\Admin\SuspendMerchantChannelRequest;
use App\Entity\MerchantSalesChannel;
use App\Entity\User;
use App\Repository\MerchantSalesChannelRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/admin/merchant-channels')]
#[AdminOnly]
class MerchantChannelController extends AbstractController
{
    public function __construct(
        private MerchantSalesChannelRepository $merchantChannelRepository,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'admin_merchant_channel_list', methods: ['GET'])]
    public function list(#[MapQueryString] MerchantChannelListQuery $query = new MerchantChannelListQuery()): JsonResponse
    {
        $result = $this->merchantChannelRepository->findPaginated(
            $query->getPage(),
            $query->getLimit(),
            $query->toFilters()
        );

        return $this->json([
            'data' => array_map(fn (MerchantSalesChannel $mc) => $this->serializeMerchantChannel($mc), $result['data']),
            'total' => $result['total'],
            'page' => $query->getPage(),
            'limit' => $query->getLimit(),
        ]);
    }

    #[Route('/pending', name: 'admin_merchant_channel_pending', methods: ['GET'])]
    public function pending(): JsonResponse
    {
        $result = $this->merchantChannelRepository->findPendingApproval();

        return $this->json([
            'data' => array_map(fn (MerchantSalesChannel $mc) => $this->serializeMerchantChannel($mc), $result),
            'total' => count($result),
        ]);
    }

    #[Route('/pending-count', name: 'admin_merchant_channel_pending_count', methods: ['GET'])]
    public function pendingCount(): JsonResponse
    {
        $count = $this->merchantChannelRepository->countPendingApproval();

        return $this->json(['count' => $count]);
    }

    #[Route('/{id}', name: 'admin_merchant_channel_detail', methods: ['GET'])]
    public function detail(string $id): JsonResponse
    {
        $mc = $this->merchantChannelRepository->find($id);
        if (!$mc) {
            return $this->json(['error' => $this->translator->trans('admin.merchant_channel.not_found')], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeMerchantChannel($mc, true));
    }

    #[Route('/{id}/approve', name: 'admin_merchant_channel_approve', methods: ['POST'])]
    public function approve(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $mc = $this->merchantChannelRepository->find($id);
        if (!$mc) {
            return $this->json(['error' => $this->translator->trans('admin.merchant_channel.not_found')], Response::HTTP_NOT_FOUND);
        }

        if (!$mc->isPending()) {
            return $this->json(['error' => $this->translator->trans('admin.merchant_channel.not_pending')], Response::HTTP_BAD_REQUEST);
        }

        $mc->approve($user->getId());
        $this->merchantChannelRepository->save($mc, true);

        return $this->json([
            'message' => $this->translator->trans('admin.merchant_channel.approved'),
            'merchantChannel' => $this->serializeMerchantChannel($mc),
        ]);
    }

    #[Route('/{id}/suspend', name: 'admin_merchant_channel_suspend', methods: ['POST'])]
    public function suspend(string $id, #[MapRequestPayload] SuspendMerchantChannelRequest $dto): JsonResponse
    {
        $mc = $this->merchantChannelRepository->find($id);
        if (!$mc) {
            return $this->json(['error' => $this->translator->trans('admin.merchant_channel.not_found')], Response::HTTP_NOT_FOUND);
        }

        if (!$mc->isActive()) {
            return $this->json(['error' => $this->translator->trans('admin.merchant_channel.not_active')], Response::HTTP_BAD_REQUEST);
        }

        $mc->suspend($dto->reason);
        $this->merchantChannelRepository->save($mc, true);

        return $this->json([
            'message' => $this->translator->trans('admin.merchant_channel.suspended'),
            'merchantChannel' => $this->serializeMerchantChannel($mc),
        ]);
    }

    #[Route('/{id}/enable', name: 'admin_merchant_channel_enable', methods: ['POST'])]
    public function enable(string $id): JsonResponse
    {
        $mc = $this->merchantChannelRepository->find($id);
        if (!$mc) {
            return $this->json(['error' => $this->translator->trans('admin.merchant_channel.not_found')], Response::HTTP_NOT_FOUND);
        }

        $mc->enable();
        $this->merchantChannelRepository->save($mc, true);

        return $this->json([
            'message' => $this->translator->trans('admin.merchant_channel.enabled'),
            'merchantChannel' => $this->serializeMerchantChannel($mc),
        ]);
    }

    private function serializeMerchantChannel(MerchantSalesChannel $mc, bool $detail = false): array
    {
        $merchant = $mc->getMerchant();
        $channel = $mc->getSalesChannel();

        $data = [
            'id' => $mc->getId(),
            'status' => $mc->getStatus(),
            'remark' => $mc->getRemark(),
            'approvedAt' => $mc->getApprovedAt()?->format('c'),
            'approvedBy' => $mc->getApprovedBy(),
            'createdAt' => $mc->getCreatedAt()->format('c'),
            'updatedAt' => $mc->getUpdatedAt()->format('c'),
            'merchant' => [
                'id' => $merchant->getId(),
                'name' => $merchant->getName(),
            ],
            'salesChannel' => [
                'id' => $channel->getId(),
                'code' => $channel->getCode(),
                'name' => $channel->getName(),
                'logoUrl' => $channel->getLogoUrl(),
            ],
        ];

        if ($detail) {
            $data['config'] = $mc->getConfig();
            $data['merchant']['contactName'] = $merchant->getContactName();
            $data['merchant']['contactPhone'] = $merchant->getContactPhone();
            $data['salesChannel']['configSchema'] = $channel->getConfigSchema();
        }

        return $data;
    }
}
