<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Dto\Auth\ChangePasswordRequest;
use App\Dto\Auth\RegisterRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Auth\AuthService;
use App\Service\Auth\EmailVerificationService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AuthServiceTest extends TestCase
{
    private UserRepository&MockObject $userRepository;
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private EmailVerificationService&MockObject $emailVerificationService;
    private TranslatorInterface&MockObject $translator;
    private AuthService $service;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->emailVerificationService = $this->createMock(EmailVerificationService::class);
        $this->translator = $this->createMock(TranslatorInterface::class);

        $this->translator->method('trans')->willReturnCallback(fn(string $key) => $key);

        $this->service = new AuthService(
            $this->userRepository,
            $this->passwordHasher,
            $this->emailVerificationService,
            $this->translator,
        );
    }

    public function testRegisterWithValidData(): void
    {
        $request = new RegisterRequest();
        $request->email = 'test@example.com';
        $request->password = 'Test123!';

        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->with('test@example.com')
            ->willReturn(null);

        $this->passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->willReturn('hashed_password');

        $this->userRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(User::class), true);

        $this->emailVerificationService->expects($this->once())
            ->method('sendVerificationEmail')
            ->with($this->isInstanceOf(User::class));

        $user = $this->service->register($request);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertEquals(User::ACCOUNT_TYPE_MERCHANT, $user->getAccountType());
    }

    public function testRegisterWithDuplicateEmail(): void
    {
        $request = new RegisterRequest();
        $request->email = 'existing@example.com';
        $request->password = 'Test123!';

        $existingUser = $this->createMock(User::class);
        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->with('existing@example.com')
            ->willReturn($existingUser);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('auth.error.user_exists');

        $this->service->register($request);
    }

    public function testChangePasswordWithValidOldPassword(): void
    {
        $user = $this->createMock(User::class);
        $request = new ChangePasswordRequest();
        $request->currentPassword = 'oldPassword123';
        $request->newPassword = 'newPassword456';

        $this->passwordHasher->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, 'oldPassword123')
            ->willReturn(true);

        $this->passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->with($user, 'newPassword456')
            ->willReturn('hashed_new_password');

        $user->expects($this->once())
            ->method('setPassword')
            ->with('hashed_new_password');

        $this->userRepository->expects($this->once())
            ->method('save')
            ->with($user, true);

        $this->service->changePassword($user, $request);
    }

    public function testChangePasswordWithInvalidOldPassword(): void
    {
        $user = $this->createMock(User::class);
        $request = new ChangePasswordRequest();
        $request->currentPassword = 'wrongPassword';
        $request->newPassword = 'newPassword456';

        $this->passwordHasher->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, 'wrongPassword')
            ->willReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('auth.change_password.incorrect_current');

        $this->service->changePassword($user, $request);
    }

    public function testGetUserByEmail(): void
    {
        $user = $this->createMock(User::class);
        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->with('test@example.com')
            ->willReturn($user);

        $result = $this->service->getUserByEmail('test@example.com');

        $this->assertSame($user, $result);
    }

    public function testGetUserByEmailNotFound(): void
    {
        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->with('notfound@example.com')
            ->willReturn(null);

        $result = $this->service->getUserByEmail('notfound@example.com');

        $this->assertNull($result);
    }
}
