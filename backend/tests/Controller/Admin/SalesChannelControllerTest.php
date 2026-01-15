<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\SalesChannelController;
use App\Dto\Admin\CreateSalesChannelRequest;
use App\Dto\Admin\Query\SalesChannelListQuery;
use App\Dto\Admin\UpdateSalesChannelRequest;
use App\Entity\SalesChannel;
use App\Repository\SalesChannelRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class SalesChannelControllerTest extends TestCase
{
    private SalesChannelRepository&MockObject $channelRepo;
    private TranslatorInterface&MockObject $translator;
    private SalesChannelController $controller;

    protected function setUp(): void
    {
        $this->channelRepo = $this->createMock(SalesChannelRepository::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->controller = new SalesChannelController($this->channelRepo, $this->translator);
    }

    public function testListChannels(): void
    {
        // Arrange
        $channel = $this->createMock(SalesChannel::class);
        $channel->method('getId')->willReturn('channel-123');
        $channel->method('getCode')->willReturn('CH01');
        $channel->method('getName')->willReturn('Test Channel');
        $channel->method('getStatus')->willReturn(SalesChannel::STATUS_ACTIVE);
        $channel->method('getLogoUrl')->willReturn('https://example.com/logo.jpg');
        $channel->method('getSortOrder')->willReturn(1);
        $channel->method('getCurrency')->willReturn('USD');
        $channel->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC')));
        $channel->method('getUpdatedAt')->willReturn(new \DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC')));

        $this->channelRepo->expects($this->once())
            ->method('findPaginated')
            ->with(1, 20, [])
            ->willReturn(['data' => [$channel], 'total' => 1]);

        // Act
        $response = $this->controller->list(new SalesChannelListQuery());

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals(1, $data['total']);
    }

    public function testDetailChannelFound(): void
    {
        // Arrange
        $channel = $this->createMock(SalesChannel::class);
        $channel->method('getId')->willReturn('channel-123');
        $channel->method('getCode')->willReturn('CH01');
        $channel->method('getName')->willReturn('Test Channel');
        $channel->method('getStatus')->willReturn(SalesChannel::STATUS_ACTIVE);
        $channel->method('getLogoUrl')->willReturn('https://example.com/logo.jpg');
        $channel->method('getSortOrder')->willReturn(1);
        $channel->method('getCurrency')->willReturn('USD');
        $channel->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC')));
        $channel->method('getUpdatedAt')->willReturn(new \DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC')));
        $channel->method('getDescription')->willReturn('Test Description');
        $channel->method('getConfig')->willReturn([]);
        $channel->method('getConfigSchema')->willReturn([]);

        $this->channelRepo->expects($this->once())
            ->method('find')
            ->with('channel-123')
            ->willReturn($channel);

        // Act
        $response = $this->controller->detail('channel-123');

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('channel-123', $data['id']);
    }

    public function testDetailChannelNotFound(): void
    {
        // Arrange
        $this->channelRepo->expects($this->once())
            ->method('find')
            ->with('nonexistent')
            ->willReturn(null);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.channel.not_found')
            ->willReturn('Channel not found');

        // Act
        $response = $this->controller->detail('nonexistent');

        // Assert
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testCreateChannelSuccess(): void
    {
        // Arrange
        $dto = new CreateSalesChannelRequest();
        $dto->code = 'CH01';
        $dto->name = 'Test Channel';
        $dto->status = SalesChannel::STATUS_ACTIVE;

        $this->channelRepo->expects($this->once())
            ->method('existsByCode')
            ->with('CH01')
            ->willReturn(false);

        $this->channelRepo->expects($this->once())
            ->method('save');

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.channel.created')
            ->willReturn('Channel created successfully');

        // Act
        $response = $this->controller->create($dto);

        // Assert
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
    }

    public function testCreateChannelCodeExists(): void
    {
        // Arrange
        $dto = new CreateSalesChannelRequest();
        $dto->code = 'CH01';
        $dto->name = 'Test Channel';
        $dto->status = SalesChannel::STATUS_ACTIVE;

        $this->channelRepo->expects($this->once())
            ->method('existsByCode')
            ->with('CH01')
            ->willReturn(true);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.channel.code_exists')
            ->willReturn('Channel code already exists');

        // Act
        $response = $this->controller->create($dto);

        // Assert
        $this->assertEquals(Response::HTTP_CONFLICT, $response->getStatusCode());
    }

    public function testUpdateChannelSuccess(): void
    {
        // Arrange
        $channel = $this->createMock(SalesChannel::class);
        $channel->method('getId')->willReturn('channel-123');

        $dto = new UpdateSalesChannelRequest();
        $dto->name = 'Updated Channel';

        $this->channelRepo->expects($this->once())
            ->method('find')
            ->with('channel-123')
            ->willReturn($channel);

        $this->channelRepo->expects($this->once())
            ->method('save');

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.channel.updated')
            ->willReturn('Channel updated successfully');

        // Act
        $response = $this->controller->update('channel-123', $dto);

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testDeleteChannelSuccess(): void
    {
        // Arrange
        $channel = $this->createMock(SalesChannel::class);
        $channel->method('getMerchantChannels')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());

        $this->channelRepo->expects($this->once())
            ->method('find')
            ->with('channel-123')
            ->willReturn($channel);

        $this->channelRepo->expects($this->once())
            ->method('remove');

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.channel.deleted')
            ->willReturn('Channel deleted successfully');

        // Act
        $response = $this->controller->delete('channel-123');

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testDeleteChannelHasMerchants(): void
    {
        // Arrange
        $channel = $this->createMock(SalesChannel::class);
        $merchants = new \Doctrine\Common\Collections\ArrayCollection([new \stdClass()]);
        $channel->method('getMerchantChannels')->willReturn($merchants);

        $this->channelRepo->expects($this->once())
            ->method('find')
            ->with('channel-123')
            ->willReturn($channel);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('admin.channel.has_merchants')
            ->willReturn('Cannot delete channel with merchants');

        // Act
        $response = $this->controller->delete('channel-123');

        // Assert
        $this->assertEquals(Response::HTTP_CONFLICT, $response->getStatusCode());
    }
}
