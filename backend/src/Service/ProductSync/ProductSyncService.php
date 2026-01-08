<?php

namespace App\Service\ProductSync;

use App\Entity\Brand;
use App\Entity\Product;
use App\Entity\ProductExternalMapping;
use App\Entity\ProductImage;
use App\Entity\ProductSku;
use App\Entity\ProductSyncJob;
use App\Enum\SizeUnit;
use App\Repository\BrandRepository;
use App\Repository\ProductExternalMappingRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductSyncJobRepository;
use App\Service\CosService;
use App\Service\ProductSync\Dto\ExternalProductDto;
use App\Service\ProductSync\Dto\ExternalSkuDto;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Core service for synchronizing products from external providers.
 *
 * This service is provider-agnostic and works with any implementation
 * of ProductDataProviderInterface.
 */
class ProductSyncService
{
    private AsciiSlugger $slugger;

    public function __construct(
        private ProductRepository $productRepository,
        private ProductExternalMappingRepository $mappingRepository,
        private ProductSyncJobRepository $syncJobRepository,
        private BrandRepository $brandRepository,
        private EntityManagerInterface $entityManager,
        private CosService $cosService,
        private LoggerInterface $logger,
    ) {
        $this->slugger = new AsciiSlugger();
    }

    /**
     * Create a new sync job for a provider.
     */
    public function createSyncJob(string $provider): ProductSyncJob
    {
        $job = new ProductSyncJob($provider);
        $this->syncJobRepository->save($job, true);

        $this->logger->info('Created sync job', [
            'job_id' => $job->getId(),
            'provider' => $provider,
        ]);

        return $job;
    }

    /**
     * Start a sync job.
     */
    public function startJob(ProductSyncJob $job, int $totalPages, int $totalProducts): void
    {
        $job->start();
        $job->setTotalPages($totalPages);
        $job->setTotalProducts($totalProducts);
        $this->syncJobRepository->save($job, true);

        $this->logger->info('Started sync job', [
            'job_id' => $job->getId(),
            'total_pages' => $totalPages,
            'total_products' => $totalProducts,
        ]);
    }

    /**
     * Sync a single product from external data.
     *
     * @return string|null The product ID if synced, null if skipped
     */
    public function syncProduct(ProductSyncJob $job, ExternalProductDto $externalProduct): ?string
    {
        $provider = $job->getProvider();

        // Validate style ID
        if (!$externalProduct->hasValidStyleId()) {
            $job->incrementSkippedProducts();
            $this->logger->debug('Skipping product without valid styleId', [
                'external_id' => $externalProduct->externalId,
            ]);

            return null;
        }

        try {
            // Check if product is already mapped to this provider
            $mapping = $this->mappingRepository->findByProviderAndExternalId(
                $provider,
                $externalProduct->externalId
            );

            if ($mapping !== null) {
                // Update existing product
                $product = $mapping->getProduct();
                $this->updateProduct($product, $externalProduct);
                $mapping->updateLastSyncedAt();
                $mapping->setExternalData($externalProduct->rawData);
                $this->mappingRepository->save($mapping);

                $job->incrementUpdatedProducts();
                $job->incrementSyncedProducts();

                $this->logger->debug('Updated existing product', [
                    'product_id' => $product->getId(),
                    'external_id' => $externalProduct->externalId,
                ]);

                return $product->getId();
            }

            // Check if product exists by style number (not yet mapped)
            $product = $this->productRepository->findByStyleNumber($externalProduct->styleId);

            if ($product !== null) {
                // Product exists but not mapped - create mapping
                $this->updateProduct($product, $externalProduct);
                $this->createMapping($product, $provider, $externalProduct);

                $job->incrementUpdatedProducts();
                $job->incrementSyncedProducts();

                $this->logger->debug('Mapped existing product', [
                    'product_id' => $product->getId(),
                    'external_id' => $externalProduct->externalId,
                ]);

                return $product->getId();
            }

            // Create new product
            $product = $this->createProduct($externalProduct);
            $this->createMapping($product, $provider, $externalProduct);

            $job->incrementCreatedProducts();
            $job->incrementSyncedProducts();

            $this->logger->debug('Created new product', [
                'product_id' => $product->getId(),
                'external_id' => $externalProduct->externalId,
            ]);

            return $product->getId();
        } catch (\Exception $e) {
            $job->incrementFailedProducts();
            $this->logger->error('Failed to sync product', [
                'external_id' => $externalProduct->externalId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Mark a page as processed and check if job is complete.
     */
    public function markPageProcessed(ProductSyncJob $job): bool
    {
        $job->incrementProcessedPages();
        $this->syncJobRepository->save($job, true);

        if ($job->isAllPagesProcessed()) {
            $job->complete();
            $this->syncJobRepository->save($job, true);

            $this->logger->info('Sync job completed', [
                'job_id' => $job->getId(),
                'synced' => $job->getSyncedProducts(),
                'created' => $job->getCreatedProducts(),
                'updated' => $job->getUpdatedProducts(),
                'skipped' => $job->getSkippedProducts(),
                'failed' => $job->getFailedProducts(),
            ]);

            return true;
        }

        return false;
    }

    /**
     * Mark job as failed.
     */
    public function failJob(ProductSyncJob $job, string $errorMessage): void
    {
        $job->fail($errorMessage);
        $this->syncJobRepository->save($job, true);

        $this->logger->error('Sync job failed', [
            'job_id' => $job->getId(),
            'error' => $errorMessage,
        ]);
    }

    /**
     * Flush pending changes to database.
     */
    public function flush(): void
    {
        $this->entityManager->flush();
    }

    /**
     * Create a new product from external data.
     */
    private function createProduct(ExternalProductDto $externalProduct): Product
    {
        $product = new Product();
        $product->setStyleNumber($externalProduct->styleId);
        $product->setName($externalProduct->title);
        $product->setSlug($this->generateUniqueSlug($externalProduct->title));
        $product->setColor($externalProduct->color ?? '');
        $product->setDescription($externalProduct->description);
        $product->setStatus('draft'); // New products start as draft
        $product->setIsActive(false); // Require admin review

        // Set brand
        if ($externalProduct->brand !== null) {
            $brand = $this->getOrCreateBrand($externalProduct->brand);
            $product->setBrand($brand);
        }

        $this->productRepository->save($product);

        // Sync image
        $this->syncProductImage($product, $externalProduct->imageUrl);

        // Sync SKUs
        $this->syncProductSkus($product, $externalProduct->skus, $externalProduct->currency);

        $this->entityManager->flush();

        return $product;
    }

    /**
     * Update an existing product with external data.
     */
    private function updateProduct(Product $product, ExternalProductDto $externalProduct): void
    {
        // Only update fields that are empty or explicitly managed
        if (empty($product->getDescription()) && $externalProduct->description !== null) {
            $product->setDescription($externalProduct->description);
        }

        if (empty($product->getColor()) && $externalProduct->color !== null) {
            $product->setColor($externalProduct->color);
        }

        // Set brand if not already set
        if ($product->getBrand() === null && $externalProduct->brand !== null) {
            $brand = $this->getOrCreateBrand($externalProduct->brand);
            $product->setBrand($brand);
        }

        $this->productRepository->save($product);

        // Sync SKUs (add new sizes, don't remove existing)
        $this->syncProductSkus($product, $externalProduct->skus, $externalProduct->currency);
    }

    /**
     * Create mapping between product and external source.
     */
    private function createMapping(Product $product, string $provider, ExternalProductDto $externalProduct): ProductExternalMapping
    {
        $mapping = new ProductExternalMapping($product, $provider, $externalProduct->externalId);
        $mapping->setExternalStyleId($externalProduct->styleId);
        $mapping->setExternalUrl($externalProduct->externalUrl);
        $mapping->setExternalData($externalProduct->rawData);

        $this->mappingRepository->save($mapping);

        return $mapping;
    }

    /**
     * Get or create a brand by name.
     */
    private function getOrCreateBrand(string $brandName): Brand
    {
        $slug = strtolower($this->slugger->slug($brandName)->toString());
        $brand = $this->brandRepository->findBySlug($slug);

        if ($brand !== null) {
            return $brand;
        }

        // Create new brand (inactive for review)
        $brand = new Brand();
        $brand->setName($brandName);
        $brand->setSlug($slug);
        $brand->setIsActive(false);

        $this->brandRepository->save($brand);

        $this->logger->info('Created new brand from sync', [
            'name' => $brandName,
            'slug' => $slug,
        ]);

        return $brand;
    }

    /**
     * Sync product image from external URL.
     */
    private function syncProductImage(Product $product, ?string $imageUrl): void
    {
        if ($imageUrl === null || !$product->getImages()->isEmpty()) {
            return; // Skip if no image URL or product already has images
        }

        // Download and upload to COS
        $result = $this->cosService->uploadFromUrl($imageUrl, 'products/images');

        if ($result === null) {
            $this->logger->warning('Failed to sync product image', [
                'product_id' => $product->getId(),
                'image_url' => $imageUrl,
            ]);

            return;
        }

        $image = new ProductImage();
        $image->setProduct($product);
        $image->setCosKey($result['cosKey']);
        $image->setUrl($result['url']);
        $image->setThumbnailUrl($result['thumbnailUrl']);
        $image->setFileSize($result['fileSize']);
        $image->setWidth($result['width']);
        $image->setHeight($result['height']);
        $image->setIsPrimary(true);
        $image->setSortOrder(0);

        $product->addImage($image);
        $this->entityManager->persist($image);
    }

    /**
     * Sync product SKUs from external data.
     *
     * @param ExternalSkuDto[] $skus
     */
    private function syncProductSkus(Product $product, array $skus, string $currency): void
    {
        $existingSizes = [];
        foreach ($product->getSkus() as $sku) {
            $key = $sku->getSizeUnit()?->value.':'.$sku->getSizeValue();
            $existingSizes[$key] = true;
        }

        $sortOrder = $product->getSkus()->count();

        foreach ($skus as $externalSku) {
            if (empty($externalSku->sizeValue)) {
                continue;
            }

            // Map size unit
            $sizeUnit = $this->mapSizeUnit($externalSku->sizeUnit);
            $key = ($sizeUnit?->value ?? '').':'.$externalSku->sizeValue;

            // Skip if size already exists
            if (isset($existingSizes[$key])) {
                continue;
            }

            $sku = new ProductSku();
            $sku->setProduct($product);
            $sku->setSizeUnit($sizeUnit);
            $sku->setSizeValue($externalSku->sizeValue);
            $sku->setPrice($externalSku->price ?? '0');
            $sku->setOriginalPrice($externalSku->originalPrice);
            $sku->setCurrency($currency);
            $sku->setBarcode($externalSku->barcode);
            $sku->setIsActive(true);
            $sku->setSortOrder($sortOrder++);

            $product->addSku($sku);
            $this->entityManager->persist($sku);
        }
    }

    /**
     * Generate a unique slug for a product.
     */
    private function generateUniqueSlug(string $title): string
    {
        $baseSlug = strtolower($this->slugger->slug($title)->toString());
        $slug = $baseSlug;
        $counter = 1;

        while ($this->productRepository->findBySlug($slug) !== null) {
            $slug = $baseSlug.'-'.$counter;
            ++$counter;

            if ($counter > 100) {
                // Fallback to ULID suffix
                $slug = $baseSlug.'-'.strtolower(substr((string) new \Symfony\Component\Uid\Ulid(), 0, 8));
                break;
            }
        }

        return $slug;
    }

    /**
     * Map external size unit to SizeUnit enum.
     */
    private function mapSizeUnit(string $unit): ?SizeUnit
    {
        return match (strtoupper($unit)) {
            'EU' => SizeUnit::EU,
            'UK' => SizeUnit::UK,
            'CM' => SizeUnit::CM,
            default => SizeUnit::US, // Default to US for sneakers
        };
    }
}
