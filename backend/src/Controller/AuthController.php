<?php

namespace App\Controller;

use App\Dto\Auth\ChangePasswordRequest;
use App\Dto\Auth\RegisterRequest;
use App\Dto\Auth\ResetPasswordRequest;
use App\Entity\User;
use App\Service\Auth\AuthService;
use App\Service\Auth\EmailVerificationService;
use App\Service\Auth\PasswordResetService;
use App\Service\Auth\RefreshTokenService;
use App\Service\Auth\TokenBlacklistService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private AuthService $authService,
        private EmailVerificationService $emailVerificationService,
        private PasswordResetService $passwordResetService,
        private RefreshTokenService $refreshTokenService,
        private TokenBlacklistService $tokenBlacklistService,
        private JWTTokenManagerInterface $jwtManager,
        private ValidatorInterface $validator,
        private string $frontendUrl = 'http://localhost:5173',
    ) {
    }

    #[Route('/register', name: 'auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $dto = new RegisterRequest();
        $dto->email = $data['email'] ?? '';
        $dto->password = $data['password'] ?? '';

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json([
                'error' => 'Validation failed',
                'violations' => $this->formatValidationErrors($errors),
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $this->authService->register($dto);
            return $this->json([
                'message' => 'Registration successful. Please check your email to verify your account.',
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                ],
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
    }

    #[Route('/verify-email', name: 'auth_verify_email', methods: ['POST', 'GET'])]
    public function verifyEmail(Request $request): Response
    {
        $token = $request->query->get('token') ?? $request->request->get('token');
        $loginUrl = $this->frontendUrl . '/login';

        if (empty($token)) {
            return $this->render('emails/verification_result.html.twig', [
                'success' => false,
                'error' => 'Invalid verification link. No token provided.',
                'redirectUrl' => $loginUrl,
            ], new Response('', Response::HTTP_BAD_REQUEST));
        }

        try {
            $this->emailVerificationService->verifyEmail($token);
            return $this->render('emails/verification_result.html.twig', [
                'success' => true,
                'redirectUrl' => $loginUrl,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->render('emails/verification_result.html.twig', [
                'success' => false,
                'error' => $e->getMessage(),
                'redirectUrl' => $loginUrl,
            ], new Response('', Response::HTTP_BAD_REQUEST));
        }
    }

    #[Route('/refresh', name: 'auth_refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $refreshToken = $data['refresh_token'] ?? '';

        if (empty($refreshToken)) {
            return $this->json(['error' => 'Refresh token is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $tokens = $this->refreshTokenService->refresh($refreshToken);
            return $this->json($tokens);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNAUTHORIZED);
        }
    }

    #[Route('/logout', name: 'auth_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $token = $request->headers->get('Authorization');
        if ($token && str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
            try {
                $payload = $this->jwtManager->parse($token);
                if (isset($payload['exp'])) {
                    $tokenId = $payload['jti'] ?? $this->generateTokenId($payload);
                    $this->tokenBlacklistService->blacklist($tokenId, $payload['exp']);
                }
            } catch (\Exception $e) {
                // Token parsing failed, ignore
            }
        }

        return $this->json(['message' => 'Logged out successfully']);
    }

    private function generateTokenId(array $payload): string
    {
        return hash('sha256', json_encode([
            'iat' => $payload['iat'] ?? 0,
            'exp' => $payload['exp'] ?? 0,
            'email' => $payload['email'] ?? '',
        ]));
    }

    #[Route('/forgot-password', name: 'auth_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? '';

        if (empty($email)) {
            return $this->json(['error' => 'Email is required'], Response::HTTP_BAD_REQUEST);
        }

        $this->passwordResetService->sendResetEmail($email);

        // Always return success to prevent email enumeration
        return $this->json([
            'message' => 'If an account with that email exists, a password reset link has been sent.',
        ]);
    }

    #[Route('/reset-password', name: 'auth_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $dto = new ResetPasswordRequest();
        $dto->token = $data['token'] ?? '';
        $dto->password = $data['password'] ?? '';

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json([
                'error' => 'Validation failed',
                'violations' => $this->formatValidationErrors($errors),
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->passwordResetService->resetPassword($dto);
            return $this->json(['message' => 'Password reset successfully']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/change-password', name: 'auth_change_password', methods: ['PUT'])]
    public function changePassword(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $dto = new ChangePasswordRequest();
        $dto->currentPassword = $data['currentPassword'] ?? '';
        $dto->newPassword = $data['newPassword'] ?? '';

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json([
                'error' => 'Validation failed',
                'violations' => $this->formatValidationErrors($errors),
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->authService->changePassword($user, $dto);
            return $this->json(['message' => 'Password changed successfully']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/me', name: 'auth_me', methods: ['GET'])]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'isVerified' => $user->isVerified(),
            'createdAt' => $user->getCreatedAt()->format('c'),
        ]);
    }

    private function formatValidationErrors($errors): array
    {
        $violations = [];
        foreach ($errors as $error) {
            $violations[$error->getPropertyPath()] = $error->getMessage();
        }
        return $violations;
    }
}
