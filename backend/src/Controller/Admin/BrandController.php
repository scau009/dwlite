<?php

namespace App\Controller\Admin;

use App\Attribute\AdminOnly;
use App\Dto\Admin\CreateBrandRequest;
use App\Dto\Admin\Query\BrandListQuery;
use App\Dto\Admin\UpdateBrandRequest;
use App\Dto\Admin\UpdateStatusRequest;
use App\Entity\Brand;
use App\Repository\BrandRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/admin/brands')]
#[AdminOnly]
class BrandController extends AbstractController
{
    public function __construct(
        private BrandRepository $brandRepository,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'admin_brand_list', methods: ['GET'])]
    public function list(#[MapQueryString] BrandListQuery $query = new BrandListQuery()): JsonResponse
    {
        $result = $this->brandRepository->findPaginated(
            $query->getPage(),
            $query->getLimit(),
            $query->toFilters()
        );

        return $this->json([
            'data' => array_map(fn(Brand $b) => $this->serializeBrand($b), $result['data']),
            'total' => $result['total'],
            'page' => $query->getPage(),
            'limit' => $query->getLimit(),
        ]);
    }

    #[Route('/{id}', name: 'admin_brand_detail', methods: ['GET'])]
    public function detail(string $id): JsonResponse
    {
        $brand = $this->brandRepository->find($id);
        if (!$brand) {
            return $this->json(['error' => $this->translator->trans('admin.brand.not_found')], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeBrand($brand, true));
    }

    #[Route('', name: 'admin_brand_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateBrandRequest $dto): JsonResponse
    {
        // 生成或使用提供的 slug
        $slug = $dto->slug ?? $this->generateSlug($dto->name);

        // 检查 slug 是否已存在
        if ($this->brandRepository->existsBySlug($slug)) {
            return $this->json(['error' => $this->translator->trans('admin.brand.slug_exists')], Response::HTTP_CONFLICT);
        }

        $brand = new Brand();
        $brand->setName($dto->name);
        $brand->setSlug($slug);

        if ($dto->logoUrl !== null) {
            $brand->setLogoUrl($dto->logoUrl);
        }
        if ($dto->description !== null) {
            $brand->setDescription($dto->description);
        }
        if ($dto->sortOrder !== null) {
            $brand->setSortOrder($dto->sortOrder);
        }
        if ($dto->isActive !== null) {
            $brand->setIsActive($dto->isActive);
        }

        $this->brandRepository->save($brand, true);

        return $this->json([
            'message' => $this->translator->trans('admin.brand.created'),
            'brand' => $this->serializeBrand($brand, true),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'admin_brand_update', methods: ['PUT'])]
    public function update(string $id, #[MapRequestPayload] UpdateBrandRequest $dto): JsonResponse
    {
        $brand = $this->brandRepository->find($id);
        if (!$brand) {
            return $this->json(['error' => $this->translator->trans('admin.brand.not_found')], Response::HTTP_NOT_FOUND);
        }

        if ($dto->name !== null) {
            $brand->setName($dto->name);
        }
        if ($dto->slug !== null) {
            // 检查 slug 是否已被其他品牌使用
            if ($this->brandRepository->existsBySlug($dto->slug, $brand->getId())) {
                return $this->json(['error' => $this->translator->trans('admin.brand.slug_exists')], Response::HTTP_CONFLICT);
            }
            $brand->setSlug($dto->slug);
        }
        if ($dto->logoUrl !== null) {
            $brand->setLogoUrl($dto->logoUrl);
        }
        if ($dto->description !== null) {
            $brand->setDescription($dto->description);
        }
        if ($dto->sortOrder !== null) {
            $brand->setSortOrder($dto->sortOrder);
        }
        if ($dto->isActive !== null) {
            $brand->setIsActive($dto->isActive);
        }

        $this->brandRepository->save($brand, true);

        return $this->json([
            'message' => $this->translator->trans('admin.brand.updated'),
            'brand' => $this->serializeBrand($brand, true),
        ]);
    }

    #[Route('/{id}', name: 'admin_brand_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $brand = $this->brandRepository->find($id);
        if (!$brand) {
            return $this->json(['error' => $this->translator->trans('admin.brand.not_found')], Response::HTTP_NOT_FOUND);
        }

        // 检查是否有关联的商品
        if ($brand->getProducts()->count() > 0) {
            return $this->json([
                'error' => $this->translator->trans('admin.brand.has_products'),
                'productCount' => $brand->getProducts()->count(),
            ], Response::HTTP_CONFLICT);
        }

        $this->brandRepository->remove($brand, true);

        return $this->json(['message' => $this->translator->trans('admin.brand.deleted')]);
    }

    #[Route('/{id}/status', name: 'admin_brand_status', methods: ['PUT'])]
    public function updateStatus(string $id, #[MapRequestPayload] UpdateStatusRequest $dto): JsonResponse
    {
        $brand = $this->brandRepository->find($id);
        if (!$brand) {
            return $this->json(['error' => $this->translator->trans('admin.brand.not_found')], Response::HTTP_NOT_FOUND);
        }

        $brand->setIsActive($dto->isActive);
        $this->brandRepository->save($brand, true);

        return $this->json([
            'message' => $dto->isActive ? $this->translator->trans('admin.brand.activated') : $this->translator->trans('admin.brand.deactivated'),
            'brand' => $this->serializeBrand($brand),
        ]);
    }

    private function serializeBrand(Brand $brand, bool $detail = false): array
    {
        $data = [
            'id' => $brand->getId(),
            'name' => $brand->getName(),
            'slug' => $brand->getSlug(),
            'logoUrl' => $brand->getLogoUrl(),
            'sortOrder' => $brand->getSortOrder(),
            'isActive' => $brand->isActive(),
            'createdAt' => $brand->getCreatedAt()->format('c'),
            'updatedAt' => $brand->getUpdatedAt()->format('c'),
        ];

        if ($detail) {
            $data['description'] = $brand->getDescription();
            $data['productCount'] = $brand->getProducts()->count();
        }

        return $data;
    }

    private function generateSlug(string $name): string
    {
        $slugger = new AsciiSlugger();
        return strtolower($slugger->slug($name)->toString());
    }
}
