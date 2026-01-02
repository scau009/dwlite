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
use App\Entity\ProductImage;
use App\Entity\ProductSku;
use App\Enum\SizeUnit;
use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProductImageRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductSkuRepository;
use App\Repository\TagRepository;
use App\Service\CosService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
        private ProductImageRepository $imageRepository,
        private BrandRepository $brandRepository,
        private CategoryRepository $categoryRepository,
        private TagRepository $tagRepository,
        private CosService $cosService,
        private TranslatorInterface $translator, private readonly LoggerInterface $logger,
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
            'data' => array_map(fn (Product $p) => $this->serializeProduct($p), $result['data']),
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

        // Check for duplicate size
        if ($dto->sizeUnit !== null && $dto->sizeValue !== null) {
            $trimmedSizeValue = trim($dto->sizeValue);
            $existingSku = $this->skuRepository->findByProductAndSize(
                $product,
                SizeUnit::from($dto->sizeUnit),
                $trimmedSizeValue
            );
            if ($existingSku) {
                return $this->json([
                    'error' => $this->translator->trans('admin.product.sku_size_exists', [
                        '%size%' => $dto->sizeUnit.' '.$trimmedSizeValue,
                    ]),
                ], Response::HTTP_CONFLICT);
            }
        }

        $sku = new ProductSku();
        $sku->setProduct($product);
        $sku->setPrice($dto->price);
        $sku->setIsActive($dto->isActive);
        $sku->setSortOrder($dto->sortOrder);

        if ($dto->sizeUnit !== null) {
            $sku->setSizeUnit(SizeUnit::from($dto->sizeUnit));
        }
        if ($dto->sizeValue !== null) {
            $sku->setSizeValue(trim($dto->sizeValue));
        }
        if ($dto->originalPrice !== null) {
            $sku->setOriginalPrice($dto->originalPrice);
        }

        $this->skuRepository->save($sku, true);

        // Reorder all SKUs by size value
        $this->reorderSkusBySizeValue($product);

        return $this->json([
            'message' => $this->translator->trans('admin.product.sku_created'),
            'sku' => $this->serializeSku($sku),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/skus/batch', name: 'admin_product_sku_batch_create', methods: ['POST'])]
    public function batchCreateSkus(string $id, #[MapRequestPayload] \App\Dto\Admin\BatchCreateSkuRequest $dto): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if (!$product) {
            return $this->json(['error' => $this->translator->trans('admin.product.not_found')], Response::HTTP_NOT_FOUND);
        }

        $requestedUnit = SizeUnit::from($dto->sizeUnit);

        // Define popular size ranges for each unit (fixed arrays, not generated)
        $sizeRanges = [
            'US' => ['5.5', '6', '6.5', '7', '7.5', '8', '8.5', '9', '9.5', '10', '10.5', '11', '11.5', '12'],
            'EU' => ['38', '38.5', '39', '40', '40.5', '41', '42', '42.5', '43', '44', '44.5', '45', '45.5', '46'],
            'UK' => ['5', '5.5', '6', '6.5', '7', '7.5', '8', '8.5', '9', '9.5', '10', '10.5', '11'],
        ];

        $sizesToAdd = $sizeRanges[$dto->sizeUnit];

        // Get existing SKUs for this product
        $existingSkus = $product->getSkus();
        $existingSizeUnits = [];
        $existingSizeValues = [];

        foreach ($existingSkus as $sku) {
            if ($sku->getSizeUnit() !== null) {
                $existingSizeUnits[$sku->getSizeUnit()->value] = true;
            }
            if ($sku->getSizeUnit() === $requestedUnit && $sku->getSizeValue() !== null) {
                $existingSizeValues[$sku->getSizeValue()] = true;
            }
        }

        // Validation 1: Check if existing SKUs have a different size unit
        if (!empty($existingSizeUnits) && !isset($existingSizeUnits[$dto->sizeUnit])) {
            $existingUnitKeys = array_keys($existingSizeUnits);

            return $this->json([
                'error' => $this->translator->trans('admin.product.size_unit_mismatch', [
                    '%existing%' => implode(', ', $existingUnitKeys),
                    '%requested%' => $dto->sizeUnit,
                ]),
            ], Response::HTTP_CONFLICT);
        }

        // Filter out sizes that already exist
        $skippedSizes = [];
        $sizesToCreate = [];

        foreach ($sizesToAdd as $sizeValue) {
            if (isset($existingSizeValues[$sizeValue])) {
                $skippedSizes[] = $sizeValue;
            } else {
                $sizesToCreate[] = $sizeValue;
            }
        }

        // If all sizes already exist
        if (empty($sizesToCreate)) {
            return $this->json([
                'error' => $this->translator->trans('admin.product.all_sizes_exist'),
                'skippedSizes' => $skippedSizes,
            ], Response::HTTP_CONFLICT);
        }

        // Create SKUs
        $createdSkus = [];
        $maxSortOrder = 0;
        foreach ($existingSkus as $sku) {
            if ($sku->getSortOrder() > $maxSortOrder) {
                $maxSortOrder = $sku->getSortOrder();
            }
        }

        foreach ($sizesToCreate as $index => $sizeValue) {
            $sku = new ProductSku();
            $sku->setProduct($product);
            $sku->setSizeUnit($requestedUnit);
            $sku->setSizeValue($sizeValue);
            $sku->setPrice($dto->price);
            $sku->setIsActive(true);
            $sku->setSortOrder($maxSortOrder + $index + 1);

            if ($dto->originalPrice !== null) {
                $sku->setOriginalPrice($dto->originalPrice);
            }

            $this->skuRepository->save($sku);
            $createdSkus[] = $sku;
        }

        $this->skuRepository->save($createdSkus[0], true); // Flush all

        // Reorder all SKUs by size value
        $this->reorderSkusBySizeValue($product);

        // Reload SKUs to get updated sort order
        $allSkus = $this->skuRepository->findByProduct($product);

        return $this->json([
            'message' => $this->translator->trans('admin.product.skus_batch_created', [
                '%count%' => count($createdSkus),
            ]),
            'createdCount' => count($createdSkus),
            'skippedCount' => count($skippedSizes),
            'skippedSizes' => $skippedSizes,
            'skus' => array_map(fn ($s) => $this->serializeSku($s), $allSkus),
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

        if ($dto->sizeUnit !== null) {
            $sku->setSizeUnit(SizeUnit::from($dto->sizeUnit));
        }
        if ($dto->sizeValue !== null) {
            $sku->setSizeValue($dto->sizeValue);
        }
        if ($dto->price !== null) {
            $sku->setPrice($dto->price);
        }
        if ($dto->originalPrice !== null) {
            $sku->setOriginalPrice($dto->originalPrice);
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

    // Image endpoints

    #[Route('/{id}/images', name: 'admin_product_image_upload', methods: ['POST'])]
    public function uploadImage(string $id, Request $request): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if (!$product) {
            return $this->json(['error' => $this->translator->trans('admin.product.not_found')], Response::HTTP_NOT_FOUND);
        }

        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => $this->translator->trans('admin.product.image_required')], Response::HTTP_BAD_REQUEST);
        }

        // Validate file type
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            return $this->json(['error' => $this->translator->trans('admin.product.invalid_image_type')], Response::HTTP_BAD_REQUEST);
        }

        // Validate file size (max 5MB)
        if ($file->getSize() > 5 * 1024 * 1024) {
            return $this->json(['error' => $this->translator->trans('admin.product.image_too_large')], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Upload to COS
            $uploadResult = $this->cosService->uploadFile($file, 'products/'.$product->getId());

            // Check if this is the first image (make it primary)
            $isPrimary = $product->getImages()->isEmpty();

            // Create image entity
            $image = new ProductImage();
            $image->setProduct($product);
            $image->setCosKey($uploadResult['cosKey']);
            $image->setUrl($uploadResult['url']);
            $image->setThumbnailUrl($uploadResult['thumbnailUrl']);
            $image->setFileSize($uploadResult['fileSize']);
            $image->setWidth($uploadResult['width']);
            $image->setHeight($uploadResult['height']);
            $image->setIsPrimary($isPrimary);
            $image->setSortOrder($product->getImages()->count());

            $this->imageRepository->save($image, true);

            return $this->json([
                'message' => $this->translator->trans('admin.product.image_uploaded'),
                'image' => $this->serializeImage($image),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return $this->json(['error' => $this->translator->trans('admin.product.upload_failed')], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/images/{imageId}', name: 'admin_product_image_delete', methods: ['DELETE'])]
    public function deleteImage(string $id, string $imageId): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if (!$product) {
            return $this->json(['error' => $this->translator->trans('admin.product.not_found')], Response::HTTP_NOT_FOUND);
        }

        $image = $this->imageRepository->find($imageId);
        if (!$image || $image->getProduct()->getId() !== $product->getId()) {
            return $this->json(['error' => $this->translator->trans('admin.product.image_not_found')], Response::HTTP_NOT_FOUND);
        }

        // Delete from COS
        $this->cosService->deleteFile($image->getCosKey());

        // If this was primary, set another image as primary
        $wasPrimary = $image->isPrimary();
        $this->imageRepository->remove($image, true);

        if ($wasPrimary) {
            $remainingImages = $this->imageRepository->findByProduct($product);
            if (!empty($remainingImages)) {
                $remainingImages[0]->setIsPrimary(true);
                $this->imageRepository->save($remainingImages[0], true);
            }
        }

        return $this->json(['message' => $this->translator->trans('admin.product.image_deleted')]);
    }

    #[Route('/{id}/images/{imageId}/primary', name: 'admin_product_image_set_primary', methods: ['PUT'])]
    public function setImagePrimary(string $id, string $imageId): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if (!$product) {
            return $this->json(['error' => $this->translator->trans('admin.product.not_found')], Response::HTTP_NOT_FOUND);
        }

        $image = $this->imageRepository->find($imageId);
        if (!$image || $image->getProduct()->getId() !== $product->getId()) {
            return $this->json(['error' => $this->translator->trans('admin.product.image_not_found')], Response::HTTP_NOT_FOUND);
        }

        // Clear primary flag from all images
        $this->imageRepository->clearPrimaryForProduct($product->getId());

        // Set new primary
        $image->setIsPrimary(true);
        $this->imageRepository->save($image, true);

        return $this->json([
            'message' => $this->translator->trans('admin.product.image_primary_set'),
            'image' => $this->serializeImage($image),
        ]);
    }

    #[Route('/{id}/images/sort', name: 'admin_product_image_sort', methods: ['PUT'])]
    public function sortImages(string $id, Request $request): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if (!$product) {
            return $this->json(['error' => $this->translator->trans('admin.product.not_found')], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $imageIds = $data['imageIds'] ?? [];

        if (empty($imageIds)) {
            return $this->json(['error' => $this->translator->trans('admin.product.image_ids_required')], Response::HTTP_BAD_REQUEST);
        }

        foreach ($imageIds as $index => $imageId) {
            $image = $this->imageRepository->find($imageId);
            if ($image && $image->getProduct()->getId() === $product->getId()) {
                $image->setSortOrder($index);
                $this->imageRepository->save($image);
            }
        }
        $this->imageRepository->save($product->getImages()->first(), true); // Flush

        return $this->json(['message' => $this->translator->trans('admin.product.images_sorted')]);
    }

    private function serializeImage(ProductImage $image): array
    {
        $cosKey = $image->getCosKey();

        return [
            'id' => $image->getId(),
            'url' => $this->cosService->getSignedUrl($cosKey),
            'thumbnailUrl' => $this->cosService->getSignedUrl($cosKey, 3600, 'imageMogr2/thumbnail/300x300>'),
            'cosKey' => $cosKey,
            'isPrimary' => $image->isPrimary(),
            'sortOrder' => $image->getSortOrder(),
            'fileSize' => $image->getFileSize(),
            'width' => $image->getWidth(),
            'height' => $image->getHeight(),
            'dimensions' => $image->getDimensions(),
            'humanFileSize' => $image->getHumanFileSize(),
            'createdAt' => $image->getCreatedAt()->format('c'),
        ];
    }

    private function serializeProduct(Product $product, bool $detail = false): array
    {
        $priceRange = $product->getPriceRange();
        $primaryImage = $product->getPrimaryImage();

        // Generate signed URL for primary image
        $primaryImageUrl = null;
        if ($primaryImage) {
            $cosKey = $primaryImage->getCosKey();
            $primaryImageUrl = $this->cosService->getSignedUrl($cosKey, 3600, 'imageMogr2/thumbnail/300x300>');
        }

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
            'primaryImageUrl' => $primaryImageUrl,
            'tags' => array_map(
                fn ($t) => ['id' => $t->getId(), 'name' => $t->getName()],
                $product->getTags()->toArray()
            ),
            'createdAt' => $product->getCreatedAt()->format('c'),
            'updatedAt' => $product->getUpdatedAt()->format('c'),
        ];

        if ($detail) {
            $data['description'] = $product->getDescription();
            $data['skus'] = array_map(
                fn ($s) => $this->serializeSku($s),
                $product->getSkus()->toArray()
            );

            // Sort images: primary first, then by sortOrder
            $images = $product->getImages()->toArray();
            usort($images, function ($a, $b) {
                if ($a->isPrimary() && !$b->isPrimary()) {
                    return -1;
                }
                if (!$a->isPrimary() && $b->isPrimary()) {
                    return 1;
                }

                return $a->getSortOrder() <=> $b->getSortOrder();
            });

            $data['images'] = array_map(
                fn ($i) => [
                    'id' => $i->getId(),
                    'url' => $this->cosService->getSignedUrl($i->getCosKey()),
                    'thumbnailUrl' => $this->cosService->getSignedUrl($i->getCosKey(), 3600, 'imageMogr2/thumbnail/300x300>'),
                    'isPrimary' => $i->isPrimary(),
                    'sortOrder' => $i->getSortOrder(),
                ],
                $images
            );
        }

        return $data;
    }

    private function serializeSku(ProductSku $sku): array
    {
        return [
            'id' => $sku->getId(),
            'sizeUnit' => $sku->getSizeUnit()?->value,
            'sizeValue' => $sku->getSizeValue(),
            'skuName' => $sku->getSkuName(),
            'price' => $sku->getPrice(),
            'originalPrice' => $sku->getOriginalPrice(),
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

    /**
     * Reorder all SKUs for a product by size value (smallest to largest).
     */
    private function reorderSkusBySizeValue(Product $product): void
    {
        $skus = $this->skuRepository->findByProduct($product);

        // Sort by sizeValue numerically
        usort($skus, function (ProductSku $a, ProductSku $b) {
            $aValue = $a->getSizeValue();
            $bValue = $b->getSizeValue();

            // Handle null values - put them at the end
            if ($aValue === null && $bValue === null) {
                return 0;
            }
            if ($aValue === null) {
                return 1;
            }
            if ($bValue === null) {
                return -1;
            }

            // Compare as floats for numeric size values
            return (float) $aValue <=> (float) $bValue;
        });

        // Update sort order
        foreach ($skus as $index => $sku) {
            $sku->setSortOrder($index);
            $this->skuRepository->save($sku);
        }

        // Flush all changes
        if (!empty($skus)) {
            $this->skuRepository->save($skus[0], true);
        }
    }
}
