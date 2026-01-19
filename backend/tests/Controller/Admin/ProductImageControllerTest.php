<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\ProductController;
use App\Entity\Product;
use App\Entity\ProductImage;
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
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProductImageControllerTest extends TestCase
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

    public function testUploadImageSuccess(): void
    {
        // Arrange
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn('product-123');
        $product->method('getImages')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());

        $this->productRepo->expects($this->once())
            ->method('find')
            ->with('product-123')
            ->willReturn($product);

        $this->cosService->expects($this->once())
            ->method('uploadFile')
            ->willReturn([
                'cosKey' => 'products/product-123/image.jpg',
                'url' => 'https://example.com/image.jpg',
                'thumbnailUrl' => 'https://example.com/thumb.jpg',
                'fileSize' => 1024,
                'width' => 800,
                'height' => 600,
            ]);

        $this->imageRepo->expects($this->once())
            ->method('save');

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.product.image_uploaded')
            ->willReturn('Image uploaded successfully');

        // Create a mock uploaded file
        $file = $this->createMock(UploadedFile::class);
        $file->method('getMimeType')->willReturn('image/jpeg');
        $file->method('getSize')->willReturn(1024);

        $request = new Request();
        $request->files->set('file', $file);

        // Act
        $response = $this->controller->uploadImage('product-123', $request);

        // Assert
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('image', $data);
    }

    public function testDeleteImage(): void
    {
        // Arrange
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn('product-123');

        $image = $this->createMock(ProductImage::class);
        $image->method('getProduct')->willReturn($product);
        $image->method('getCosKey')->willReturn('products/product-123/image.jpg');
        $image->method('isPrimary')->willReturn(false);

        $this->productRepo->expects($this->once())
            ->method('find')
            ->with('product-123')
            ->willReturn($product);

        $this->imageRepo->expects($this->once())
            ->method('find')
            ->with('image-456')
            ->willReturn($image);

        $this->cosService->expects($this->once())
            ->method('deleteFile')
            ->with('products/product-123/image.jpg');

        $this->imageRepo->expects($this->once())
            ->method('remove');

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.product.image_deleted')
            ->willReturn('Image deleted successfully');

        // Act
        $response = $this->controller->deleteImage('product-123', 'image-456');

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('message', $data);
    }

    public function testSetPrimaryImage(): void
    {
        // Arrange
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn('product-123');

        $image = $this->createMock(ProductImage::class);
        $image->method('getId')->willReturn('image-456');
        $image->method('getProduct')->willReturn($product);
        $image->method('getCosKey')->willReturn('products/product-123/image.jpg');
        $image->method('isPrimary')->willReturn(true);
        $image->method('getSortOrder')->willReturn(0);

        $this->productRepo->expects($this->once())
            ->method('find')
            ->with('product-123')
            ->willReturn($product);

        $this->imageRepo->expects($this->once())
            ->method('find')
            ->with('image-456')
            ->willReturn($image);

        $this->imageRepo->expects($this->once())
            ->method('clearPrimaryForProduct')
            ->with('product-123');

        $image->expects($this->once())
            ->method('setIsPrimary')
            ->with(true);

        $this->imageRepo->expects($this->once())
            ->method('save');

        $this->cosService->expects($this->exactly(2))
            ->method('getSignedUrl')
            ->willReturn('https://example.com/signed-url.jpg');

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.product.image_primary_set')
            ->willReturn('Primary image set successfully');

        // Act
        $response = $this->controller->setImagePrimary('product-123', 'image-456');

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('image', $data);
    }

    public function testSortImages(): void
    {
        // Arrange
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn('product-123');

        $image1 = $this->createMock(ProductImage::class);
        $image1->method('getProduct')->willReturn($product);
        $image2 = $this->createMock(ProductImage::class);
        $image2->method('getProduct')->willReturn($product);

        $images = new \Doctrine\Common\Collections\ArrayCollection([$image1, $image2]);
        $product->method('getImages')->willReturn($images);

        $this->productRepo->expects($this->once())
            ->method('find')
            ->with('product-123')
            ->willReturn($product);

        $this->imageRepo->expects($this->exactly(2))
            ->method('find')
            ->willReturnOnConsecutiveCalls($image1, $image2);

        $this->imageRepo->expects($this->atLeastOnce())
            ->method('save');

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.product.images_sorted')
            ->willReturn('Images sorted successfully');

        $request = new Request([], [], [], [], [], [], json_encode([
            'imageIds' => ['image-1', 'image-2'],
        ]));

        // Act
        $response = $this->controller->sortImages('product-123', $request);

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('message', $data);
    }

    public function testUploadImageInvalidFileType(): void
    {
        // Arrange
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn('product-123');

        $this->productRepo->expects($this->once())
            ->method('find')
            ->with('product-123')
            ->willReturn($product);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.product.invalid_image_type')
            ->willReturn('Invalid image type');

        // Create a file with invalid MIME type
        $file = $this->createMock(UploadedFile::class);
        $file->method('getMimeType')->willReturn('text/plain');

        $request = new Request();
        $request->files->set('file', $file);

        // Act
        $response = $this->controller->uploadImage('product-123', $request);

        // Assert
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testUploadImageTooLarge(): void
    {
        // Arrange
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn('product-123');

        $this->productRepo->expects($this->once())
            ->method('find')
            ->with('product-123')
            ->willReturn($product);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.product.image_too_large')
            ->willReturn('File too large');

        // Create a file that's too large (> 5MB)
        $file = $this->createMock(UploadedFile::class);
        $file->method('getMimeType')->willReturn('image/jpeg');
        $file->method('getSize')->willReturn(6 * 1024 * 1024); // 6MB

        $request = new Request();
        $request->files->set('file', $file);

        // Act
        $response = $this->controller->uploadImage('product-123', $request);

        // Assert
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testDeleteNonExistentImage(): void
    {
        // Arrange
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn('product-123');

        $this->productRepo->expects($this->once())
            ->method('find')
            ->with('product-123')
            ->willReturn($product);

        $this->imageRepo->expects($this->once())
            ->method('find')
            ->with('non-existent')
            ->willReturn(null);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.product.image_not_found')
            ->willReturn('Image not found');

        // Act
        $response = $this->controller->deleteImage('product-123', 'non-existent');

        // Assert
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }
}
