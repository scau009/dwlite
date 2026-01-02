<?php

namespace App\Controller\Admin;

use App\Attribute\AdminOnly;
use App\Dto\Admin\CreateCategoryRequest;
use App\Dto\Admin\Query\CategoryListQuery;
use App\Dto\Admin\UpdateCategoryRequest;
use App\Dto\Admin\UpdateStatusRequest;
use App\Entity\Category;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/admin/categories')]
#[AdminOnly]
class CategoryController extends AbstractController
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'admin_category_list', methods: ['GET'])]
    public function list(#[MapQueryString] CategoryListQuery $query = new CategoryListQuery()): JsonResponse
    {
        $result = $this->categoryRepository->findPaginated(
            $query->getPage(),
            $query->getLimit(),
            $query->toFilters()
        );

        return $this->json([
            'data' => array_map(fn (Category $c) => $this->serializeCategory($c), $result['data']),
            'total' => $result['total'],
            'page' => $query->getPage(),
            'limit' => $query->getLimit(),
        ]);
    }

    #[Route('/tree', name: 'admin_category_tree', methods: ['GET'])]
    public function tree(Request $request): JsonResponse
    {
        $activeOnly = filter_var($request->query->get('activeOnly', 'false'), FILTER_VALIDATE_BOOLEAN);
        $categories = $this->categoryRepository->findAllForTree($activeOnly);

        $tree = $this->buildTree($categories);

        return $this->json(['data' => $tree]);
    }

    #[Route('/{id}', name: 'admin_category_detail', methods: ['GET'])]
    public function detail(string $id): JsonResponse
    {
        $category = $this->categoryRepository->find($id);
        if (!$category) {
            return $this->json(['error' => $this->translator->trans('admin.category.not_found')], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeCategory($category, true));
    }

    #[Route('', name: 'admin_category_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateCategoryRequest $dto): JsonResponse
    {
        // 生成或使用提供的 slug
        $slug = $dto->slug ?? $this->generateSlug($dto->name);

        // 检查 slug 是否已存在
        if ($this->categoryRepository->existsBySlug($slug)) {
            return $this->json(['error' => $this->translator->trans('admin.category.slug_exists')], Response::HTTP_CONFLICT);
        }

        $category = new Category();
        $category->setName($dto->name);
        $category->setSlug($slug);

        // 处理父级分类
        if ($dto->parentId !== null) {
            $parent = $this->categoryRepository->find($dto->parentId);
            if (!$parent) {
                return $this->json(['error' => $this->translator->trans('admin.category.parent_not_found')], Response::HTTP_BAD_REQUEST);
            }
            // 检查层级深度（限制为3级）
            if ($parent->getLevel() >= 2) {
                return $this->json(['error' => $this->translator->trans('admin.category.max_depth')], Response::HTTP_BAD_REQUEST);
            }
            $category->setParent($parent);
        }

        if ($dto->description !== null) {
            $category->setDescription($dto->description);
        }
        if ($dto->sortOrder !== null) {
            $category->setSortOrder($dto->sortOrder);
        }
        if ($dto->isActive !== null) {
            $category->setIsActive($dto->isActive);
        }

        $this->categoryRepository->save($category, true);

        return $this->json([
            'message' => $this->translator->trans('admin.category.created'),
            'category' => $this->serializeCategory($category, true),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'admin_category_update', methods: ['PUT'])]
    public function update(string $id, #[MapRequestPayload] UpdateCategoryRequest $dto): JsonResponse
    {
        $category = $this->categoryRepository->find($id);
        if (!$category) {
            return $this->json(['error' => $this->translator->trans('admin.category.not_found')], Response::HTTP_NOT_FOUND);
        }

        if ($dto->name !== null) {
            $category->setName($dto->name);
        }
        if ($dto->slug !== null) {
            // 检查 slug 是否已被其他分类使用
            if ($this->categoryRepository->existsBySlug($dto->slug, $category->getId())) {
                return $this->json(['error' => $this->translator->trans('admin.category.slug_exists')], Response::HTTP_CONFLICT);
            }
            $category->setSlug($dto->slug);
        }
        if ($dto->parentId !== null) {
            // 不能设置自己为父级
            if ($dto->parentId === $category->getId()) {
                return $this->json(['error' => $this->translator->trans('admin.category.self_parent')], Response::HTTP_BAD_REQUEST);
            }
            $parent = $this->categoryRepository->find($dto->parentId);
            if (!$parent) {
                return $this->json(['error' => $this->translator->trans('admin.category.parent_not_found')], Response::HTTP_BAD_REQUEST);
            }
            // 检查是否会造成循环引用
            if ($this->wouldCreateCycle($category, $parent)) {
                return $this->json(['error' => $this->translator->trans('admin.category.descendant_parent')], Response::HTTP_BAD_REQUEST);
            }
            // 检查层级深度
            if ($parent->getLevel() >= 2) {
                return $this->json(['error' => $this->translator->trans('admin.category.max_depth')], Response::HTTP_BAD_REQUEST);
            }
            $category->setParent($parent);
        }
        if ($dto->description !== null) {
            $category->setDescription($dto->description);
        }
        if ($dto->sortOrder !== null) {
            $category->setSortOrder($dto->sortOrder);
        }
        if ($dto->isActive !== null) {
            $category->setIsActive($dto->isActive);
        }

        $this->categoryRepository->save($category, true);

        return $this->json([
            'message' => $this->translator->trans('admin.category.updated'),
            'category' => $this->serializeCategory($category, true),
        ]);
    }

    #[Route('/{id}', name: 'admin_category_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $category = $this->categoryRepository->find($id);
        if (!$category) {
            return $this->json(['error' => $this->translator->trans('admin.category.not_found')], Response::HTTP_NOT_FOUND);
        }

        // 检查是否有子分类
        if ($category->hasChildren()) {
            return $this->json([
                'error' => $this->translator->trans('admin.category.has_children'),
                'childCount' => $category->getChildren()->count(),
            ], Response::HTTP_CONFLICT);
        }

        // 检查是否有关联的商品
        if ($category->getProducts()->count() > 0) {
            return $this->json([
                'error' => $this->translator->trans('admin.category.has_products'),
                'productCount' => $category->getProducts()->count(),
            ], Response::HTTP_CONFLICT);
        }

        $this->categoryRepository->remove($category, true);

        return $this->json(['message' => $this->translator->trans('admin.category.deleted')]);
    }

    #[Route('/{id}/status', name: 'admin_category_status', methods: ['PUT'])]
    public function updateStatus(string $id, #[MapRequestPayload] UpdateStatusRequest $dto): JsonResponse
    {
        $category = $this->categoryRepository->find($id);
        if (!$category) {
            return $this->json(['error' => $this->translator->trans('admin.category.not_found')], Response::HTTP_NOT_FOUND);
        }

        $category->setIsActive($dto->isActive);
        $this->categoryRepository->save($category, true);

        return $this->json([
            'message' => $dto->isActive ? $this->translator->trans('admin.category.activated') : $this->translator->trans('admin.category.deactivated'),
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
     * 构建树形结构.
     *
     * @param Category[] $categories
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
     * 检查设置 parent 是否会造成循环引用.
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
