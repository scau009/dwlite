<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\CategoryController;
use App\Dto\Admin\CreateCategoryRequest;
use App\Dto\Admin\Query\CategoryListQuery;
use App\Dto\Admin\UpdateCategoryRequest;
use App\Dto\Admin\UpdateStatusRequest;
use App\Entity\Category;
use App\Repository\CategoryRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class CategoryControllerTest extends TestCase
{
    private CategoryRepository&MockObject $categoryRepo;
    private TranslatorInterface&MockObject $translator;
    private CategoryController $controller;

    protected function setUp(): void
    {
        $this->categoryRepo = $this->createMock(CategoryRepository::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->controller = new CategoryController($this->categoryRepo, $this->translator);
    }

    public function testListCategories(): void
    {
        // Arrange
        $category = $this->createMock(Category::class);
        $category->method('getId')->willReturn('category-123');
        $category->method('getName')->willReturn('Sneakers');
        $category->method('getSlug')->willReturn('sneakers');
        $category->method('getParent')->willReturn(null);
        $category->method('getLevel')->willReturn(0);
        $category->method('getSortOrder')->willReturn(1);
        $category->method('isActive')->willReturn(true);
        $category->method('hasChildren')->willReturn(false);
        $category->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC')));
        $category->method('getUpdatedAt')->willReturn(new \DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC')));
        $category->method('getProducts')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());
        $category->method('getChildren')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());

        $this->categoryRepo->expects($this->once())
            ->method('findPaginated')
            ->with(1, 20, [])
            ->willReturn(['data' => [$category], 'total' => 1]);

        // Act
        $response = $this->controller->list(new CategoryListQuery());

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertEquals(1, $data['total']);
    }

    public function testDetailCategoryFound(): void
    {
        // Arrange
        $category = $this->createMock(Category::class);
        $category->method('getId')->willReturn('category-123');
        $category->method('getName')->willReturn('Sneakers');
        $category->method('getSlug')->willReturn('sneakers');
        $category->method('getParent')->willReturn(null);
        $category->method('getLevel')->willReturn(0);
        $category->method('getSortOrder')->willReturn(1);
        $category->method('isActive')->willReturn(true);
        $category->method('hasChildren')->willReturn(false);
        $category->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC')));
        $category->method('getUpdatedAt')->willReturn(new \DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC')));
        $category->method('getDescription')->willReturn('Test description');
        $category->method('getProducts')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());
        $category->method('getChildren')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());

        $this->categoryRepo->expects($this->once())
            ->method('find')
            ->with('category-123')
            ->willReturn($category);

        // Act
        $response = $this->controller->detail('category-123');

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('category-123', $data['id']);
        $this->assertEquals('Sneakers', $data['name']);
    }

    public function testDetailCategoryNotFound(): void
    {
        // Arrange
        $this->categoryRepo->expects($this->once())
            ->method('find')
            ->with('nonexistent')
            ->willReturn(null);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.category.not_found')
            ->willReturn('Category not found');

        // Act
        $response = $this->controller->detail('nonexistent');

        // Assert
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testCreateCategorySuccess(): void
    {
        // Arrange
        $dto = new CreateCategoryRequest();
        $dto->name = 'Sneakers';
        $dto->slug = null;
        $dto->parentId = null;

        $this->categoryRepo->expects($this->once())
            ->method('existsBySlug')
            ->willReturn(false);

        $this->categoryRepo->expects($this->once())
            ->method('save');

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.category.created')
            ->willReturn('Category created successfully');

        // Act
        $response = $this->controller->create($dto);

        // Assert
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
    }

    public function testCreateCategorySlugExists(): void
    {
        // Arrange
        $dto = new CreateCategoryRequest();
        $dto->name = 'Sneakers';
        $dto->slug = 'sneakers';

        $this->categoryRepo->expects($this->once())
            ->method('existsBySlug')
            ->with('sneakers')
            ->willReturn(true);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.category.slug_exists')
            ->willReturn('Slug already exists');

        // Act
        $response = $this->controller->create($dto);

        // Assert
        $this->assertEquals(Response::HTTP_CONFLICT, $response->getStatusCode());
    }

    public function testCreateCategoryWithParent(): void
    {
        // Arrange
        $parent = $this->createMock(Category::class);
        $parent->method('getLevel')->willReturn(0);

        $dto = new CreateCategoryRequest();
        $dto->name = 'Running Shoes';
        $dto->parentId = 'parent-123';

        $this->categoryRepo->expects($this->once())
            ->method('existsBySlug')
            ->willReturn(false);

        $this->categoryRepo->expects($this->once())
            ->method('find')
            ->with('parent-123')
            ->willReturn($parent);

        $this->categoryRepo->expects($this->once())
            ->method('save');

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.category.created')
            ->willReturn('Category created successfully');

        // Act
        $response = $this->controller->create($dto);

        // Assert
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
    }

    public function testUpdateCategorySuccess(): void
    {
        // Arrange
        $category = $this->createMock(Category::class);
        $category->method('getId')->willReturn('category-123');

        $dto = new UpdateCategoryRequest();
        $dto->name = 'Sneakers Updated';

        $this->categoryRepo->expects($this->once())
            ->method('find')
            ->with('category-123')
            ->willReturn($category);

        $this->categoryRepo->expects($this->once())
            ->method('save');

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.category.updated')
            ->willReturn('Category updated successfully');

        // Act
        $response = $this->controller->update('category-123', $dto);

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testDeleteCategorySuccess(): void
    {
        // Arrange
        $category = $this->createMock(Category::class);
        $category->method('hasChildren')->willReturn(false);
        $category->method('getProducts')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());

        $this->categoryRepo->expects($this->once())
            ->method('find')
            ->with('category-123')
            ->willReturn($category);

        $this->categoryRepo->expects($this->once())
            ->method('remove');

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.category.deleted')
            ->willReturn('Category deleted successfully');

        // Act
        $response = $this->controller->delete('category-123');

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testDeleteCategoryHasChildren(): void
    {
        // Arrange
        $category = $this->createMock(Category::class);
        $category->method('hasChildren')->willReturn(true);
        $children = new \Doctrine\Common\Collections\ArrayCollection([new \stdClass()]);
        $category->method('getChildren')->willReturn($children);

        $this->categoryRepo->expects($this->once())
            ->method('find')
            ->with('category-123')
            ->willReturn($category);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.category.has_children')
            ->willReturn('Cannot delete category with children');

        // Act
        $response = $this->controller->delete('category-123');

        // Assert
        $this->assertEquals(Response::HTTP_CONFLICT, $response->getStatusCode());
    }

    public function testDeleteCategoryHasProducts(): void
    {
        // Arrange
        $category = $this->createMock(Category::class);
        $category->method('hasChildren')->willReturn(false);
        $products = new \Doctrine\Common\Collections\ArrayCollection([new \stdClass()]);
        $category->method('getProducts')->willReturn($products);

        $this->categoryRepo->expects($this->once())
            ->method('find')
            ->with('category-123')
            ->willReturn($category);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.category.has_products')
            ->willReturn('Cannot delete category with products');

        // Act
        $response = $this->controller->delete('category-123');

        // Assert
        $this->assertEquals(Response::HTTP_CONFLICT, $response->getStatusCode());
    }

    public function testUpdateStatusSuccess(): void
    {
        // Arrange
        $category = $this->createMock(Category::class);
        $dto = new UpdateStatusRequest();
        $dto->isActive = true;

        $this->categoryRepo->expects($this->once())
            ->method('find')
            ->with('category-123')
            ->willReturn($category);

        $category->expects($this->once())
            ->method('setIsActive')
            ->with(true);

        $this->categoryRepo->expects($this->once())
            ->method('save');

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.category.activated')
            ->willReturn('Category activated');

        // Act
        $response = $this->controller->updateStatus('category-123', $dto);

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }
}
