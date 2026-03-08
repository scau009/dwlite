<?php

namespace App\Controller\Admin;

use App\Attribute\AdminOnly;
use App\Dto\Admin\Query\ChannelProductListQuery;
use App\Entity\ChannelProduct;
use App\Entity\ChannelProductSource;
use App\Entity\ChannelProductSyncLog;
use App\Enum\SyncTriggerSourceEnum;
use App\Message\PushChannelProductMessage;
use App\Repository\ChannelProductRepository;
use App\Repository\ChannelProductSyncLogRepository;
use App\Service\ChannelProductSyncService;
use App\Service\CosService;
use App\Service\Fulfillment\FulfillmentAllocationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/admin/channel-products')]
#[AdminOnly]
class ChannelProductController extends AbstractController
{
    public function __construct(
        private ChannelProductRepository $channelProductRepository,
        private ChannelProductSyncLogRepository $syncLogRepository,
        private ChannelProductSyncService $syncService,
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
        private CosService $cosService,
        private MessageBusInterface $messageBus,
    ) {}

    #[Route('', name: 'admin_channel_product_list', methods: ['GET'])]
    public function list(#[MapQueryString] ChannelProductListQuery $query = new ChannelProductListQuery()): JsonResponse
    {
        $result = $this->channelProductRepository->findPaginated(
            $query->getPage(),
            $query->getLimit(),
            $query->toFilters()
        );

        return $this->json([
            'data' => array_map(fn(ChannelProduct $cp) => $this->serializeChannelProduct($cp), $result['data']),
            'total' => $result['total'],
            'page' => $query->getPage(),
            'limit' => $query->getLimit(),
        ]);
    }

    #[Route('/{id}', name: 'admin_channel_product_detail', methods: ['GET'])]
    public function detail(string $id): JsonResponse
    {
        $channelProduct = $this->channelProductRepository->find($id);
        if (!$channelProduct) {
            return $this->json(['error' => $this->translator->trans('admin.channelProduct.notFound')], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['data' => $this->serializeChannelProduct($channelProduct, true)]);
    }

    #[Route('/{id}/activate', name: 'admin_channel_product_activate', methods: ['POST'])]
    public function activate(string $id): JsonResponse
    {
        $channelProduct = $this->channelProductRepository->find($id);
        if (!$channelProduct) {
            return $this->json(['error' => $this->translator->trans('admin.channelProduct.notFound')], Response::HTTP_NOT_FOUND);
        }

        if ($channelProduct->isActive()) {
            return $this->json(['error' => $this->translator->trans('admin.channelProduct.alreadyActive')], Response::HTTP_BAD_REQUEST);
        }

        $channelProduct->activate();
        $this->entityManager->flush();

        // Trigger sync to external channel
        $this->syncService->triggerSyncFromChannelProduct($channelProduct, SyncTriggerSourceEnum::MANUAL);

        return $this->json([
            'message' => $this->translator->trans('admin.channelProduct.activated'),
            'data' => $this->serializeChannelProduct($channelProduct),
        ]);
    }

    #[Route('/{id}/pause', name: 'admin_channel_product_pause', methods: ['POST'])]
    public function pause(string $id): JsonResponse
    {
        $channelProduct = $this->channelProductRepository->find($id);
        if (!$channelProduct) {
            return $this->json(['error' => $this->translator->trans('admin.channelProduct.notFound')], Response::HTTP_NOT_FOUND);
        }

        if ($channelProduct->isPaused()) {
            return $this->json(['error' => $this->translator->trans('admin.channelProduct.alreadyPaused')], Response::HTTP_BAD_REQUEST);
        }

        $channelProduct->pause();
        $this->entityManager->flush();

        // Trigger sync to external channel
        $this->syncService->triggerSyncFromChannelProduct($channelProduct, SyncTriggerSourceEnum::MANUAL);

        return $this->json([
            'message' => $this->translator->trans('admin.channelProduct.paused'),
            'data' => $this->serializeChannelProduct($channelProduct),
        ]);
    }

    #[Route('/{id}/delist', name: 'admin_channel_product_delist', methods: ['POST'])]
    public function delist(string $id): JsonResponse
    {
        $channelProduct = $this->channelProductRepository->find($id);
        if (!$channelProduct) {
            return $this->json(['error' => $this->translator->trans('admin.channelProduct.notFound')], Response::HTTP_NOT_FOUND);
        }

        if ($channelProduct->isDelisted()) {
            return $this->json(['error' => $this->translator->trans('admin.channelProduct.alreadyDelisted')], Response::HTTP_BAD_REQUEST);
        }

        // Only allow delist for active or paused products with externalId
        if (!$channelProduct->isActive() && !$channelProduct->isPaused()) {
            return $this->json(['error' => $this->translator->trans('admin.channelProduct.cannotDelistStatus')], Response::HTTP_BAD_REQUEST);
        }

        if (empty($channelProduct->getExternalId())) {
            return $this->json(['error' => $this->translator->trans('admin.channelProduct.noExternalId')], Response::HTTP_BAD_REQUEST);
        }

        // Update status to delisted
        $channelProduct->delist();
        $this->entityManager->flush();

        // Dispatch async message to call delist API on external channel
        $this->messageBus->dispatch(PushChannelProductMessage::delist($channelProduct->getId()));

        return $this->json([
            'message' => $this->translator->trans('admin.channelProduct.delisted'),
            'data' => $this->serializeChannelProduct($channelProduct),
        ]);
    }

    #[Route('/{id}/sync', name: 'admin_channel_product_sync', methods: ['POST'])]
    public function triggerSync(string $id): JsonResponse
    {
        $channelProduct = $this->channelProductRepository->find($id);
        if (!$channelProduct) {
            return $this->json(['error' => $this->translator->trans('admin.channelProduct.notFound')], Response::HTTP_NOT_FOUND);
        }

        // Sync all source statuses first (self-healing)
        $correctedCount = $this->syncService->syncAllSourceStatuses($channelProduct);

        // Recalculate stock and price
        $channelProduct->recalculateStock();
        $this->entityManager->flush();

        // Trigger sync to external channel
        $this->syncService->triggerSyncFromChannelProduct($channelProduct, SyncTriggerSourceEnum::MANUAL);

        return $this->json([
            'message' => $this->translator->trans('admin.channelProduct.syncTriggered'),
            'data' => $this->serializeChannelProduct($channelProduct),
            'correctedSources' => $correctedCount,
        ]);
    }

    #[Route('/{id}/sources', name: 'admin_channel_product_sources', methods: ['GET'])]
    public function getSources(string $id, FulfillmentAllocationService $allocationService): JsonResponse
    {
        $channelProduct = $this->channelProductRepository->find($id);
        if (!$channelProduct) {
            return $this->json(['error' => $this->translator->trans('admin.channelProduct.notFound')], Response::HTTP_NOT_FOUND);
        }

        // 使用分配服务按规则引擎评分排序
        $scoredSources = $allocationService->scoreSourcesForDisplay($channelProduct);

        $rank = 1;
        $data = array_map(function (array $scored) use (&$rank) {
            $result = $this->serializeSource($scored['source']);
            $result['displayScore'] = round($scored['score'], 2);
            $result['allocationRank'] = $rank++;

            return $result;
        }, $scoredSources);

        return $this->json([
            'data' => $data,
        ]);
    }

    #[Route('/{id}/sync-logs', name: 'admin_channel_product_sync_logs', methods: ['GET'])]
    public function getSyncLogs(string $id, Request $request): JsonResponse
    {
        $channelProduct = $this->channelProductRepository->find($id);
        if (!$channelProduct) {
            return $this->json(['error' => $this->translator->trans('admin.channelProduct.notFound')], Response::HTTP_NOT_FOUND);
        }

        $page = max(1, (int) $request->query->get('page', '1'));
        $limit = min(100, max(1, (int) $request->query->get('limit', '20')));
        $status = $request->query->get('status');
        $operation = $request->query->get('operation');

        $result = $this->syncLogRepository->findByChannelProductPaginated(
            $id,
            $page,
            $limit,
            $status,
            $operation
        );

        return $this->json([
            'data' => array_map(fn(ChannelProductSyncLog $log) => $this->serializeSyncLog($log), $result['data']),
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    private function serializeSyncLog(ChannelProductSyncLog $log): array
    {
        return [
            'id' => $log->getId(),
            'operation' => $log->getOperation(),
            'triggerSource' => $log->getTriggerSource(),
            'status' => $log->getStatus(),
            'errorCode' => $log->getErrorCode(),
            'errorMessage' => $log->getErrorMessage(),
            'beforeData' => $log->getBeforeData(),
            'afterData' => $log->getAfterData(),
            'durationMs' => $log->getDurationMs(),
            'startedAt' => $log->getStartedAt()->format('c'),
            'completedAt' => $log->getCompletedAt()?->format('c'),
            'createdAt' => $log->getCreatedAt()->format('c'),
        ];
    }

    private function serializeChannelProduct(ChannelProduct $cp, bool $detail = false): array
    {
        $sku = $cp->getProductSku();
        $product = $sku->getProduct();
        $channel = $cp->getSalesChannel();

        // Get signed image URL
        $imageUrl = null;
        $primaryImage = $product->getPrimaryImage();
        if ($primaryImage) {
            $imageUrl = $this->cosService->getSignedUrl(
                $primaryImage->getCosKey(),
                3600,
                'imageMogr2/thumbnail/120x120>'
            );
        }

        $data = [
            'id' => $cp->getId(),
            'salesChannel' => [
                'id' => $channel->getId(),
                'code' => $channel->getCode(),
                'name' => $channel->getName(),
                'currency' => $channel->getCurrency(),
            ],
            'productSku' => [
                'id' => $sku->getId(),
                'skuCode' => $product->getStyleNumber() . '-' . $sku->getSkuName(),
                'productName' => $product->getName(),
                'productId' => $product->getId(),
                'styleNumber' => $product->getStyleNumber(),
                'imageUrl' => $imageUrl,
                'sizeUnit' => $sku->getSizeUnit()?->value,
                'sizeValue' => $sku->getSizeValue(),
            ],
            'platformPrice' => $cp->getPlatformPrice(),
            'platformCompareAtPrice' => $cp->getPlatformCompareAtPrice(),
            'stockQuantity' => $cp->getStockQuantity(),
            'stockMode' => $cp->getStockMode(),
            'status' => $cp->getStatus(),
            'syncStatus' => $cp->getSyncStatus(),
            'externalId' => $cp->getExternalId(),
            'externalUrl' => $cp->getExternalUrl(),
            'lastSyncedAt' => $cp->getLastSyncedAt()?->format('c'),
            'syncError' => $cp->getSyncError(),
            'createdAt' => $cp->getCreatedAt()->format('c'),
            'updatedAt' => $cp->getUpdatedAt()->format('c'),
        ];

        if ($detail) {
            $data['safetyBuffer'] = $cp->getSafetyBuffer();
            $data['fixedStock'] = $cp->getFixedStock();
            $data['totalSoldQuantity'] = $cp->getTotalSoldQuantity();
            $data['sourcesCount'] = $cp->getSources()->count();
            $data['activeSourcesCount'] = $cp->getActiveSources()->count();
        }

        return $data;
    }

    private function serializeSource(ChannelProductSource $source): array
    {
        $listing = $source->getInventoryListing();
        $inventory = $listing->getMerchantInventory();
        $merchant = $inventory->getMerchant();
        $warehouse = $inventory->getWarehouse();

        return [
            'id' => $source->getId(),
            'priority' => $source->getPriority(),
            'isActive' => $source->isActive(),
            'soldQuantity' => $source->getSoldQuantity(),
            'remark' => $source->getRemark(),
            'listing' => [
                'id' => $listing->getId(),
                'price' => $listing->getPrice(),
                'compareAtPrice' => $listing->getCompareAtPrice(),
                'allocationMode' => $listing->getAllocationMode(),
                'allocatedQuantity' => $listing->getAllocatedQuantity(),
                'availableQuantity' => $listing->getAvailableQuantity(),
                'fulfillmentType' => $listing->getFulfillmentType(),
                'pricingModel' => $listing->getPricingModel(),
                'status' => $listing->getStatus(),
                'currency' => $source->getChannelProduct()->getSalesChannel()->getCurrency(),
            ],
            'merchant' => [
                'id' => $merchant->getId(),
                'name' => $merchant->getName(),
            ],
            'warehouse' => [
                'id' => $warehouse->getId(),
                'name' => $warehouse->getName(),
                'shortName' => $warehouse->getShortName(),
            ],
            'createdAt' => $source->getCreatedAt()->format('c'),
        ];
    }
}
