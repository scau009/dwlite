<?php

namespace App\Service\Auth;

use App\Entity\EmailVerificationToken;
use App\Entity\User;
use App\Repository\EmailVerificationTokenRepository;
use App\Repository\UserRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class EmailVerificationService
{
    public function __construct(
        private EmailVerificationTokenRepository $tokenRepository,
        private UserRepository $userRepository,
        private MailerInterface $mailer,
        private Environment $twig,
        private string $appUrl = 'http://localhost:8000',
    ) {
    }

    public function sendVerificationEmail(User $user): void
    {
        // Delete any existing tokens for this user
        $this->tokenRepository->deleteByUser($user);

        // Create new token
        $token = new EmailVerificationToken($user);
        $this->tokenRepository->save($token, true);

        // Send email
        $email = (new Email())
            ->to($user->getEmail())
            ->subject('Verify your email address')
            ->html($this->twig->render('emails/verification.html.twig', [
                'user' => $user,
                'token' => $token->getToken(),
                'verifyUrl' => $this->appUrl . '/api/auth/verify-email?token=' . $token->getToken(),
            ]));

        $this->mailer->send($email);
    }

    public function verifyEmail(string $token): User
    {
        $verificationToken = $this->tokenRepository->findByToken($token);

        if ($verificationToken === null) {
            throw new \InvalidArgumentException('Invalid verification token');
        }

        if ($verificationToken->isExpired()) {
            $this->tokenRepository->remove($verificationToken, true);
            throw new \InvalidArgumentException('Verification token has expired');
        }

        $user = $verificationToken->getUser();
        $user->setIsVerified(true);
        $this->userRepository->save($user, true);

        // Delete the token
        $this->tokenRepository->remove($verificationToken, true);

        return $user;
    }

    public function resendVerificationEmail(User $user): void
    {
        if ($user->isVerified()) {
            throw new \InvalidArgumentException('Email is already verified');
        }

        $this->sendVerificationEmail($user);
    }
}
