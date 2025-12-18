<?php

namespace App\Service\Auth;

use App\Entity\EmailVerificationToken;
use App\Entity\User;
use App\Repository\EmailVerificationTokenRepository;
use App\Repository\UserRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class EmailVerificationService
{
    public function __construct(
        private EmailVerificationTokenRepository $tokenRepository,
        private UserRepository $userRepository,
        private MailerInterface $mailer,
        private Environment $twig,
        private TranslatorInterface $translator,
        private string $frontendUrl = 'http://localhost:5173',
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
                'verifyUrl' => $this->frontendUrl . '/verify-email?token=' . $token->getToken(),
            ]));

        $this->mailer->send($email);
    }

    public function verifyEmail(string $token): User
    {
        $verificationToken = $this->tokenRepository->findByToken($token);

        if ($verificationToken === null) {
            throw new \InvalidArgumentException($this->translator->trans('auth.verify_email.invalid_token'));
        }

        if ($verificationToken->isExpired()) {
            $this->tokenRepository->remove($verificationToken, true);
            throw new \InvalidArgumentException($this->translator->trans('auth.verify_email.token_expired'));
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
            throw new \InvalidArgumentException($this->translator->trans('auth.verify_email.already_verified'));
        }

        $this->sendVerificationEmail($user);
    }
}
