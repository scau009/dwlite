<?php

namespace App\Service\Auth;

use App\Dto\Auth\ResetPasswordRequest;
use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Twig\Environment;

class PasswordResetService
{
    public function __construct(
        private PasswordResetTokenRepository $tokenRepository,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private MailerInterface $mailer,
        private Environment $twig,
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
        $emailMessage = (new Email())
            ->to($user->getEmail())
            ->subject('Reset your password')
            ->html($this->twig->render('emails/password_reset.html.twig', [
                'user' => $user,
                'token' => $token->getToken(),
                'resetUrl' => $this->appUrl . '/reset-password?token=' . $token->getToken(),
            ]));

        $this->mailer->send($emailMessage);
    }

    public function resetPassword(ResetPasswordRequest $request): User
    {
        $token = $this->tokenRepository->findByToken($request->token);

        if ($token === null) {
            throw new \InvalidArgumentException('Invalid reset token');
        }

        if ($token->isExpired()) {
            $this->tokenRepository->remove($token, true);
            throw new \InvalidArgumentException('Reset token has expired');
        }

        $user = $token->getUser();
        $user->setPassword($this->passwordHasher->hashPassword($user, $request->password));
        $this->userRepository->save($user, true);

        // Delete the token
        $this->tokenRepository->remove($token, true);

        return $user;
    }
}
