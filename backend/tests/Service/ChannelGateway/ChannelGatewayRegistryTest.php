<?php

declare(strict_types=1);

namespace App\Tests\Service\ChannelGateway;

use App\Service\ChannelGateway\ChannelGatewayInterface;
use App\Service\ChannelGateway\ChannelGatewayRegistry;
use App\Service\ChannelGateway\Exception\ChannelGatewayException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ChannelGatewayRegistryTest extends TestCase
{
    public function testGetGatewayByCode(): void
    {
        $mockGateway = $this->createMock(ChannelGatewayInterface::class);
        $mockGateway->method('getCode')->willReturn('mock');

        $registry = new ChannelGatewayRegistry([$mockGateway]);

        $result = $registry->get('mock');

        $this->assertSame($mockGateway, $result);
    }

    public function testGetUnknownGatewayThrows(): void
    {
        $registry = new ChannelGatewayRegistry([]);

        $this->expectException(ChannelGatewayException::class);

        $registry->get('unknown');
    }

    public function testHasGateway(): void
    {
        $mockGateway = $this->createMock(ChannelGatewayInterface::class);
        $mockGateway->method('getCode')->willReturn('mock');

        $registry = new ChannelGatewayRegistry([$mockGateway]);

        $this->assertTrue($registry->has('mock'));
        $this->assertFalse($registry->has('unknown'));
    }

    public function testGetAvailableGateways(): void
    {
        $gateway1 = $this->createMock(ChannelGatewayInterface::class);
        $gateway1->method('getCode')->willReturn('gateway1');

        $gateway2 = $this->createMock(ChannelGatewayInterface::class);
        $gateway2->method('getCode')->willReturn('gateway2');

        $registry = new ChannelGatewayRegistry([$gateway1, $gateway2]);

        $result = $registry->getAvailableGateways();

        $this->assertCount(2, $result);
        $this->assertContains($gateway1, $result);
        $this->assertContains($gateway2, $result);
    }
}
