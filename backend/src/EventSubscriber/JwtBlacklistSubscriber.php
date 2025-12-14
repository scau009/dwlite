<?php

namespace App\EventSubscriber;

use App\Service\Auth\TokenBlacklistService;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class JwtBlacklistSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TokenBlacklistService $blacklistService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::JWT_DECODED => 'onJwtDecoded',
        ];
    }

    public function onJwtDecoded(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();

        // Generate token ID from payload (same logic as logout)
        $tokenId = $payload['jti'] ?? $this->generateTokenId($payload);

        if ($this->blacklistService->isBlacklisted($tokenId)) {
            $event->markAsInvalid();
        }
    }

    private function generateTokenId(array $payload): string
    {
        // Generate a consistent ID from token payload
        return hash('sha256', json_encode([
            'iat' => $payload['iat'] ?? 0,
            'exp' => $payload['exp'] ?? 0,
            'email' => $payload['email'] ?? '',
        ]));
    }
}
