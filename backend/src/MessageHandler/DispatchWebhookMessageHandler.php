<?php

namespace App\MessageHandler;

use App\Entity\WebhookDelivery;
use App\Message\DispatchWebhookMessage;
use App\Repository\WebhookDeliveryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Handler for dispatching webhook deliveries.
 */
#[AsMessageHandler]
class DispatchWebhookMessageHandler
{
    public function __construct(
        private readonly WebhookDeliveryRepository $deliveryRepository,
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $httpClient,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(DispatchWebhookMessage $message): void
    {
        // Find delivery
        $delivery = $this->deliveryRepository->find($message->deliveryId);
        if ($delivery === null) {
            $this->logger->warning('Webhook delivery not found', [
                'delivery_id' => $message->deliveryId,
            ]);

            return;
        }

        $webhook = $delivery->getWebhook();

        // Check if webhook is still active
        if (!$webhook->isActive()) {
            $this->logger->info('Webhook is not active, skipping delivery', [
                'delivery_id' => $delivery->getId(),
                'webhook_id' => $webhook->getId(),
                'webhook_status' => $webhook->getStatus(),
            ]);

            return;
        }

        // Update last triggered time
        $webhook->updateLastTriggeredAt();

        // Build payload
        $payload = $delivery->buildFullPayload();
        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        // Sign payload
        $signature = $webhook->signPayload($jsonPayload);

        $this->logger->info('Attempting webhook delivery', [
            'delivery_id' => $delivery->getId(),
            'webhook_id' => $webhook->getId(),
            'url' => $webhook->getUrl(),
            'event' => $delivery->getEvent(),
            'attempt' => $delivery->getAttempts() + 1,
        ]);

        try {
            // Make HTTP POST request
            $response = $this->httpClient->request('POST', $webhook->getUrl(), [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Event' => $delivery->getEvent(),
                    'X-Webhook-Event-Id' => $delivery->getEventId(),
                ],
                'body' => $jsonPayload,
                'timeout' => 30,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getContent(false); // false = don't throw on error

            // Success (2xx status codes)
            if ($statusCode >= 200 && $statusCode < 300) {
                $delivery->markDelivered($statusCode, $responseBody);
                $webhook->markSuccess();

                $this->em->flush();

                $this->logger->info('Webhook delivered successfully', [
                    'delivery_id' => $delivery->getId(),
                    'webhook_id' => $webhook->getId(),
                    'status_code' => $statusCode,
                ]);

                return;
            }

            // Non-2xx status codes are treated as failures
            $this->handleFailure($delivery, $webhook, $statusCode, $responseBody);
        } catch (TransportExceptionInterface $e) {
            // Network errors (timeout, connection refused, etc.)
            $this->logger->warning('Webhook delivery network error', [
                'delivery_id' => $delivery->getId(),
                'webhook_id' => $webhook->getId(),
                'error' => $e->getMessage(),
            ]);

            $this->handleFailure($delivery, $webhook, 0, $e->getMessage());
        } catch (\Throwable $e) {
            // Other errors (JSON encoding, etc.)
            $this->logger->error('Webhook delivery unexpected error', [
                'delivery_id' => $delivery->getId(),
                'webhook_id' => $webhook->getId(),
                'error' => $e->getMessage(),
            ]);

            $this->handleFailure($delivery, $webhook, 0, $e->getMessage());
        }
    }

    private function handleFailure(
        WebhookDelivery $delivery,
        $webhook,
        int $statusCode,
        ?string $responseBody
    ): void {
        $delivery->markAttemptFailed($statusCode, $responseBody);
        $webhook->incrementFailureCount();

        $this->em->flush();

        $this->logger->warning('Webhook delivery failed', [
            'delivery_id' => $delivery->getId(),
            'webhook_id' => $webhook->getId(),
            'status_code' => $statusCode,
            'attempts' => $delivery->getAttempts(),
            'can_retry' => $delivery->canRetry(),
        ]);

        // Schedule retry if possible
        if ($delivery->canRetry()) {
            $nextRetryAt = $delivery->getNextRetryAt();
            if ($nextRetryAt === null) {
                return;
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $delaySeconds = max(0, $nextRetryAt->getTimestamp() - $now->getTimestamp());

            // Re-dispatch with delay
            $this->bus->dispatch(
                new DispatchWebhookMessage($delivery->getId()),
                [new DelayStamp($delaySeconds * 1000)] // DelayStamp expects milliseconds
            );

            $this->logger->info('Webhook retry scheduled', [
                'delivery_id' => $delivery->getId(),
                'webhook_id' => $webhook->getId(),
                'next_retry_at' => $nextRetryAt->format(\DateTimeInterface::ATOM),
                'delay_seconds' => $delaySeconds,
            ]);
        } else {
            $this->logger->warning('Webhook delivery permanently failed', [
                'delivery_id' => $delivery->getId(),
                'webhook_id' => $webhook->getId(),
                'total_attempts' => $delivery->getAttempts(),
            ]);
        }
    }
}
