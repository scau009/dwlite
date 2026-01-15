<?php

declare(strict_types=1);

namespace App\Tests\Service\Infrastructure;

use App\Service\RequestIdService;
use PHPUnit\Framework\TestCase;

class RequestIdServiceTest extends TestCase
{
    public function testGenerateRequestId(): void
    {
        $service = new RequestIdService();
        $requestId = $service->generate();

        $this->assertNotEmpty($requestId);
        $this->assertIsString($requestId);
    }

    public function testSetAndGetRequestId(): void
    {
        $service = new RequestIdService();
        $requestId = 'test-request-id';

        $service->set($requestId);
        $result = $service->get();

        $this->assertEquals($requestId, $result);
    }

    public function testGetGeneratesIfNotSet(): void
    {
        $service = new RequestIdService();
        $requestId = $service->get();

        $this->assertNotEmpty($requestId);
    }
}
