<?php

namespace App\Service\OpenApi;

use App\Entity\Merchant;
use App\Entity\Warehouse;
use App\Entity\Webhook;
use App\Entity\WebhookDelivery;
use App\Message\DispatchWebhookMessage;
use App\Repository\WebhookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Service for dispatching webhooks.
 */
class WebhookService
{
    public function __construct(
        private readonly WebhookRepository $webhookRepository,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Trigger webhooks for a merchant event.
     */
    public function triggerMerchantEvent(string $event, Merchant $merchant, array $payload): void
    {
        $webhooks = $this->webhookRepository->findActiveByMerchantAndEvent($merchant, $event);

        if (empty($webhooks)) {
            return;
        }

        foreach ($webhooks as $webhook) {
            $this->dispatchWebhook($webhook, $payload);
        }
    }

    /**
     * Trigger webhooks for a warehouse event.
     */
    public function triggerWarehouseEvent(string $event, Warehouse $warehouse, array $payload): void
    {
        $webhooks = $this->webhookRepository->findActiveByWarehouseAndEvent($warehouse, $event);

        if (empty($webhooks)) {
            return;
        }

        foreach ($webhooks as $webhook) {
            $this->dispatchWebhook($webhook, $payload);
        }
    }

    /**
     * Dispatch a webhook to the message queue.
     */
    private function dispatchWebhook(Webhook $webhook, array $payload): void
    {
        // Create delivery record
        $delivery = new WebhookDelivery();
        $delivery->setWebhook($webhook);
        $delivery->setPayload($payload);
        $delivery->setStatus(WebhookDelivery::STATUS_PENDING);

        $this->em->persist($delivery);
        $this->em->flush();

        // Dispatch to message queue
        $this->bus->dispatch(new DispatchWebhookMessage($delivery->getId()));

        $this->logger->info('Webhook dispatched', [
            'webhook_id' => $webhook->getId(),
            'delivery_id' => $delivery->getId(),
            'events' => $webhook->getEvents(),
        ]);
    }
}
