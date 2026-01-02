<?php

namespace App\Service\Auth;

use App\Dto\Auth\ResetPasswordRequest;
use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use App\Service\Mail\MailServiceInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class PasswordResetService
{
    public function __construct(
        private PasswordResetTokenRepository $tokenRepository,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private MailServiceInterface $mailService,
        private Environment $twig,
        private TranslatorInterface $translator,
        private string $appUrl = 'http://localhost:8000',
    ) {
    }

    public function sendResetEmail(string $email): void
    {
        $user = $this->userRepository->findByEmail($email);

        // Don't reveal if user exists or not
        if ($user === null) {
            return;
        }

        // Delete any existing tokens for this user
        $this->tokenRepository->deleteByUser($user);

        // Create new token
        $token = new PasswordResetToken($user);
        $this->tokenRepository->save($token, true);

        // Send email
        $htmlContent = $this->twig->render('emails/password_reset.html.twig', [
            'user' => $user,
            'token' => $token->getToken(),
            'resetUrl' => $this->appUrl.'/reset-password?token='.$token->getToken(),
        ]);

        $this->mailService->send(
            $user->getEmail(),
            $this->translator->trans('email.password_reset.subject'),
            $htmlContent
        );
    }

    public function resetPassword(ResetPasswordRequest $request): User
    {
        $token = $this->tokenRepository->findByToken($request->token);

        if ($token === null) {
            throw new \InvalidArgumentException($this->translator->trans('auth.reset_password.invalid_token'));
        }

        if ($token->isExpired()) {
            $this->tokenRepository->remove($token, true);
            throw new \InvalidArgumentException($this->translator->trans('auth.reset_password.token_expired'));
        }

        $user = $token->getUser();
        $user->setPassword($this->passwordHasher->hashPassword($user, $request->password));
        $this->userRepository->save($user, true);

        // Delete the token
        $this->tokenRepository->remove($token, true);

        return $user;
    }
}
