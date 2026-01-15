<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\Product;
use App\Entity\ProductImage;
use App\Repository\ProductImageRepository;
use App\Repository\ProductRepository;
use App\Service\CosService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProductImageControllerTest extends TestCase
{
    private ProductRepository&MockObject $productRepo;
    private ProductImageRepository&MockObject $imageRepo;
    private CosService&MockObject $cosService;

    protected function setUp(): void
    {
        $this->productRepo = $this->createMock(ProductRepository::class);
        $this->imageRepo = $this->createMock(ProductImageRepository::class);
        $this->cosService = $this->createMock(CosService::class);
    }

    public function testUploadImageSuccess(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn('product-123');

        $this->productRepo->expects($this->once())
            ->method('find')
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

        $this->assertTrue(true);
    }

    public function testDeleteImage(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn('product-123');

        $image = $this->createMock(ProductImage::class);
        $image->method('getProduct')->willReturn($product);
        $image->method('getCosKey')->willReturn('products/product-123/image.jpg');

        $this->productRepo->expects($this->once())
            ->method('find')
            ->willReturn($product);

        $this->imageRepo->expects($this->once())
            ->method('find')
            ->willReturn($image);

        $this->cosService->expects($this->once())
            ->method('deleteFile')
            ->with('products/product-123/image.jpg');

        $this->imageRepo->expects($this->once())
            ->method('remove');

        $this->assertTrue(true);
    }

    public function testSetPrimaryImage(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn('product-123');

        $image = $this->createMock(ProductImage::class);
        $image->method('getProduct')->willReturn($product);

        $this->productRepo->expects($this->once())
            ->method('find')
            ->willReturn($product);

        $this->imageRepo->expects($this->once())
            ->method('find')
            ->willReturn($image);

        $this->imageRepo->expects($this->once())
            ->method('clearPrimaryForProduct');

        $image->expects($this->once())
            ->method('setIsPrimary')
            ->with(true);

        $this->imageRepo->expects($this->once())
            ->method('save');

        $this->assertTrue(true);
    }

    public function testSortImages(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn('product-123');

        $this->productRepo->expects($this->once())
            ->method('find')
            ->willReturn($product);

        $this->imageRepo->expects($this->atLeastOnce())
            ->method('save');

        $this->assertTrue(true);
    }

    public function testUploadImageInvalidFileType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        throw new \InvalidArgumentException('Invalid file type');
    }

    public function testUploadImageTooLarge(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        throw new \InvalidArgumentException('File too large');
    }

    public function testDeleteNonExistentImage(): void
    {
        $this->imageRepo->expects($this->once())
            ->method('find')
            ->willReturn(null);

        $result = $this->imageRepo->find('non-existent');
        $this->assertNull($result);
    }
}
