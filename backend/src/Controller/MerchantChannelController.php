<?php

namespace App\Controller;

use App\Dto\Admin\Query\PaginationQuery;
use App\Dto\Merchant\ApplyChannelRequest;
use App\Entity\MerchantSalesChannel;
use App\Entity\SalesChannel;
use App\Entity\User;
use App\Repository\MerchantRepository;
use App\Repository\MerchantSalesChannelRepository;
use App\Repository\SalesChannelRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * 商户自助服务 - 销售渠道申请与管理.
 */
#[Route('/api/merchant')]
class MerchantChannelController extends AbstractController
{
    public function __construct(
        private MerchantRepository $merchantRepository,
        private SalesChannelRepository $salesChannelRepository,
        private MerchantSalesChannelRepository $merchantChannelRepository,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * 获取可申请的销售渠道列表.
     */
    #[Route('/sales-channels', name: 'merchant_available_channels', methods: ['GET'])]
    public function listAvailableChannels(#[CurrentUser] User $user): JsonResponse
    {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $channels = $this->salesChannelRepository->findAvailableForMerchant($merchant);

        return $this->json([
            'data' => array_map(fn (SalesChannel $c) => $this->serializeSalesChannel($c), $channels),
        ]);
    }

    /**
     * 获取我的渠道申请/连接列表.
     */
    #[Route('/my-channels', name: 'merchant_my_channels', methods: ['GET'])]
    public function listMyChannels(
        #[CurrentUser] User $user,
        #[MapQueryString] PaginationQuery $query = new PaginationQuery(),
        ?Request $request = null
    ): JsonResponse {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $filters = [];
        if ($request && $status = $request->query->get('status')) {
            $filters['status'] = $status;
        }

        $result = $this->merchantChannelRepository->findByMerchantPaginated(
            $merchant,
            $query->getPage(),
            $query->getLimit(),
            $filters
        );

        return $this->json([
            'data' => array_map(fn (MerchantSalesChannel $mc) => $this->serializeMerchantChannel($mc), $result['data']),
            'total' => $result['total'],
            'page' => $query->getPage(),
            'limit' => $query->getLimit(),
        ]);
    }

    /**
     * 申请销售渠道.
     */
    #[Route('/my-channels', name: 'merchant_apply_channel', methods: ['POST'])]
    public function applyChannel(
        #[CurrentUser] User $user,
        #[MapRequestPayload] ApplyChannelRequest $dto
    ): JsonResponse {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $channel = $this->salesChannelRepository->find($dto->salesChannelId);
        if (!$channel) {
            return $this->json(['error' => $this->translator->trans('merchant_channel.channel_not_found')], Response::HTTP_NOT_FOUND);
        }

        if (!$channel->isActive()) {
            return $this->json(['error' => $this->translator->trans('merchant_channel.channel_not_available')], Response::HTTP_BAD_REQUEST);
        }

        // 检查是否已申请
        $existing = $this->merchantChannelRepository->findByMerchantAndChannel($merchant, $channel);
        if ($existing) {
            return $this->json(['error' => $this->translator->trans('merchant_channel.already_applied')], Response::HTTP_CONFLICT);
        }

        $mc = new MerchantSalesChannel();
        $mc->setMerchant($merchant);
        $mc->setSalesChannel($channel);
        if ($dto->remark) {
            $mc->setRemark($dto->remark);
        }

        $this->merchantChannelRepository->save($mc, true);

        return $this->json([
            'message' => $this->translator->trans('merchant_channel.applied'),
            'merchantChannel' => $this->serializeMerchantChannel($mc),
        ], Response::HTTP_CREATED);
    }

    /**
     * 取消申请（pending）或停用渠道（active）.
     */
    #[Route('/my-channels/{id}', name: 'merchant_channel_cancel', methods: ['DELETE'])]
    public function cancelOrDisable(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $mc = $this->merchantChannelRepository->find($id);
        if (!$mc || $mc->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => $this->translator->trans('merchant_channel.not_found')], Response::HTTP_NOT_FOUND);
        }

        if ($mc->isPending()) {
            // 取消待审核的申请 - 直接删除记录
            $this->merchantChannelRepository->remove($mc, true);

            return $this->json([
                'message' => $this->translator->trans('merchant_channel.application_cancelled'),
            ]);
        }

        if ($mc->isActive()) {
            // 停用已启用的渠道
            $mc->disable();
            $this->merchantChannelRepository->save($mc, true);

            return $this->json([
                'message' => $this->translator->trans('merchant_channel.disabled'),
                'merchantChannel' => $this->serializeMerchantChannel($mc),
            ]);
        }

        return $this->json(['error' => $this->translator->trans('merchant_channel.cannot_cancel_or_disable')], Response::HTTP_BAD_REQUEST);
    }

    /**
     * 重新启用已停用的渠道.
     */
    #[Route('/my-channels/{id}/enable', name: 'merchant_channel_enable', methods: ['POST'])]
    public function enableChannel(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $mc = $this->merchantChannelRepository->find($id);
        if (!$mc || $mc->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => $this->translator->trans('merchant_channel.not_found')], Response::HTTP_NOT_FOUND);
        }

        if (!$mc->isDisabled()) {
            return $this->json(['error' => $this->translator->trans('merchant_channel.not_disabled')], Response::HTTP_BAD_REQUEST);
        }

        $mc->enable();
        $this->merchantChannelRepository->save($mc, true);

        return $this->json([
            'message' => $this->translator->trans('merchant_channel.enabled'),
            'merchantChannel' => $this->serializeMerchantChannel($mc),
        ]);
    }

    private function serializeSalesChannel(SalesChannel $channel): array
    {
        return [
            'id' => $channel->getId(),
            'code' => $channel->getCode(),
            'name' => $channel->getName(),
            'logoUrl' => $channel->getLogoUrl(),
            'description' => $channel->getDescription(),
            'businessType' => $channel->getBusinessType(),
        ];
    }

    private function serializeMerchantChannel(MerchantSalesChannel $mc): array
    {
        $channel = $mc->getSalesChannel();

        return [
            'id' => $mc->getId(),
            'status' => $mc->getStatus(),
            'remark' => $mc->getRemark(),
            'approvedAt' => $mc->getApprovedAt()?->format('c'),
            'createdAt' => $mc->getCreatedAt()->format('c'),
            'updatedAt' => $mc->getUpdatedAt()->format('c'),
            'salesChannel' => [
                'id' => $channel->getId(),
                'code' => $channel->getCode(),
                'name' => $channel->getName(),
                'logoUrl' => $channel->getLogoUrl(),
                'businessType' => $channel->getBusinessType(),
            ],
        ];
    }
}
