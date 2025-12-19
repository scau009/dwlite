<?php

namespace App\Controller\Admin;

use App\Attribute\AdminOnly;
use App\Dto\Admin\CreateProductRequest;
use App\Dto\Admin\CreateProductSkuRequest;
use App\Dto\Admin\Query\ProductListQuery;
use App\Dto\Admin\UpdateProductRequest;
use App\Dto\Admin\UpdateProductSkuRequest;
use App\Dto\Admin\UpdateProductStatusRequest;
use App\Dto\Admin\UpdateStatusRequest;
use App\Entity\Product;
use App\Entity\ProductSku;
use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductSkuRepository;
use App\Repository\TagRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/admin/products')]
#[AdminOnly]
class ProductController extends AbstractController
{
    public function __construct(
        private ProductRepository $productRepository,
        private ProductSkuRepository $skuRepository,
        private BrandRepository $brandRepository,
        private CategoryRepository $categoryRepository,
        private TagRepository $tagRepository,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'admin_product_list', methods: ['GET'])]
    public function list(#[MapQueryString] ProductListQuery $query = new ProductListQuery()): JsonResponse
    {
        $result = $this->productRepository->findWithFilters(
            $query->toFilters(),
            $query->getPage(),
            $query->getLimit()
        );

        return $this->json([
            'data' => array_map(fn(Product $p) => $this->serializeProduct($p), $result['data']),
            'total' => $result['meta']['total'],
            'page' => $query->getPage(),
            'limit' => $query->getLimit(),
        ]);
    }

    #[Route('/{id}', name: 'admin_product_detail', methods: ['GET'])]
    public function detail(string $id): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if (!$product) {
            return $this->json(['error' => $this->translator->trans('admin.product.not_found')], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeProduct($product, true));
    }

    #[Route('', name: 'admin_product_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateProductRequest $dto): JsonResponse
    {
        // 生成或使用提供的 slug
        $slug = $dto->slug ?? $this->generateSlug($dto->name);

        // 检查 slug 是否已存在
        if ($this->productRepository->findBySlug($slug)) {
            return $this->json(['error' => $this->translator->trans('admin.product.slug_exists')], Response::HTTP_CONFLICT);
        }

        // 检查款号是否已存在
        if ($this->productRepository->findByStyleNumber($dto->styleNumber)) {
            return $this->json(['error' => $this->translator->trans('admin.product.style_number_exists')], Response::HTTP_CONFLICT);
        }

        $product = new Product();
        $product->setName($dto->name);
        $product->setSlug($slug);
        $product->setStyleNumber($dto->styleNumber);
        $product->setSeason($dto->season);
        $product->setStatus($dto->status);

        if ($dto->color !== null) {
            $product->setColor($dto->color);
        }
        if ($dto->description !== null) {
            $product->setDescription($dto->description);
        }
        if ($dto->brandId !== null) {
            $brand = $this->brandRepository->find($dto->brandId);
            if ($brand) {
                $product->setBrand($brand);
            }
        }
        if ($dto->categoryId !== null) {
            $category = $this->categoryRepository->find($dto->categoryId);
            if ($category) {
                $product->setCategory($category);
            }
        }
        if ($dto->tagIds !== null) {
            foreach ($dto->tagIds as $tagId) {
                $tag = $this->tagRepository->find($tagId);
                if ($tag) {
                    $product->addTag($tag);
                }
            }
        }

        $this->productRepository->save($product, true);

        return $this->json([
            'message' => $this->translator->trans('admin.product.created'),
            'product' => $this->serializeProduct($product, true),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'admin_product_update', methods: ['PUT'])]
    public function update(string $id, #[MapRequestPayload] UpdateProductRequest $dto): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if (!$product) {
            return $this->json(['error' => $this->translator->trans('admin.product.not_found')], Response::HTTP_NOT_FOUND);
        }

        if ($dto->name !== null) {
            $product->setName($dto->name);
        }
        if ($dto->slug !== null) {
            $existing = $this->productRepository->findBySlug($dto->slug);
            if ($existing && $existing->getId() !== $product->getId()) {
                return $this->json(['error' => $this->translator->trans('admin.product.slug_exists')], Response::HTTP_CONFLICT);
            }
            $product->setSlug($dto->slug);
        }
        if ($dto->styleNumber !== null) {
            $existing = $this->productRepository->findByStyleNumber($dto->styleNumber);
            if ($existing && $existing->getId() !== $product->getId()) {
                return $this->json(['error' => $this->translator->trans('admin.product.style_number_exists')], Response::HTTP_CONFLICT);
            }
            $product->setStyleNumber($dto->styleNumber);
        }
        if ($dto->season !== null) {
            $product->setSeason($dto->season);
        }
        if ($dto->color !== null) {
            $product->setColor($dto->color);
        }
        if ($dto->description !== null) {
            $product->setDescription($dto->description);
        }
        if ($dto->status !== null) {
            $product->setStatus($dto->status);
        }
        if ($dto->isActive !== null) {
            $product->setIsActive($dto->isActive);
        }
        if ($dto->brandId !== null) {
            $brand = $this->brandRepository->find($dto->brandId);
            $product->setBrand($brand);
        }
        if ($dto->categoryId !== null) {
            $category = $this->categoryRepository->find($dto->categoryId);
            $product->setCategory($category);
        }
        if ($dto->tagIds !== null) {
            $product->clearTags();
            foreach ($dto->tagIds as $tagId) {
                $tag = $this->tagRepository->find($tagId);
                if ($tag) {
                    $product->addTag($tag);
                }
            }
        }

        $this->productRepository->save($product, true);

        return $this->json([
            'message' => $this->translator->trans('admin.product.updated'),
            'product' => $this->serializeProduct($product, true),
        ]);
    }

    #[Route('/{id}', name: 'admin_product_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if (!$product) {
            return $this->json(['error' => $this->translator->trans('admin.product.not_found')], Response::HTTP_NOT_FOUND);
        }

        $this->productRepository->remove($product, true);

        return $this->json(['message' => $this->translator->trans('admin.product.deleted')]);
    }

    #[Route('/{id}/status', name: 'admin_product_status', methods: ['PUT'])]
    public function updateStatus(string $id, #[MapRequestPayload] UpdateProductStatusRequest $dto): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if (!$product) {
            return $this->json(['error' => $this->translator->trans('admin.product.not_found')], Response::HTTP_NOT_FOUND);
        }

        $product->setStatus($dto->status);
        $this->productRepository->save($product, true);

        return $this->json([
            'message' => $this->translator->trans('admin.product.status_updated'),
            'product' => $this->serializeProduct($product),
        ]);
    }

    // SKU endpoints

    #[Route('/{id}/skus', name: 'admin_product_sku_create', methods: ['POST'])]
    public function createSku(string $id, #[MapRequestPayload] CreateProductSkuRequest $dto): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if (!$product) {
            return $this->json(['error' => $this->translator->trans('admin.product.not_found')], Response::HTTP_NOT_FOUND);
        }

        // 检查 SKU 编码是否已存在
        if ($this->skuRepository->findBySkuCode($dto->skuCode)) {
            return $this->json(['error' => $this->translator->trans('admin.product.sku_code_exists')], Response::HTTP_CONFLICT);
        }

        $sku = new ProductSku();
        $sku->setProduct($product);
        $sku->setSkuCode($dto->skuCode);
        $sku->setPrice($dto->price);
        $sku->setIsActive($dto->isActive);
        $sku->setSortOrder($dto->sortOrder);

        if ($dto->colorCode !== null) {
            $sku->setColorCode($dto->colorCode);
        }
        if ($dto->sizeUnit !== null) {
            $sku->setSizeUnit($dto->sizeUnit);
        }
        if ($dto->sizeValue !== null) {
            $sku->setSizeValue($dto->sizeValue);
        }
        if ($dto->specInfo !== null) {
            $sku->setSpecInfo($dto->specInfo);
        }
        if ($dto->originalPrice !== null) {
            $sku->setOriginalPrice($dto->originalPrice);
        }
        if ($dto->costPrice !== null) {
            $sku->setCostPrice($dto->costPrice);
        }

        $this->skuRepository->save($sku, true);

        return $this->json([
            'message' => $this->translator->trans('admin.product.sku_created'),
            'sku' => $this->serializeSku($sku),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/skus/{skuId}', name: 'admin_product_sku_update', methods: ['PUT'])]
    public function updateSku(string $id, string $skuId, #[MapRequestPayload] UpdateProductSkuRequest $dto): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if (!$product) {
            return $this->json(['error' => $this->translator->trans('admin.product.not_found')], Response::HTTP_NOT_FOUND);
        }

        $sku = $this->skuRepository->find($skuId);
        if (!$sku || $sku->getProduct()->getId() !== $product->getId()) {
            return $this->json(['error' => $this->translator->trans('admin.product.sku_not_found')], Response::HTTP_NOT_FOUND);
        }

        if ($dto->colorCode !== null) {
            $sku->setColorCode($dto->colorCode);
        }
        if ($dto->sizeUnit !== null) {
            $sku->setSizeUnit($dto->sizeUnit);
        }
        if ($dto->sizeValue !== null) {
            $sku->setSizeValue($dto->sizeValue);
        }
        if ($dto->specInfo !== null) {
            $sku->setSpecInfo($dto->specInfo);
        }
        if ($dto->price !== null) {
            $sku->setPrice($dto->price);
        }
        if ($dto->originalPrice !== null) {
            $sku->setOriginalPrice($dto->originalPrice);
        }
        if ($dto->costPrice !== null) {
            $sku->setCostPrice($dto->costPrice);
        }
        if ($dto->isActive !== null) {
            $sku->setIsActive($dto->isActive);
        }
        if ($dto->sortOrder !== null) {
            $sku->setSortOrder($dto->sortOrder);
        }

        $this->skuRepository->save($sku, true);

        return $this->json([
            'message' => $this->translator->trans('admin.product.sku_updated'),
            'sku' => $this->serializeSku($sku),
        ]);
    }

    #[Route('/{id}/skus/{skuId}', name: 'admin_product_sku_delete', methods: ['DELETE'])]
    public function deleteSku(string $id, string $skuId): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if (!$product) {
            return $this->json(['error' => $this->translator->trans('admin.product.not_found')], Response::HTTP_NOT_FOUND);
        }

        $sku = $this->skuRepository->find($skuId);
        if (!$sku || $sku->getProduct()->getId() !== $product->getId()) {
            return $this->json(['error' => $this->translator->trans('admin.product.sku_not_found')], Response::HTTP_NOT_FOUND);
        }

        $this->skuRepository->remove($sku, true);

        return $this->json(['message' => $this->translator->trans('admin.product.sku_deleted')]);
    }

    #[Route('/{id}/skus/{skuId}/status', name: 'admin_product_sku_status', methods: ['PUT'])]
    public function updateSkuStatus(string $id, string $skuId, #[MapRequestPayload] UpdateStatusRequest $dto): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if (!$product) {
            return $this->json(['error' => $this->translator->trans('admin.product.not_found')], Response::HTTP_NOT_FOUND);
        }

        $sku = $this->skuRepository->find($skuId);
        if (!$sku || $sku->getProduct()->getId() !== $product->getId()) {
            return $this->json(['error' => $this->translator->trans('admin.product.sku_not_found')], Response::HTTP_NOT_FOUND);
        }

        $sku->setIsActive($dto->isActive);
        $this->skuRepository->save($sku, true);

        return $this->json([
            'message' => $dto->isActive
                ? $this->translator->trans('admin.product.sku_activated')
                : $this->translator->trans('admin.product.sku_deactivated'),
            'sku' => $this->serializeSku($sku),
        ]);
    }

    private function serializeProduct(Product $product, bool $detail = false): array
    {
        $priceRange = $product->getPriceRange();
        $primaryImage = $product->getPrimaryImage();

        $data = [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'slug' => $product->getSlug(),
            'styleNumber' => $product->getStyleNumber(),
            'season' => $product->getSeason(),
            'color' => $product->getColor(),
            'status' => $product->getStatus(),
            'isActive' => $product->isActive(),
            'brandId' => $product->getBrand()?->getId(),
            'brandName' => $product->getBrand()?->getName(),
            'categoryId' => $product->getCategory()?->getId(),
            'categoryName' => $product->getCategory()?->getName(),
            'skuCount' => $product->getSkuCount(),
            'priceRange' => $priceRange,
            'primaryImageUrl' => $primaryImage?->getThumbnailUrl() ?? $primaryImage?->getUrl(),
            'createdAt' => $product->getCreatedAt()->format('c'),
            'updatedAt' => $product->getUpdatedAt()->format('c'),
        ];

        if ($detail) {
            $data['description'] = $product->getDescription();
            $data['tags'] = array_map(
                fn($t) => ['id' => $t->getId(), 'name' => $t->getName()],
                $product->getTags()->toArray()
            );
            $data['skus'] = array_map(
                fn($s) => $this->serializeSku($s),
                $product->getSkus()->toArray()
            );
            $data['images'] = array_map(
                fn($i) => [
                    'id' => $i->getId(),
                    'url' => $i->getUrl(),
                    'thumbnailUrl' => $i->getThumbnailUrl(),
                    'isPrimary' => $i->isPrimary(),
                    'sortOrder' => $i->getSortOrder(),
                ],
                $product->getImages()->toArray()
            );
        }

        return $data;
    }

    private function serializeSku(ProductSku $sku): array
    {
        return [
            'id' => $sku->getId(),
            'skuCode' => $sku->getSkuCode(),
            'colorCode' => $sku->getColorCode(),
            'sizeUnit' => $sku->getSizeUnit(),
            'sizeValue' => $sku->getSizeValue(),
            'specInfo' => $sku->getSpecInfo(),
            'specDescription' => $sku->getSpecDescription(),
            'price' => $sku->getPrice(),
            'originalPrice' => $sku->getOriginalPrice(),
            'costPrice' => $sku->getCostPrice(),
            'isActive' => $sku->isActive(),
            'sortOrder' => $sku->getSortOrder(),
            'createdAt' => $sku->getCreatedAt()->format('c'),
            'updatedAt' => $sku->getUpdatedAt()->format('c'),
        ];
    }

    private function generateSlug(string $name): string
    {
        $slugger = new AsciiSlugger();
        return strtolower($slugger->slug($name)->toString());
    }
}
