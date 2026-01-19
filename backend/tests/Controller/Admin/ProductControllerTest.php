<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\ProductController;
use App\Dto\Admin\BatchUpdateProductStatusRequest;
use App\Dto\Admin\CreateProductRequest;
use App\Dto\Admin\Query\ProductListQuery;
use App\Dto\Admin\UpdateProductRequest;
use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Product;
use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProductImageRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductSkuRepository;
use App\Repository\TagRepository;
use App\Service\CosService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProductControllerTest extends TestCase
{
    private ProductRepository&MockObject $productRepo;
    private ProductSkuRepository&MockObject $skuRepo;
    private ProductImageRepository&MockObject $imageRepo;
    private BrandRepository&MockObject $brandRepo;
    private CategoryRepository&MockObject $categoryRepo;
    private TagRepository&MockObject $tagRepo;
    private CosService&MockObject $cosService;
    private TranslatorInterface&MockObject $translator;
    private LoggerInterface&MockObject $logger;
    private ProductController $controller;

    protected function setUp(): void
    {
        $this->productRepo = $this->createMock(ProductRepository::class);
        $this->skuRepo = $this->createMock(ProductSkuRepository::class);
        $this->imageRepo = $this->createMock(ProductImageRepository::class);
        $this->brandRepo = $this->createMock(BrandRepository::class);
        $this->categoryRepo = $this->createMock(CategoryRepository::class);
        $this->tagRepo = $this->createMock(TagRepository::class);
        $this->cosService = $this->createMock(CosService::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->controller = new ProductController(
            $this->productRepo,
            $this->skuRepo,
            $this->imageRepo,
            $this->brandRepo,
            $this->categoryRepo,
            $this->tagRepo,
            $this->cosService,
            $this->translator,
            $this->logger
        );
    }

    public function testListProducts(): void
    {
        // Arrange
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn('product-123');
        $product->method('getName')->willReturn('Test Product');
        $product->method('getSlug')->willReturn('test-product');
        $product->method('getStyleNumber')->willReturn('STYLE001');
        $product->method('getStatus')->willReturn(Product::STATUS_ACTIVE);
        $product->method('getSeason')->willReturn('FW24');
        $product->method('getColor')->willReturn('Black');
        $product->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC')));
        $product->method('getUpdatedAt')->willReturn(new \DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC')));
        $product->method('getBrand')->willReturn(null);
        $product->method('getCategory')->willReturn(null);
        $product->method('getTags')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());
        $product->method('getSkus')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());
        $product->method('getImages')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());

        $this->productRepo->expects($this->once())
            ->method('findWithFilters')
            ->willReturn([
                'data' => [$product],
                'meta' => ['total' => 1],
            ]);

        // Act
        $response = $this->controller->list(new ProductListQuery());

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals(1, $data['total']);
    }

    public function testDetailProductFound(): void
    {
        // Arrange
        $brand = $this->createMock(Brand::class);
        $brand->method('getId')->willReturn('brand-123');
        $brand->method('getName')->willReturn('Nike');

        $category = $this->createMock(Category::class);
        $category->method('getId')->willReturn('cat-123');
        $category->method('getName')->willReturn('Sneakers');

        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn('product-123');
        $product->method('getName')->willReturn('Test Product');
        $product->method('getSlug')->willReturn('test-product');
        $product->method('getStyleNumber')->willReturn('STYLE001');
        $product->method('getStatus')->willReturn(Product::STATUS_ACTIVE);
        $product->method('getSeason')->willReturn('FW24');
        $product->method('getColor')->willReturn('Black');
        $product->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC')));
        $product->method('getUpdatedAt')->willReturn(new \DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC')));
        $product->method('getBrand')->willReturn($brand);
        $product->method('getCategory')->willReturn($category);
        $product->method('getTags')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());
        $product->method('getSkus')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());
        $product->method('getImages')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());
        $product->method('getDescription')->willReturn('Test description');
        $product->method('getMaterial')->willReturn('Leather');
        $product->method('getReleaseDate')->willReturn(new \DateTimeImmutable('2024-01-15', new \DateTimeZone('UTC')));
        $product->method('getRetailPrice')->willReturn('100.00');
        $product->method('getRetailPriceCurrency')->willReturn('USD');

        $this->productRepo->expects($this->once())
            ->method('find')
            ->with('product-123')
            ->willReturn($product);

        // Act
        $response = $this->controller->detail('product-123');

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('product-123', $data['id']);
    }

    public function testDetailProductNotFound(): void
    {
        // Arrange
        $this->productRepo->expects($this->once())
            ->method('find')
            ->with('nonexistent')
            ->willReturn(null);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.product.not_found')
            ->willReturn('Product not found');

        // Act
        $response = $this->controller->detail('nonexistent');

        // Assert
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testCreateProductSuccess(): void
    {
        // Arrange
        $brand = $this->createMock(Brand::class);
        $category = $this->createMock(Category::class);

        $dto = new CreateProductRequest();
        $dto->name = 'Test Product';
        $dto->styleNumber = 'STYLE001';
        $dto->brandId = 'brand-123';
        $dto->categoryId = 'cat-123';
        $dto->season = 'FW24';
        $dto->color = 'Black';

        $this->productRepo->expects($this->once())
            ->method('findBySlug')
            ->willReturn(null);

        $this->productRepo->expects($this->once())
            ->method('findByStyleNumber')
            ->with('STYLE001')
            ->willReturn(null);

        $this->brandRepo->expects($this->once())
            ->method('find')
            ->with('brand-123')
            ->willReturn($brand);

        $this->categoryRepo->expects($this->once())
            ->method('find')
            ->with('cat-123')
            ->willReturn($category);

        $this->productRepo->expects($this->once())
            ->method('save');

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.product.created')
            ->willReturn('Product created successfully');

        // Act
        $response = $this->controller->create($dto);

        // Assert
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
    }

    public function testCreateProductDuplicateStyleNumber(): void
    {
        // Arrange
        $existingProduct = $this->createMock(Product::class);

        $dto = new CreateProductRequest();
        $dto->name = 'Test Product';
        $dto->styleNumber = 'STYLE001';
        $dto->brandId = 'brand-123';
        $dto->categoryId = 'cat-123';

        $this->productRepo->expects($this->once())
            ->method('findBySlug')
            ->willReturn(null);

        $this->productRepo->expects($this->once())
            ->method('findByStyleNumber')
            ->with('STYLE001')
            ->willReturn($existingProduct);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.product.style_number_exists')
            ->willReturn('Style number already exists');

        // Act
        $response = $this->controller->create($dto);

        // Assert
        $this->assertEquals(Response::HTTP_CONFLICT, $response->getStatusCode());
    }

    public function testUpdateProductSuccess(): void
    {
        // Arrange
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn('product-123');
        $product->method('getSlug')->willReturn('test-product');
        $product->method('getStyleNumber')->willReturn('STYLE001');

        $dto = new UpdateProductRequest();
        $dto->name = 'Updated Product';

        $this->productRepo->expects($this->once())
            ->method('find')
            ->with('product-123')
            ->willReturn($product);

        $this->productRepo->expects($this->once())
            ->method('save');

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.product.updated')
            ->willReturn('Product updated successfully');

        // Act
        $response = $this->controller->update('product-123', $dto);

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testDeleteProductSuccess(): void
    {
        // Arrange
        $product = $this->createMock(Product::class);
        $product->method('getImages')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());

        $this->productRepo->expects($this->once())
            ->method('find')
            ->with('product-123')
            ->willReturn($product);

        $this->productRepo->expects($this->once())
            ->method('remove');

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.product.deleted')
            ->willReturn('Product deleted successfully');

        // Act
        $response = $this->controller->delete('product-123');

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testBatchUpdateProductStatus(): void
    {
        // Arrange
        $product1 = $this->createMock(Product::class);
        $product2 = $this->createMock(Product::class);

        $dto = new BatchUpdateProductStatusRequest();
        $dto->productIds = ['product-1', 'product-2'];
        $dto->status = Product::STATUS_ACTIVE;

        $this->productRepo->expects($this->exactly(2))
            ->method('find')
            ->willReturnOnConsecutiveCalls($product1, $product2);

        $product1->expects($this->once())
            ->method('setStatus')
            ->with(Product::STATUS_ACTIVE);

        $product2->expects($this->once())
            ->method('setStatus')
            ->with(Product::STATUS_ACTIVE);

        $this->productRepo->expects($this->exactly(2))
            ->method('save');

        $this->productRepo->expects($this->once())
            ->method('flush');

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.product.batch_status_updated', ['%count%' => 2])
            ->willReturn('2 products updated');

        // Act
        $response = $this->controller->batchUpdateStatus($dto);

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(2, $data['updatedCount']);
    }
}
