<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\WarehouseController;
use App\Dto\Admin\CreateWarehouseRequest;
use App\Dto\Admin\Query\WarehouseListQuery;
use App\Dto\Admin\UpdateWarehouseRequest;
use App\Entity\Warehouse;
use App\Repository\MerchantRepository;
use App\Repository\WarehouseRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class WarehouseControllerTest extends TestCase
{
    private WarehouseRepository&MockObject $warehouseRepo;
    private MerchantRepository&MockObject $merchantRepo;
    private TranslatorInterface&MockObject $translator;
    private WarehouseController $controller;

    protected function setUp(): void
    {
        $this->warehouseRepo = $this->createMock(WarehouseRepository::class);
        $this->merchantRepo = $this->createMock(MerchantRepository::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->controller = new WarehouseController(
            $this->warehouseRepo,
            $this->merchantRepo,
            $this->translator
        );
    }

    public function testListWarehouses(): void
    {
        // Arrange
        $warehouse = $this->createMock(Warehouse::class);
        $warehouse->method('getId')->willReturn('wh-123');
        $warehouse->method('getCode')->willReturn('WH01');
        $warehouse->method('getName')->willReturn('Test Warehouse');
        $warehouse->method('getShortName')->willReturn('TW');
        $warehouse->method('getType')->willReturn(Warehouse::TYPE_DISTRIBUTION);
        $warehouse->method('getCategory')->willReturn(Warehouse::CATEGORY_PLATFORM);
        $warehouse->method('getCountryCode')->willReturn('CN');
        $warehouse->method('getStatus')->willReturn(Warehouse::STATUS_ACTIVE);
        $warehouse->method('getSortOrder')->willReturn(1);
        $warehouse->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC')));
        $warehouse->method('getUpdatedAt')->willReturn(new \DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC')));
        $warehouse->method('getMerchant')->willReturn(null);
        $warehouse->method('getFullAddress')->willReturn('Test Address');
        $warehouse->method('getCity')->willReturn('Beijing');
        $warehouse->method('getProvince')->willReturn('Beijing');
        $warehouse->method('getContactName')->willReturn('John Doe');
        $warehouse->method('getContactPhone')->willReturn('1234567890');

        $this->warehouseRepo->expects($this->once())
            ->method('findPaginated')
            ->with(1, 20, [])
            ->willReturn(['data' => [$warehouse], 'total' => 1]);

        // Act
        $response = $this->controller->list(new WarehouseListQuery());

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals(1, $data['total']);
    }

    public function testDetailWarehouseFound(): void
    {
        // Arrange
        $warehouse = $this->createMock(Warehouse::class);
        $warehouse->method('getId')->willReturn('wh-123');
        $warehouse->method('getCode')->willReturn('WH01');
        $warehouse->method('getName')->willReturn('Test Warehouse');
        $warehouse->method('getShortName')->willReturn('TW');
        $warehouse->method('getType')->willReturn(Warehouse::TYPE_DISTRIBUTION);
        $warehouse->method('getCategory')->willReturn(Warehouse::CATEGORY_PLATFORM);
        $warehouse->method('getCountryCode')->willReturn('CN');
        $warehouse->method('getStatus')->willReturn(Warehouse::STATUS_ACTIVE);
        $warehouse->method('getSortOrder')->willReturn(1);
        $warehouse->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC')));
        $warehouse->method('getUpdatedAt')->willReturn(new \DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC')));
        $warehouse->method('getMerchant')->willReturn(null);
        $warehouse->method('getFullAddress')->willReturn('Test Address');
        $warehouse->method('getCity')->willReturn('Beijing');
        $warehouse->method('getProvince')->willReturn('Beijing');
        $warehouse->method('getContactName')->willReturn('John Doe');
        $warehouse->method('getContactPhone')->willReturn('1234567890');
        $warehouse->method('getDescription')->willReturn('Test Description');
        $warehouse->method('getTimezone')->willReturn('Asia/Shanghai');
        $warehouse->method('getDistrict')->willReturn('Chaoyang');
        $warehouse->method('getAddress')->willReturn('123 Test St');
        $warehouse->method('getPostalCode')->willReturn('100000');
        $warehouse->method('getLongitude')->willReturn(116.4074);
        $warehouse->method('getLatitude')->willReturn(39.9042);
        $warehouse->method('getContactEmail')->willReturn('test@example.com');
        $warehouse->method('getInternalNotes')->willReturn('Internal notes');

        $this->warehouseRepo->expects($this->once())
            ->method('find')
            ->with('wh-123')
            ->willReturn($warehouse);

        // Act
        $response = $this->controller->detail('wh-123');

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('wh-123', $data['id']);
    }

    public function testDetailWarehouseNotFound(): void
    {
        // Arrange
        $this->warehouseRepo->expects($this->once())
            ->method('find')
            ->with('nonexistent')
            ->willReturn(null);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.warehouse.not_found')
            ->willReturn('Warehouse not found');

        // Act
        $response = $this->controller->detail('nonexistent');

        // Assert
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testCreateWarehouseSuccess(): void
    {
        // Arrange
        $dto = new CreateWarehouseRequest();
        $dto->code = 'WH01';
        $dto->name = 'Test Warehouse';
        $dto->shortName = 'TW';
        $dto->type = Warehouse::TYPE_DISTRIBUTION;
        $dto->category = Warehouse::CATEGORY_PLATFORM;
        $dto->countryCode = 'CN';
        $dto->timezone = 'Asia/Shanghai';
        $dto->province = 'Beijing';
        $dto->city = 'Beijing';
        $dto->district = 'Chaoyang';
        $dto->address = '123 Test St';
        $dto->status = Warehouse::STATUS_ACTIVE;
        $dto->sortOrder = 1;

        $this->warehouseRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => 'WH01'])
            ->willReturn(null);

        $this->warehouseRepo->expects($this->once())
            ->method('save');

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.warehouse.created')
            ->willReturn('Warehouse created successfully');

        // Act
        $response = $this->controller->create($dto);

        // Assert
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
    }

    public function testCreateWarehouseCodeExists(): void
    {
        // Arrange
        $existing = $this->createMock(Warehouse::class);
        $dto = new CreateWarehouseRequest();
        $dto->code = 'WH01';
        $dto->name = 'Test Warehouse';
        $dto->shortName = 'TW';
        $dto->type = Warehouse::TYPE_DISTRIBUTION;
        $dto->category = Warehouse::CATEGORY_PLATFORM;
        $dto->countryCode = 'CN';
        $dto->timezone = 'Asia/Shanghai';
        $dto->province = 'Beijing';
        $dto->city = 'Beijing';
        $dto->district = 'Chaoyang';
        $dto->address = '123 Test St';
        $dto->status = Warehouse::STATUS_ACTIVE;
        $dto->sortOrder = 1;

        $this->warehouseRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => 'WH01'])
            ->willReturn($existing);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.warehouse.code_exists')
            ->willReturn('Warehouse code already exists');

        // Act
        $response = $this->controller->create($dto);

        // Assert
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testUpdateWarehouseSuccess(): void
    {
        // Arrange
        $warehouse = $this->createMock(Warehouse::class);
        $warehouse->method('getId')->willReturn('wh-123');
        $warehouse->method('getCode')->willReturn('WH01');

        $dto = new UpdateWarehouseRequest();
        $dto->name = 'Updated Warehouse';

        $this->warehouseRepo->expects($this->once())
            ->method('find')
            ->with('wh-123')
            ->willReturn($warehouse);

        $this->warehouseRepo->expects($this->once())
            ->method('save');

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.warehouse.updated')
            ->willReturn('Warehouse updated successfully');

        // Act
        $response = $this->controller->update('wh-123', $dto);

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testUpdateWarehouseNotFound(): void
    {
        // Arrange
        $dto = new UpdateWarehouseRequest();
        $dto->name = 'Updated Warehouse';

        $this->warehouseRepo->expects($this->once())
            ->method('find')
            ->with('nonexistent')
            ->willReturn(null);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.warehouse.not_found')
            ->willReturn('Warehouse not found');

        // Act
        $response = $this->controller->update('nonexistent', $dto);

        // Assert
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testDeleteWarehouseSuccess(): void
    {
        // Arrange
        $warehouse = $this->createMock(Warehouse::class);

        $this->warehouseRepo->expects($this->once())
            ->method('find')
            ->with('wh-123')
            ->willReturn($warehouse);

        $this->warehouseRepo->expects($this->once())
            ->method('remove');

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.warehouse.deleted')
            ->willReturn('Warehouse deleted successfully');

        // Act
        $response = $this->controller->delete('wh-123');

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testDeleteWarehouseNotFound(): void
    {
        // Arrange
        $this->warehouseRepo->expects($this->once())
            ->method('find')
            ->with('nonexistent')
            ->willReturn(null);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.warehouse.not_found')
            ->willReturn('Warehouse not found');

        // Act
        $response = $this->controller->delete('nonexistent');

        // Assert
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }
}
