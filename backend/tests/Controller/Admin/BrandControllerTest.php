<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\BrandController;
use App\Dto\Admin\BatchUpdateBrandStatusRequest;
use App\Dto\Admin\CreateBrandRequest;
use App\Dto\Admin\Query\BrandListQuery;
use App\Dto\Admin\UpdateBrandRequest;
use App\Dto\Admin\UpdateStatusRequest;
use App\Entity\Brand;
use App\Repository\BrandRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class BrandControllerTest extends TestCase
{
    private BrandRepository&MockObject $brandRepo;
    private TranslatorInterface&MockObject $translator;
    private BrandController $controller;

    protected function setUp(): void
    {
        $this->brandRepo = $this->createMock(BrandRepository::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->controller = new BrandController($this->brandRepo, $this->translator);
    }

    public function testListBrands(): void
    {
        // Arrange
        $brand = $this->createMock(Brand::class);
        $brand->method('getId')->willReturn('brand-123');
        $brand->method('getName')->willReturn('Nike');
        $brand->method('getSlug')->willReturn('nike');
        $brand->method('getLogoUrl')->willReturn('https://example.com/logo.jpg');
        $brand->method('getSortOrder')->willReturn(1);
        $brand->method('isActive')->willReturn(true);
        $brand->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC')));
        $brand->method('getUpdatedAt')->willReturn(new \DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC')));
        $brand->method('getProducts')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());

        $this->brandRepo->expects($this->once())
            ->method('findPaginated')
            ->with(1, 20, [])
            ->willReturn(['data' => [$brand], 'total' => 1]);

        // Act
        $response = $this->controller->list(new BrandListQuery());

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertEquals(1, $data['total']);
    }

    public function testDetailBrandFound(): void
    {
        // Arrange
        $brand = $this->createMock(Brand::class);
        $brand->method('getId')->willReturn('brand-123');
        $brand->method('getName')->willReturn('Nike');
        $brand->method('getSlug')->willReturn('nike');
        $brand->method('getLogoUrl')->willReturn('https://example.com/logo.jpg');
        $brand->method('getSortOrder')->willReturn(1);
        $brand->method('isActive')->willReturn(true);
        $brand->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC')));
        $brand->method('getUpdatedAt')->willReturn(new \DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC')));
        $brand->method('getDescription')->willReturn('Test description');
        $brand->method('getProducts')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());

        $this->brandRepo->expects($this->once())
            ->method('find')
            ->with('brand-123')
            ->willReturn($brand);

        // Act
        $response = $this->controller->detail('brand-123');

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('brand-123', $data['id']);
        $this->assertEquals('Nike', $data['name']);
    }

    public function testDetailBrandNotFound(): void
    {
        // Arrange
        $this->brandRepo->expects($this->once())
            ->method('find')
            ->with('nonexistent')
            ->willReturn(null);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.brand.not_found')
            ->willReturn('Brand not found');

        // Act
        $response = $this->controller->detail('nonexistent');

        // Assert
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testCreateBrandSuccess(): void
    {
        // Arrange
        $dto = new CreateBrandRequest();
        $dto->name = 'Nike';
        $dto->slug = null;

        $this->brandRepo->expects($this->once())
            ->method('existsBySlug')
            ->willReturn(false);

        $this->brandRepo->expects($this->once())
            ->method('save');

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.brand.created')
            ->willReturn('Brand created successfully');

        // Act
        $response = $this->controller->create($dto);

        // Assert
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
    }

    public function testCreateBrandSlugExists(): void
    {
        // Arrange
        $dto = new CreateBrandRequest();
        $dto->name = 'Nike';
        $dto->slug = 'nike';

        $this->brandRepo->expects($this->once())
            ->method('existsBySlug')
            ->with('nike')
            ->willReturn(true);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.brand.slug_exists')
            ->willReturn('Slug already exists');

        // Act
        $response = $this->controller->create($dto);

        // Assert
        $this->assertEquals(Response::HTTP_CONFLICT, $response->getStatusCode());
    }

    public function testUpdateBrandSuccess(): void
    {
        // Arrange
        $brand = $this->createMock(Brand::class);
        $brand->method('getId')->willReturn('brand-123');

        $dto = new UpdateBrandRequest();
        $dto->name = 'Nike Updated';

        $this->brandRepo->expects($this->once())
            ->method('find')
            ->with('brand-123')
            ->willReturn($brand);

        $this->brandRepo->expects($this->once())
            ->method('save');

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.brand.updated')
            ->willReturn('Brand updated successfully');

        // Act
        $response = $this->controller->update('brand-123', $dto);

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testDeleteBrandSuccess(): void
    {
        // Arrange
        $brand = $this->createMock(Brand::class);
        $brand->method('getProducts')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());

        $this->brandRepo->expects($this->once())
            ->method('find')
            ->with('brand-123')
            ->willReturn($brand);

        $this->brandRepo->expects($this->once())
            ->method('remove');

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.brand.deleted')
            ->willReturn('Brand deleted successfully');

        // Act
        $response = $this->controller->delete('brand-123');

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testDeleteBrandHasProducts(): void
    {
        // Arrange
        $brand = $this->createMock(Brand::class);
        $products = new \Doctrine\Common\Collections\ArrayCollection([new \stdClass()]);
        $brand->method('getProducts')->willReturn($products);

        $this->brandRepo->expects($this->once())
            ->method('find')
            ->with('brand-123')
            ->willReturn($brand);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.brand.has_products')
            ->willReturn('Cannot delete brand with products');

        // Act
        $response = $this->controller->delete('brand-123');

        // Assert
        $this->assertEquals(Response::HTTP_CONFLICT, $response->getStatusCode());
    }

    public function testUpdateStatusSuccess(): void
    {
        // Arrange
        $brand = $this->createMock(Brand::class);
        $dto = new UpdateStatusRequest();
        $dto->isActive = true;

        $this->brandRepo->expects($this->once())
            ->method('find')
            ->with('brand-123')
            ->willReturn($brand);

        $brand->expects($this->once())
            ->method('setIsActive')
            ->with(true);

        $this->brandRepo->expects($this->once())
            ->method('save');

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.brand.activated')
            ->willReturn('Brand activated');

        // Act
        $response = $this->controller->updateStatus('brand-123', $dto);

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testBatchUpdateStatusSuccess(): void
    {
        // Arrange
        $dto = new BatchUpdateBrandStatusRequest();
        $dto->ids = ['brand-1', 'brand-2'];
        $dto->isActive = true;

        $this->brandRepo->expects($this->once())
            ->method('batchUpdateStatus')
            ->with(['brand-1', 'brand-2'], true)
            ->willReturn(2);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.brand.batch_activated', ['%count%' => 2])
            ->willReturn('2 brands activated');

        // Act
        $response = $this->controller->batchUpdateStatus($dto);

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(2, $data['updated']);
    }
}
