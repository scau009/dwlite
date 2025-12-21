<?php

namespace App\Service\Auth;

use App\Dto\Auth\ChangePasswordRequest;
use App\Dto\Auth\RegisterRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AuthService
{
    public function __construct(
        private UserRepository              $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private EmailVerificationService    $emailVerificationService,
        private TranslatorInterface         $translator,
    )
    {
    }

    public function register(RegisterRequest $request): User
    {
        // Check if user already exists
        $existingUser = $this->userRepository->findByEmail($request->email);
        if ($existingUser !== null) {
            throw new \InvalidArgumentException($this->translator->trans('auth.error.user_exists'));
        }

        $user = new User();
        $user->setEmail($request->email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $request->password));
        $user->setRoles(['ROLE_USER'])
            ->setAccountType(User::ACCOUNT_TYPE_MERCHANT);

        $this->userRepository->save($user, true);

        // Send verification email
        $this->emailVerificationService->sendVerificationEmail($user);

        return $user;
    }

    public function changePassword(User $user, ChangePasswordRequest $request): void
    {
        // Verify current password
        if (!$this->passwordHasher->isPasswordValid($user, $request->currentPassword)) {
            throw new \InvalidArgumentException($this->translator->trans('auth.change_password.incorrect_current'));
        }

        // Hash and set new password
        $user->setPassword($this->passwordHasher->hashPassword($user, $request->newPassword));
        $this->userRepository->save($user, true);
    }

    public function getUserByEmail(string $email): ?User
    {
        return $this->userRepository->findByEmail($email);
    }
}
