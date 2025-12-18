<?php

namespace App\Controller\Admin;

use App\Attribute\AdminOnly;
use App\Entity\Category;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/api/admin/categories')]
#[AdminOnly]
class CategoryController extends AbstractController
{
    public function __construct(
        private CategoryRepository $categoryRepository,
    ) {
    }

    #[Route('', name: 'admin_category_list', methods: ['GET'])]
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
        if ($request->query->has('parentId')) {
            $parentId = $request->query->get('parentId');
            $filters['parentId'] = $parentId === '' || $parentId === 'null' ? null : $parentId;
        }

        $result = $this->categoryRepository->findPaginated($page, $limit, $filters);

        return $this->json([
            'data' => array_map(fn(Category $c) => $this->serializeCategory($c), $result['data']),
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    #[Route('/tree', name: 'admin_category_tree', methods: ['GET'])]
    public function tree(Request $request): JsonResponse
    {
        $activeOnly = filter_var($request->query->get('activeOnly', false), FILTER_VALIDATE_BOOLEAN);
        $categories = $this->categoryRepository->findAllForTree($activeOnly);

        $tree = $this->buildTree($categories);

        return $this->json(['data' => $tree]);
    }

    #[Route('/{id}', name: 'admin_category_detail', methods: ['GET'])]
    public function detail(string $id): JsonResponse
    {
        $category = $this->categoryRepository->find($id);
        if (!$category) {
            return $this->json(['error' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeCategory($category, true));
    }

    #[Route('', name: 'admin_category_create', methods: ['POST'])]
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
        if ($this->categoryRepository->existsBySlug($slug)) {
            return $this->json(['error' => 'slug already exists'], Response::HTTP_CONFLICT);
        }

        $category = new Category();
        $category->setName($data['name']);
        $category->setSlug($slug);

        // 处理父级分类
        if (!empty($data['parentId'])) {
            $parent = $this->categoryRepository->find($data['parentId']);
            if (!$parent) {
                return $this->json(['error' => 'Parent category not found'], Response::HTTP_BAD_REQUEST);
            }
            // 检查层级深度（限制为3级）
            if ($parent->getLevel() >= 2) {
                return $this->json(['error' => 'Maximum category depth is 3 levels'], Response::HTTP_BAD_REQUEST);
            }
            $category->setParent($parent);
        }

        if (isset($data['description'])) {
            $category->setDescription($data['description']);
        }
        if (isset($data['sortOrder'])) {
            $category->setSortOrder((int) $data['sortOrder']);
        }
        if (isset($data['isActive'])) {
            $category->setIsActive((bool) $data['isActive']);
        }

        $this->categoryRepository->save($category, true);

        return $this->json([
            'message' => 'Category created successfully',
            'category' => $this->serializeCategory($category, true),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'admin_category_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $category = $this->categoryRepository->find($id);
        if (!$category) {
            return $this->json(['error' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $category->setName($data['name']);
        }
        if (isset($data['slug'])) {
            // 检查 slug 是否已被其他分类使用
            if ($this->categoryRepository->existsBySlug($data['slug'], $category->getId())) {
                return $this->json(['error' => 'slug already exists'], Response::HTTP_CONFLICT);
            }
            $category->setSlug($data['slug']);
        }
        if (array_key_exists('parentId', $data)) {
            if ($data['parentId'] === null) {
                $category->setParent(null);
            } else {
                // 不能设置自己为父级
                if ($data['parentId'] === $category->getId()) {
                    return $this->json(['error' => 'Cannot set self as parent'], Response::HTTP_BAD_REQUEST);
                }
                $parent = $this->categoryRepository->find($data['parentId']);
                if (!$parent) {
                    return $this->json(['error' => 'Parent category not found'], Response::HTTP_BAD_REQUEST);
                }
                // 检查是否会造成循环引用
                if ($this->wouldCreateCycle($category, $parent)) {
                    return $this->json(['error' => 'Cannot set a descendant as parent'], Response::HTTP_BAD_REQUEST);
                }
                // 检查层级深度
                if ($parent->getLevel() >= 2) {
                    return $this->json(['error' => 'Maximum category depth is 3 levels'], Response::HTTP_BAD_REQUEST);
                }
                $category->setParent($parent);
            }
        }
        if (array_key_exists('description', $data)) {
            $category->setDescription($data['description']);
        }
        if (isset($data['sortOrder'])) {
            $category->setSortOrder((int) $data['sortOrder']);
        }
        if (isset($data['isActive'])) {
            $category->setIsActive((bool) $data['isActive']);
        }

        $this->categoryRepository->save($category, true);

        return $this->json([
            'message' => 'Category updated successfully',
            'category' => $this->serializeCategory($category, true),
        ]);
    }

    #[Route('/{id}', name: 'admin_category_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $category = $this->categoryRepository->find($id);
        if (!$category) {
            return $this->json(['error' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        // 检查是否有子分类
        if ($category->hasChildren()) {
            return $this->json([
                'error' => 'Cannot delete category with child categories',
                'childCount' => $category->getChildren()->count(),
            ], Response::HTTP_CONFLICT);
        }

        // 检查是否有关联的商品
        if ($category->getProducts()->count() > 0) {
            return $this->json([
                'error' => 'Cannot delete category with associated products',
                'productCount' => $category->getProducts()->count(),
            ], Response::HTTP_CONFLICT);
        }

        $this->categoryRepository->remove($category, true);

        return $this->json(['message' => 'Category deleted successfully']);
    }

    #[Route('/{id}/status', name: 'admin_category_status', methods: ['PUT'])]
    public function updateStatus(string $id, Request $request): JsonResponse
    {
        $category = $this->categoryRepository->find($id);
        if (!$category) {
            return $this->json(['error' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $isActive = $data['isActive'] ?? null;

        if ($isActive === null) {
            return $this->json(['error' => 'isActive field is required'], Response::HTTP_BAD_REQUEST);
        }

        $category->setIsActive((bool) $isActive);
        $this->categoryRepository->save($category, true);

        return $this->json([
            'message' => $isActive ? 'Category activated' : 'Category deactivated',
            'category' => $this->serializeCategory($category),
        ]);
    }

    private function serializeCategory(Category $category, bool $detail = false): array
    {
        $data = [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'slug' => $category->getSlug(),
            'parentId' => $category->getParent()?->getId(),
            'parentName' => $category->getParent()?->getName(),
            'level' => $category->getLevel(),
            'sortOrder' => $category->getSortOrder(),
            'isActive' => $category->isActive(),
            'hasChildren' => $category->hasChildren(),
            'createdAt' => $category->getCreatedAt()->format('c'),
            'updatedAt' => $category->getUpdatedAt()->format('c'),
        ];

        if ($detail) {
            $data['description'] = $category->getDescription();
            $data['productCount'] = $category->getProducts()->count();
            $data['childCount'] = $category->getChildren()->count();
        }

        return $data;
    }

    private function serializeCategoryForTree(Category $category): array
    {
        return [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'slug' => $category->getSlug(),
            'level' => $category->getLevel(),
            'sortOrder' => $category->getSortOrder(),
            'isActive' => $category->isActive(),
        ];
    }

    /**
     * 构建树形结构
     *
     * @param Category[] $categories
     * @return array
     */
    private function buildTree(array $categories): array
    {
        $indexed = [];
        $tree = [];

        // 先索引所有分类
        foreach ($categories as $category) {
            $indexed[$category->getId()] = $this->serializeCategoryForTree($category);
            $indexed[$category->getId()]['children'] = [];
        }

        // 构建树
        foreach ($categories as $category) {
            $id = $category->getId();
            $parentId = $category->getParent()?->getId();

            if ($parentId === null) {
                $tree[] = &$indexed[$id];
            } else {
                if (isset($indexed[$parentId])) {
                    $indexed[$parentId]['children'][] = &$indexed[$id];
                }
            }
        }

        return $tree;
    }

    private function generateSlug(string $name): string
    {
        $slugger = new AsciiSlugger();
        return strtolower($slugger->slug($name)->toString());
    }

    /**
     * 检查设置 parent 是否会造成循环引用
     */
    private function wouldCreateCycle(Category $category, Category $newParent): bool
    {
        $current = $newParent;
        while ($current !== null) {
            if ($current->getId() === $category->getId()) {
                return true;
            }
            $current = $current->getParent();
        }
        return false;
    }
}