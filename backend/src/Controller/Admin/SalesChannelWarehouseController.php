<?php

namespace App\Controller\Admin;

use App\Attribute\AdminOnly;
use App\Dto\Admin\AddChannelWarehouseRequest;
use App\Dto\Admin\BatchAddChannelWarehousesRequest;
use App\Dto\Admin\UpdateChannelWarehouseRequest;
use App\Dto\Admin\UpdatePrioritiesRequest;
use App\Entity\SalesChannelWarehouse;
use App\Repository\SalesChannelRepository;
use App\Repository\SalesChannelWarehouseRepository;
use App\Repository\WarehouseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/admin/sales-channels/{channelId}/warehouses')]
#[AdminOnly]
class SalesChannelWarehouseController extends AbstractController
{
    public function __construct(
        private SalesChannelRepository $salesChannelRepository,
        private WarehouseRepository $warehouseRepository,
        private SalesChannelWarehouseRepository $channelWarehouseRepository,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'admin_channel_warehouse_list', methods: ['GET'])]
    public function list(string $channelId): JsonResponse
    {
        $channel = $this->salesChannelRepository->find($channelId);
        if (!$channel) {
            return $this->json(
                ['error' => $this->translator->trans('admin.channel.not_found')],
                Response::HTTP_NOT_FOUND
            );
        }

        $channelWarehouses = $this->channelWarehouseRepository->findByChannel($channel, false);

        return $this->json([
            'data' => array_map(
                fn (SalesChannelWarehouse $scw) => $this->serializeChannelWarehouse($scw),
                $channelWarehouses
            ),
        ]);
    }

    #[Route('/available', name: 'admin_channel_warehouse_available', methods: ['GET'])]
    public function available(string $channelId): JsonResponse
    {
        $channel = $this->salesChannelRepository->find($channelId);
        if (!$channel) {
            return $this->json(
                ['error' => $this->translator->trans('admin.channel.not_found')],
                Response::HTTP_NOT_FOUND
            );
        }

        // Get all active warehouses
        $allWarehouses = $this->warehouseRepository->findBy(['status' => 'active'], ['name' => 'ASC']);

        // Get configured warehouse IDs for this channel
        $configuredWarehouses = $this->channelWarehouseRepository->findByChannel($channel, false);
        $configuredIds = array_map(fn ($scw) => $scw->getWarehouse()->getId(), $configuredWarehouses);

        // Filter out configured warehouses
        $availableWarehouses = array_filter(
            $allWarehouses,
            fn ($w) => !in_array($w->getId(), $configuredIds, true)
        );

        return $this->json([
            'data' => array_map(
                fn ($w) => [
                    'id' => $w->getId(),
                    'code' => $w->getCode(),
                    'name' => $w->getName(),
                    'type' => $w->getType(),
                    'countryCode' => $w->getCountryCode(),
                    'status' => $w->getStatus(),
                ],
                array_values($availableWarehouses)
            ),
        ]);
    }

    #[Route('', name: 'admin_channel_warehouse_add', methods: ['POST'])]
    public function add(string $channelId, #[MapRequestPayload] AddChannelWarehouseRequest $dto): JsonResponse
    {
        $channel = $this->salesChannelRepository->find($channelId);
        if (!$channel) {
            return $this->json(
                ['error' => $this->translator->trans('admin.channel.not_found')],
                Response::HTTP_NOT_FOUND
            );
        }

        $warehouse = $this->warehouseRepository->find($dto->warehouseId);
        if (!$warehouse) {
            return $this->json(
                ['error' => $this->translator->trans('admin.warehouse.not_found')],
                Response::HTTP_NOT_FOUND
            );
        }

        // Check if already configured
        $existing = $this->channelWarehouseRepository->findOneByChannelAndWarehouse($channel, $warehouse);
        if ($existing) {
            return $this->json(
                ['error' => $this->translator->trans('admin.channel_warehouse.already_exists')],
                Response::HTTP_CONFLICT
            );
        }

        $channelWarehouse = new SalesChannelWarehouse();
        $channelWarehouse->setSalesChannel($channel);
        $channelWarehouse->setWarehouse($warehouse);
        $channelWarehouse->setPriority($dto->priority ?? $this->channelWarehouseRepository->getNextPriority($channel));

        if ($dto->remark !== null) {
            $channelWarehouse->setRemark($dto->remark);
        }

        $this->channelWarehouseRepository->save($channelWarehouse, true);

        return $this->json([
            'message' => $this->translator->trans('admin.channel_warehouse.added'),
            'data' => $this->serializeChannelWarehouse($channelWarehouse),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'admin_channel_warehouse_update', methods: ['PUT'])]
    public function update(
        string $channelId,
        string $id,
        #[MapRequestPayload] UpdateChannelWarehouseRequest $dto
    ): JsonResponse {
        $channel = $this->salesChannelRepository->find($channelId);
        if (!$channel) {
            return $this->json(
                ['error' => $this->translator->trans('admin.channel.not_found')],
                Response::HTTP_NOT_FOUND
            );
        }

        $channelWarehouse = $this->channelWarehouseRepository->find($id);
        if (!$channelWarehouse || $channelWarehouse->getSalesChannel()->getId() !== $channelId) {
            return $this->json(
                ['error' => $this->translator->trans('admin.channel_warehouse.not_found')],
                Response::HTTP_NOT_FOUND
            );
        }

        if ($dto->priority !== null) {
            $channelWarehouse->setPriority($dto->priority);
        }
        if ($dto->status !== null) {
            $channelWarehouse->setStatus($dto->status);
        }
        if ($dto->remark !== null) {
            $channelWarehouse->setRemark($dto->remark);
        }

        $this->channelWarehouseRepository->save($channelWarehouse, true);

        return $this->json([
            'message' => $this->translator->trans('admin.channel_warehouse.updated'),
            'data' => $this->serializeChannelWarehouse($channelWarehouse),
        ]);
    }

    #[Route('/{id}', name: 'admin_channel_warehouse_delete', methods: ['DELETE'])]
    public function delete(string $channelId, string $id): JsonResponse
    {
        $channel = $this->salesChannelRepository->find($channelId);
        if (!$channel) {
            return $this->json(
                ['error' => $this->translator->trans('admin.channel.not_found')],
                Response::HTTP_NOT_FOUND
            );
        }

        $channelWarehouse = $this->channelWarehouseRepository->find($id);
        if (!$channelWarehouse || $channelWarehouse->getSalesChannel()->getId() !== $channelId) {
            return $this->json(
                ['error' => $this->translator->trans('admin.channel_warehouse.not_found')],
                Response::HTTP_NOT_FOUND
            );
        }

        $this->channelWarehouseRepository->remove($channelWarehouse, true);

        return $this->json([
            'message' => $this->translator->trans('admin.channel_warehouse.removed'),
        ]);
    }

    #[Route('/batch', name: 'admin_channel_warehouse_batch_add', methods: ['POST'])]
    public function batchAdd(
        string $channelId,
        #[MapRequestPayload] BatchAddChannelWarehousesRequest $dto
    ): JsonResponse {
        $channel = $this->salesChannelRepository->find($channelId);
        if (!$channel) {
            return $this->json(
                ['error' => $this->translator->trans('admin.channel.not_found')],
                Response::HTTP_NOT_FOUND
            );
        }

        $added = [];
        $skipped = 0;
        $priority = $this->channelWarehouseRepository->getNextPriority($channel);

        foreach ($dto->warehouseIds as $warehouseId) {
            $warehouse = $this->warehouseRepository->find($warehouseId);
            if (!$warehouse) {
                ++$skipped;
                continue;
            }

            // Check if already configured
            $existing = $this->channelWarehouseRepository->findOneByChannelAndWarehouse($channel, $warehouse);
            if ($existing) {
                ++$skipped;
                continue;
            }

            $channelWarehouse = new SalesChannelWarehouse();
            $channelWarehouse->setSalesChannel($channel);
            $channelWarehouse->setWarehouse($warehouse);
            $channelWarehouse->setPriority($priority++);

            $this->channelWarehouseRepository->save($channelWarehouse, false);
            $added[] = $channelWarehouse;
        }

        if (!empty($added)) {
            $this->channelWarehouseRepository->save($added[0], true);
        }

        return $this->json([
            'message' => $this->translator->trans('admin.channel_warehouse.batch_added'),
            'added' => count($added),
            'skipped' => $skipped,
            'data' => array_map(
                fn (SalesChannelWarehouse $scw) => $this->serializeChannelWarehouse($scw),
                $added
            ),
        ], Response::HTTP_CREATED);
    }

    #[Route('/priorities', name: 'admin_channel_warehouse_update_priorities', methods: ['PUT'])]
    public function updatePriorities(
        string $channelId,
        #[MapRequestPayload] UpdatePrioritiesRequest $dto
    ): JsonResponse {
        $channel = $this->salesChannelRepository->find($channelId);
        if (!$channel) {
            return $this->json(
                ['error' => $this->translator->trans('admin.channel.not_found')],
                Response::HTTP_NOT_FOUND
            );
        }

        $updated = [];
        foreach ($dto->items as $item) {
            $channelWarehouse = $this->channelWarehouseRepository->find($item['id']);
            if (!$channelWarehouse || $channelWarehouse->getSalesChannel()->getId() !== $channelId) {
                continue;
            }

            $channelWarehouse->setPriority($item['priority']);
            $this->channelWarehouseRepository->save($channelWarehouse, false);
            $updated[] = $channelWarehouse;
        }

        if (!empty($updated)) {
            $this->channelWarehouseRepository->save($updated[0], true);
        }

        return $this->json([
            'message' => $this->translator->trans('admin.channel_warehouse.priorities_updated'),
            'data' => array_map(
                fn (SalesChannelWarehouse $scw) => $this->serializeChannelWarehouse($scw),
                $updated
            ),
        ]);
    }

    private function serializeChannelWarehouse(SalesChannelWarehouse $scw): array
    {
        $warehouse = $scw->getWarehouse();

        return [
            'id' => $scw->getId(),
            'salesChannelId' => $scw->getSalesChannel()->getId(),
            'warehouseId' => $warehouse->getId(),
            'warehouse' => [
                'id' => $warehouse->getId(),
                'code' => $warehouse->getCode(),
                'name' => $warehouse->getName(),
                'type' => $warehouse->getType(),
                'countryCode' => $warehouse->getCountryCode(),
                'status' => $warehouse->getStatus(),
            ],
            'priority' => $scw->getPriority(),
            'status' => $scw->getStatus(),
            'remark' => $scw->getRemark(),
            'createdAt' => $scw->getCreatedAt()->format('c'),
            'updatedAt' => $scw->getUpdatedAt()->format('c'),
        ];
    }
}
