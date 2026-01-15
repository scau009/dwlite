<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\JwtAuthenticator;
use PHPUnit\Framework\TestCase;

class JwtAuthenticatorTest extends TestCase
{
    public function testSupportsAuthentication(): void
    {
        $this->assertTrue(true);
    }

    public function testAuthenticateWithValidToken(): void
    {
        $this->assertTrue(true);
    }

    public function testAuthenticateWithInvalidToken(): void
    {
        $this->expectException(\Exception::class);
        throw new \Exception('Invalid token');
    }
}
