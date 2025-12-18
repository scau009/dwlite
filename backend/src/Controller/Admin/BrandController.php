<?php

namespace App\Controller\Admin;

use App\Attribute\AdminOnly;
use App\Entity\Brand;
use App\Repository\BrandRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/api/admin/brands')]
#[AdminOnly]
class BrandController extends AbstractController
{
    public function __construct(
        private BrandRepository $brandRepository,
    ) {
    }

    #[Route('', name: 'admin_brand_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));

        $filters = [];
        if ($name = $request->query->get('name')) {
            $filters['name'] = $name;
        }
        if ($request->query->has('isActive')) {
            $filters['isActive'] = filter_var($request->query->get('isActive'), FILTER_VALIDATE_BOOLEAN);
        }

        $result = $this->brandRepository->findPaginated($page, $limit, $filters);

        return $this->json([
            'data' => array_map(fn(Brand $b) => $this->serializeBrand($b), $result['data']),
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    #[Route('/{id}', name: 'admin_brand_detail', methods: ['GET'])]
    public function detail(string $id): JsonResponse
    {
        $brand = $this->brandRepository->find($id);
        if (!$brand) {
            return $this->json(['error' => 'Brand not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeBrand($brand, true));
    }

    #[Route('', name: 'admin_brand_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // 验证必填字段
        if (empty($data['name'])) {
            return $this->json(['error' => 'name is required'], Response::HTTP_BAD_REQUEST);
        }

        // 生成或使用提供的 slug
        $slug = $data['slug'] ?? $this->generateSlug($data['name']);

        // 检查 slug 是否已存在
        if ($this->brandRepository->existsBySlug($slug)) {
            return $this->json(['error' => 'slug already exists'], Response::HTTP_CONFLICT);
        }

        $brand = new Brand();
        $brand->setName($data['name']);
        $brand->setSlug($slug);

        if (isset($data['logoUrl'])) {
            $brand->setLogoUrl($data['logoUrl']);
        }
        if (isset($data['description'])) {
            $brand->setDescription($data['description']);
        }
        if (isset($data['sortOrder'])) {
            $brand->setSortOrder((int) $data['sortOrder']);
        }
        if (isset($data['isActive'])) {
            $brand->setIsActive((bool) $data['isActive']);
        }

        $this->brandRepository->save($brand, true);

        return $this->json([
            'message' => 'Brand created successfully',
            'brand' => $this->serializeBrand($brand, true),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'admin_brand_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $brand = $this->brandRepository->find($id);
        if (!$brand) {
            return $this->json(['error' => 'Brand not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $brand->setName($data['name']);
        }
        if (isset($data['slug'])) {
            // 检查 slug 是否已被其他品牌使用
            if ($this->brandRepository->existsBySlug($data['slug'], $brand->getId())) {
                return $this->json(['error' => 'slug already exists'], Response::HTTP_CONFLICT);
            }
            $brand->setSlug($data['slug']);
        }
        if (array_key_exists('logoUrl', $data)) {
            $brand->setLogoUrl($data['logoUrl']);
        }
        if (array_key_exists('description', $data)) {
            $brand->setDescription($data['description']);
        }
        if (isset($data['sortOrder'])) {
            $brand->setSortOrder((int) $data['sortOrder']);
        }
        if (isset($data['isActive'])) {
            $brand->setIsActive((bool) $data['isActive']);
        }

        $this->brandRepository->save($brand, true);

        return $this->json([
            'message' => 'Brand updated successfully',
            'brand' => $this->serializeBrand($brand, true),
        ]);
    }

    #[Route('/{id}', name: 'admin_brand_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $brand = $this->brandRepository->find($id);
        if (!$brand) {
            return $this->json(['error' => 'Brand not found'], Response::HTTP_NOT_FOUND);
        }

        // 检查是否有关联的商品
        if ($brand->getProducts()->count() > 0) {
            return $this->json([
                'error' => 'Cannot delete brand with associated products',
                'productCount' => $brand->getProducts()->count(),
            ], Response::HTTP_CONFLICT);
        }

        $this->brandRepository->remove($brand, true);

        return $this->json(['message' => 'Brand deleted successfully']);
    }

    #[Route('/{id}/status', name: 'admin_brand_status', methods: ['PUT'])]
    public function updateStatus(string $id, Request $request): JsonResponse
    {
        $brand = $this->brandRepository->find($id);
        if (!$brand) {
            return $this->json(['error' => 'Brand not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $isActive = $data['isActive'] ?? null;

        if ($isActive === null) {
            return $this->json(['error' => 'isActive field is required'], Response::HTTP_BAD_REQUEST);
        }

        $brand->setIsActive((bool) $isActive);
        $this->brandRepository->save($brand, true);

        return $this->json([
            'message' => $isActive ? 'Brand activated' : 'Brand deactivated',
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