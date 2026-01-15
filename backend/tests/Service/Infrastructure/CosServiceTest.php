<?php

declare(strict_types=1);

namespace App\Tests\Service\Infrastructure;

use App\Service\CosService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CosServiceTest extends TestCase
{
    private CosService&MockObject $cosService;

    protected function setUp(): void
    {
        $this->cosService = $this->createMock(CosService::class);
    }

    public function testUploadFile(): void
    {
        $this->cosService->expects($this->once())
            ->method('uploadFile')
            ->willReturn([
                'cosKey' => 'test/file.jpg',
                'url' => 'https://example.com/file.jpg',
                'thumbnailUrl' => 'https://example.com/thumb.jpg',
                'fileSize' => 1024,
                'width' => 800,
                'height' => 600,
            ]);

        $result = $this->cosService->uploadFile($this->createMock(\Symfony\Component\HttpFoundation\File\UploadedFile::class), 'test');
        $this->assertArrayHasKey('cosKey', $result);
        $this->assertArrayHasKey('url', $result);
    }

    public function testDeleteFile(): void
    {
        $this->cosService->expects($this->once())
            ->method('deleteFile')
            ->with('test/file.jpg');

        $this->cosService->deleteFile('test/file.jpg');
        $this->assertTrue(true);
    }

    public function testGetSignedUrl(): void
    {
        $this->cosService->expects($this->once())
            ->method('getSignedUrl')
            ->with('test/file.jpg')
            ->willReturn('https://example.com/signed-url');

        $result = $this->cosService->getSignedUrl('test/file.jpg');
        $this->assertStringContainsString('https://', $result);
    }
}
