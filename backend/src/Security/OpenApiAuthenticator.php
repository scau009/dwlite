<?php

namespace App\Security;

use App\Entity\ApiKeyLog;
use App\Service\OpenApi\ApiKeyService;
use App\Service\OpenApi\SignatureService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class OpenApiAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly ApiKeyService $apiKeyService,
        private readonly SignatureService $signatureService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function supports(Request $request): ?bool
    {
        // Only support requests to /api/v1/open/
        return str_starts_with($request->getPathInfo(), '/api/v1/open/');
    }

    public function authenticate(Request $request): Passport
    {
        $startTime = hrtime(true);

        try {
            // Extract headers (case-insensitive)
            $headers = [
                'x-api-key' => $request->headers->get('X-Api-Key'),
                'x-timestamp' => $request->headers->get('X-Timestamp'),
                'x-nonce' => $request->headers->get('X-Nonce'),
                'x-signature' => $request->headers->get('X-Signature'),
            ];

            // Validate headers presence
            $headerValidation = $this->signatureService->validateHeaders($headers);
            if (!$headerValidation['valid']) {
                throw new CustomUserMessageAuthenticationException($headerValidation['error'], ['_error_code' => 'INVALID_REQUEST']);
            }

            $keyId = $headers['x-api-key'];
            $timestamp = $headers['x-timestamp'];
            $nonce = $headers['x-nonce'];
            $signature = $headers['x-signature'];

            // Get API Key
            $apiKey = $this->apiKeyService->getActiveApiKeyByKeyId($keyId);
            if ($apiKey === null) {
                throw new CustomUserMessageAuthenticationException('Invalid or inactive API key', ['_error_code' => 'INVALID_API_KEY']);
            }

            // Check IP whitelist
            $clientIp = $request->getClientIp() ?? '';
            if (!$this->apiKeyService->isIpAllowed($apiKey, $clientIp)) {
                $this->logger->warning('IP not allowed', [
                    'keyId' => $keyId,
                    'clientIp' => $clientIp,
                ]);
                throw new CustomUserMessageAuthenticationException('IP not allowed', ['_error_code' => 'IP_NOT_ALLOWED']);
            }

            // Get request body
            $body = $request->getContent();

            // Verify signature
            $signatureResult = $this->signatureService->verifySignature(
                $request->getMethod(),
                $request->getPathInfo(),
                $timestamp,
                $nonce,
                $body,
                $signature,
                $apiKey->getKeySecret() // Use plain secret for HMAC
            );

            if (!$signatureResult['valid']) {
                throw new CustomUserMessageAuthenticationException('Signature verification failed', ['_error_code' => $signatureResult['error']]);
            }

            // Create API Key User
            $userBadge = new UserBadge(
                $apiKey->getKeyId(),
                fn () => new ApiKeyUser($apiKey)
            );

            return new SelfValidatingPassport($userBadge);
        } catch (CustomUserMessageAuthenticationException $e) {
            // Log the authentication attempt
            $this->logApiCall(
                $request,
                $headers['x-api-key'] ?? null,
                Response::HTTP_UNAUTHORIZED,
                $e->getMessageData()['_error_code'] ?? 'AUTHENTICATION_ERROR',
                $startTime
            );
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('Authentication error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new CustomUserMessageAuthenticationException('Authentication failed', ['_error_code' => 'AUTHENTICATION_ERROR']);
        }
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Authentication successful, return null to continue with the request
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $errorCode = $exception instanceof CustomUserMessageAuthenticationException
            ? ($exception->getMessageData()['_error_code'] ?? 'AUTHENTICATION_ERROR')
            : 'AUTHENTICATION_ERROR';

        return new JsonResponse([
            'success' => false,
            'error' => [
                'code' => $errorCode,
                'message' => $exception->getMessage(),
            ],
            'requestId' => $request->attributes->get('request_id'),
        ], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Log API call attempt.
     */
    private function logApiCall(
        Request $request,
        ?string $keyId,
        int $statusCode,
        ?string $errorCode,
        int $startTime
    ): void {
        if ($keyId === null) {
            return;
        }

        $apiKey = $this->apiKeyService->getApiKeyByKeyId($keyId);
        if ($apiKey === null) {
            return;
        }

        $endTime = hrtime(true);
        $responseTime = (int) (($endTime - $startTime) / 1_000_000); // Convert to milliseconds

        $log = ApiKeyLog::create(
            $apiKey,
            $request->getPathInfo(),
            $request->getMethod(),
            $request->attributes->get('request_id') ?? '',
            $request->getClientIp() ?? '',
            $statusCode,
            $responseTime,
            $request->headers->get('User-Agent'),
            strlen($request->getContent()),
            $errorCode
        );

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
