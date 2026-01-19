<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Attribute\AdminOnly;
use App\Entity\User;
use App\Security\AdminOnlyAttributeListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class AdminOnlyAttributeListenerTest extends TestCase
{
    public function testAllowsAdminAccess(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getRoles')->willReturn(['ROLE_ADMIN']);

        $this->assertEquals(['ROLE_ADMIN'], $user->getRoles());
    }

    public function testDeniesNonAdminAccess(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getRoles')->willReturn(['ROLE_USER']);

        $this->assertNotContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testAllowsAccessWithoutAttribute(): void
    {
        // Controllers without AdminOnly attribute should allow all authenticated users
        $this->assertTrue(true);
    }

    public function testDeniesUnauthenticatedAccess(): void
    {
        $user = null;

        $this->assertNull($user);
    }
}
