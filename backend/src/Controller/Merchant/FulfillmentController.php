<?php

declare(strict_types=1);

namespace App\Controller\Merchant;

use App\Dto\Merchant\Query\FulfillmentListQuery;
use App\Dto\Merchant\Request\RejectFulfillmentRequest;
use App\Dto\Merchant\Request\ShipFulfillmentRequest;
use App\Entity\Fulfillment;
use App\Entity\User;
use App\Repository\MerchantRepository;
use App\Service\Fulfillment\FulfillmentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * 商户履约单管理 - 商户接单、拒绝、发货.
 */
#[Route('/api/merchant/fulfillments')]
class FulfillmentController extends AbstractController
{
    public function __construct(
        private readonly MerchantRepository $merchantRepository,
        private readonly FulfillmentService $fulfillmentService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * 获取商户履约单列表.
     */
    #[Route('', name: 'merchant_fulfillment_list', methods: ['GET'])]
    public function list(
        #[CurrentUser] User $user,
        #[MapQueryString] FulfillmentListQuery $query = new FulfillmentListQuery()
    ): JsonResponse {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(
                ['error' => $this->translator->trans('merchant.not_found')],
                Response::HTTP_NOT_FOUND
            );
        }

        $result = $this->fulfillmentService->getMerchantFulfillments(
            $merchant,
            $query->getPage(),
            $query->getLimit(),
            $query->getStatus(),
            $query->getFulfillmentType()
        );

        return $this->json([
            'data' => array_map(fn (Fulfillment $f) => $this->serializeFulfillment($f), $result['data']),
            'total' => $result['total'],
            'page' => $query->getPage(),
            'limit' => $query->getLimit(),
        ]);
    }

    /**
     * 获取履约单详情.
     */
    #[Route('/{id}', name: 'merchant_fulfillment_detail', methods: ['GET'])]
    public function detail(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(
                ['error' => $this->translator->trans('merchant.not_found')],
                Response::HTTP_NOT_FOUND
            );
        }

        $fulfillment = $this->fulfillmentService->getMerchantFulfillment($id, $merchant);
        if (!$fulfillment) {
            return $this->json(
                ['error' => $this->translator->trans('fulfillment.not_found')],
                Response::HTTP_NOT_FOUND
            );
        }

        return $this->json(['data' => $this->serializeFulfillment($fulfillment)]);
    }

    /**
     * 商户接单.
     */
    #[Route('/{id}/accept', name: 'merchant_fulfillment_accept', methods: ['POST'])]
    public function accept(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(
                ['error' => $this->translator->trans('merchant.not_found')],
                Response::HTTP_NOT_FOUND
            );
        }

        $fulfillment = $this->fulfillmentService->getMerchantFulfillment($id, $merchant);
        if (!$fulfillment) {
            return $this->json(
                ['error' => $this->translator->trans('fulfillment.not_found')],
                Response::HTTP_NOT_FOUND
            );
        }

        try {
            $this->fulfillmentService->acceptFulfillment($fulfillment, $user);

            return $this->json([
                'message' => $this->translator->trans('fulfillment.accept_success'),
                'data' => $this->serializeFulfillment($fulfillment),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(
                ['error' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * 商户拒绝接单.
     */
    #[Route('/{id}/reject', name: 'merchant_fulfillment_reject', methods: ['POST'])]
    public function reject(
        string $id,
        #[CurrentUser] User $user,
        #[MapRequestPayload] RejectFulfillmentRequest $request
    ): JsonResponse {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(
                ['error' => $this->translator->trans('merchant.not_found')],
                Response::HTTP_NOT_FOUND
            );
        }

        $fulfillment = $this->fulfillmentService->getMerchantFulfillment($id, $merchant);
        if (!$fulfillment) {
            return $this->json(
                ['error' => $this->translator->trans('fulfillment.not_found')],
                Response::HTTP_NOT_FOUND
            );
        }

        try {
            $this->fulfillmentService->rejectFulfillment($fulfillment, $request->reason, $user);

            return $this->json([
                'message' => $this->translator->trans('fulfillment.reject_success'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(
                ['error' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * 商户发货.
     */
    #[Route('/{id}/ship', name: 'merchant_fulfillment_ship', methods: ['POST'])]
    public function ship(
        string $id,
        #[CurrentUser] User $user,
        #[MapRequestPayload] ShipFulfillmentRequest $request
    ): JsonResponse {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(
                ['error' => $this->translator->trans('merchant.not_found')],
                Response::HTTP_NOT_FOUND
            );
        }

        $fulfillment = $this->fulfillmentService->getMerchantFulfillment($id, $merchant);
        if (!$fulfillment) {
            return $this->json(
                ['error' => $this->translator->trans('fulfillment.not_found')],
                Response::HTTP_NOT_FOUND
            );
        }

        try {
            $this->fulfillmentService->shipFulfillment(
                $fulfillment,
                $request->carrier,
                $request->trackingNumber,
                $request->trackingUrl,
                $user
            );

            return $this->json([
                'message' => $this->translator->trans('fulfillment.ship_success'),
                'data' => $this->serializeFulfillment($fulfillment),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(
                ['error' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    private function serializeFulfillment(Fulfillment $fulfillment): array
    {
        $order = $fulfillment->getOrder();
        $merchant = $fulfillment->getMerchant();
        $items = $fulfillment->getItems();

        return [
            'id' => $fulfillment->getId(),
            'fulfillmentType' => $fulfillment->getFulfillmentType(),
            'status' => $fulfillment->getStatus(),
            'deadline' => $fulfillment->getDeadlineAt()?->format(\DateTimeInterface::ATOM),
            'carrier' => $fulfillment->getShippingCarrier(),
            'trackingNumber' => $fulfillment->getTrackingNumber(),
            'trackingUrl' => $fulfillment->getTrackingUrl(),
            'rejectionReason' => $fulfillment->getRejectionReason(),
            'isExpired' => $fulfillment->isExpired(),
            'order' => [
                'id' => $order->getId(),
                'orderNo' => $order->getOrderNo(),
                'totalAmount' => $order->getTotalAmount(),
                'status' => $order->getStatus(),
            ],
            'merchant' => $merchant ? [
                'id' => $merchant->getId(),
                'name' => $merchant->getName(),
            ] : null,
            'items' => array_map(fn ($item) => [
                'id' => $item->getId(),
                'skuCode' => $item->getOrderItem()->getProductSku()?->getProduct()->getStyleNumber().'-'.$item->getOrderItem()->getProductSku()?->getSkuName(),
                'productName' => $item->getOrderItem()->getProductSku()?->getProduct()->getName(),
                'quantity' => $item->getQuantity(),
                'unitPrice' => $item->getListPrice() ?? $item->getOrderItem()->getUnitPrice(),
            ], $items->toArray()),
            'createdAt' => $fulfillment->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $fulfillment->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
