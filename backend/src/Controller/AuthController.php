<?php

namespace App\Controller;

use App\Dto\Auth\ChangePasswordRequest;
use App\Dto\Auth\ForgotPasswordRequest;
use App\Dto\Auth\RefreshTokenRequest;
use App\Dto\Auth\RegisterRequest;
use App\Dto\Auth\ResetPasswordRequest;
use App\Dto\Auth\VerifyEmailRequest;
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
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

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
    ) {
    }

    #[Route('/register', name: 'auth_register', methods: ['POST'])]
    public function register(#[MapRequestPayload] RegisterRequest $dto): JsonResponse
    {
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
    public function verifyEmail(Request $request): JsonResponse
    {
        // 支持 GET 请求中的 query 参数
        $token = $request->query->get('token');
        if (!$token) {
            $data = json_decode($request->getContent(), true) ?? [];
            $token = $data['token'] ?? '';
        }

        if (empty($token)) {
            return $this->json([
                'error' => 'Validation failed',
                'violations' => ['token' => 'Token is required'],
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $this->emailVerificationService->verifyEmail($token);
            return $this->json([
                'message' => 'Email verified successfully',
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/refresh', name: 'auth_refresh', methods: ['POST'])]
    public function refresh(#[MapRequestPayload] RefreshTokenRequest $dto): JsonResponse
    {
        try {
            $tokens = $this->refreshTokenService->refresh($dto->refreshToken);
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
    public function forgotPassword(#[MapRequestPayload] ForgotPasswordRequest $dto): JsonResponse
    {
        $this->passwordResetService->sendResetEmail($dto->email);

        // Always return success to prevent email enumeration
        return $this->json([
            'message' => 'If an account with that email exists, a password reset link has been sent.',
        ]);
    }

    #[Route('/reset-password', name: 'auth_reset_password', methods: ['POST'])]
    public function resetPassword(#[MapRequestPayload] ResetPasswordRequest $dto): JsonResponse
    {
        try {
            $this->passwordResetService->resetPassword($dto);
            return $this->json(['message' => 'Password reset successfully']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/change-password', name: 'auth_change_password', methods: ['PUT'])]
    public function changePassword(
        #[MapRequestPayload] ChangePasswordRequest $dto,
        #[CurrentUser] User $user
    ): JsonResponse {
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
            'accountType' => $user->getAccountType(),
            'createdAt' => $user->getCreatedAt()->format('c'),
        ]);
    }
}
