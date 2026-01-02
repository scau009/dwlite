<?php

namespace App\Controller\Admin;

use App\Attribute\AdminOnly;
use App\Dto\Admin\CreateSalesChannelRequest;
use App\Dto\Admin\Query\SalesChannelListQuery;
use App\Dto\Admin\UpdateSalesChannelRequest;
use App\Dto\Admin\UpdateSalesChannelStatusRequest;
use App\Entity\SalesChannel;
use App\Repository\SalesChannelRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/admin/sales-channels')]
#[AdminOnly]
class SalesChannelController extends AbstractController
{
    public function __construct(
        private SalesChannelRepository $salesChannelRepository,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'admin_sales_channel_list', methods: ['GET'])]
    public function list(#[MapQueryString] SalesChannelListQuery $query = new SalesChannelListQuery()): JsonResponse
    {
        $result = $this->salesChannelRepository->findPaginated(
            $query->getPage(),
            $query->getLimit(),
            $query->toFilters()
        );

        return $this->json([
            'data' => array_map(fn (SalesChannel $c) => $this->serializeChannel($c), $result['data']),
            'total' => $result['total'],
            'page' => $query->getPage(),
            'limit' => $query->getLimit(),
        ]);
    }

    #[Route('/{id}', name: 'admin_sales_channel_detail', methods: ['GET'])]
    public function detail(string $id): JsonResponse
    {
        $channel = $this->salesChannelRepository->find($id);
        if (!$channel) {
            return $this->json(['error' => $this->translator->trans('admin.channel.not_found')], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeChannel($channel, true));
    }

    #[Route('', name: 'admin_sales_channel_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateSalesChannelRequest $dto): JsonResponse
    {
        if ($this->salesChannelRepository->existsByCode($dto->code)) {
            return $this->json(['error' => $this->translator->trans('admin.channel.code_exists')], Response::HTTP_CONFLICT);
        }

        $channel = new SalesChannel();
        $channel->setCode($dto->code);
        $channel->setName($dto->name);
        $channel->setStatus($dto->status);

        if ($dto->logoUrl !== null) {
            $channel->setLogoUrl($dto->logoUrl);
        }
        if ($dto->description !== null) {
            $channel->setDescription($dto->description);
        }
        if ($dto->config !== null) {
            $channel->setConfig($dto->config);
        }
        if ($dto->configSchema !== null) {
            $channel->setConfigSchema($dto->configSchema);
        }
        if ($dto->sortOrder !== null) {
            $channel->setSortOrder($dto->sortOrder);
        }

        $this->salesChannelRepository->save($channel, true);

        return $this->json([
            'message' => $this->translator->trans('admin.channel.created'),
            'channel' => $this->serializeChannel($channel, true),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'admin_sales_channel_update', methods: ['PUT'])]
    public function update(string $id, #[MapRequestPayload] UpdateSalesChannelRequest $dto): JsonResponse
    {
        $channel = $this->salesChannelRepository->find($id);
        if (!$channel) {
            return $this->json(['error' => $this->translator->trans('admin.channel.not_found')], Response::HTTP_NOT_FOUND);
        }

        if ($dto->name !== null) {
            $channel->setName($dto->name);
        }
        if ($dto->logoUrl !== null) {
            $channel->setLogoUrl($dto->logoUrl);
        }
        if ($dto->description !== null) {
            $channel->setDescription($dto->description);
        }
        if ($dto->config !== null) {
            $channel->setConfig($dto->config);
        }
        if ($dto->configSchema !== null) {
            $channel->setConfigSchema($dto->configSchema);
        }
        if ($dto->sortOrder !== null) {
            $channel->setSortOrder($dto->sortOrder);
        }

        $this->salesChannelRepository->save($channel, true);

        return $this->json([
            'message' => $this->translator->trans('admin.channel.updated'),
            'channel' => $this->serializeChannel($channel, true),
        ]);
    }

    #[Route('/{id}', name: 'admin_sales_channel_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $channel = $this->salesChannelRepository->find($id);
        if (!$channel) {
            return $this->json(['error' => $this->translator->trans('admin.channel.not_found')], Response::HTTP_NOT_FOUND);
        }

        if ($channel->getMerchantChannels()->count() > 0) {
            return $this->json([
                'error' => $this->translator->trans('admin.channel.has_merchants'),
                'merchantCount' => $channel->getMerchantChannels()->count(),
            ], Response::HTTP_CONFLICT);
        }

        $this->salesChannelRepository->remove($channel, true);

        return $this->json(['message' => $this->translator->trans('admin.channel.deleted')]);
    }

    #[Route('/{id}/status', name: 'admin_sales_channel_status', methods: ['PUT'])]
    public function updateStatus(string $id, #[MapRequestPayload] UpdateSalesChannelStatusRequest $dto): JsonResponse
    {
        $channel = $this->salesChannelRepository->find($id);
        if (!$channel) {
            return $this->json(['error' => $this->translator->trans('admin.channel.not_found')], Response::HTTP_NOT_FOUND);
        }

        $channel->setStatus($dto->status);
        $this->salesChannelRepository->save($channel, true);

        return $this->json([
            'message' => $this->translator->trans('admin.channel.status_updated'),
            'channel' => $this->serializeChannel($channel),
        ]);
    }

    private function serializeChannel(SalesChannel $channel, bool $detail = false): array
    {
        $data = [
            'id' => $channel->getId(),
            'code' => $channel->getCode(),
            'name' => $channel->getName(),
            'logoUrl' => $channel->getLogoUrl(),
            'status' => $channel->getStatus(),
            'sortOrder' => $channel->getSortOrder(),
            'createdAt' => $channel->getCreatedAt()->format('c'),
            'updatedAt' => $channel->getUpdatedAt()->format('c'),
        ];

        if ($detail) {
            $data['description'] = $channel->getDescription();
            $data['config'] = $channel->getConfig();
            $data['configSchema'] = $channel->getConfigSchema();
            $data['merchantCount'] = $channel->getMerchantChannels()->count();
        }

        return $data;
    }
}
