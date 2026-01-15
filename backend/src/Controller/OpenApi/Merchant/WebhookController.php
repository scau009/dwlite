<?php

namespace App\Controller\OpenApi\Merchant;

use App\Attribute\OpenApiOnly;
use App\Entity\ApiKey;
use App\Entity\Webhook;
use App\Repository\WebhookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for creating/updating webhook.
 */
class WebhookDto
{
    #[Assert\NotBlank]
    #[Assert\Url]
    public string $url;

    /** @var array<string> */
    #[Assert\NotBlank]
    #[Assert\All([
        new Assert\Choice(choices: [
            'fulfillment.created',
            'fulfillment.deadline_approaching',
            'fulfillment.expired',
            'settlement.created',
            'settlement.completed',
            'inbound.receiving_completed',
            'inbound.exception_reported',
        ]),
    ])]
    public array $events;

    #[Assert\Length(max: 64)]
    public ?string $secret = null;
}

/**
 * Merchant Webhook API Controller.
 */
#[Route('/api/v1/open/merchant/webhooks', name: 'open_api_merchant_webhook_')]
#[OpenApiOnly(permission: 'webhook:manage')]
class WebhookController extends AbstractController
{
    public function __construct(
        private readonly WebhookRepository $webhookRepository,
        private readonly EntityManagerInterface $em
    ) {
    }

    /**
     * List webhooks.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var ApiKey $apiKey */
        $apiKey = $request->attributes->get('api_key');

        $webhooks = $this->webhookRepository->findByApiKey($apiKey);

        return $this->json([
            'success' => true,
            'data' => array_map(fn(Webhook $w) => $this->serializeWebhook($w), $webhooks),
            'requestId' => $request->attributes->get('request_id'),
        ]);
    }

    /**
     * Get webhook details.
     */
    #[Route('/{id}', name: 'details', methods: ['GET'])]
    public function details(string $id, Request $request): JsonResponse
    {
        /** @var ApiKey $apiKey */
        $apiKey = $request->attributes->get('api_key');

        $webhook = $this->webhookRepository->find($id);
        if ($webhook === null || $webhook->getApiKey()->getId() !== $apiKey->getId()) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'Webhook not found',
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => $this->serializeWebhook($webhook),
            'requestId' => $request->attributes->get('request_id'),
        ]);
    }

    /**
     * Create webhook.
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] WebhookDto $dto,
        Request $request
    ): JsonResponse {
        /** @var ApiKey $apiKey */
        $apiKey = $request->attributes->get('api_key');

        $webhook = new Webhook();
        $webhook->setApiKey($apiKey);
        $webhook->setUrl($dto->url);
        $webhook->setEvents($dto->events);
        $webhook->setSecret($dto->secret ?? bin2hex(random_bytes(16)));

        $this->em->persist($webhook);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'data' => $this->serializeWebhook($webhook),
            'requestId' => $request->attributes->get('request_id'),
        ], Response::HTTP_CREATED);
    }

    /**
     * Update webhook.
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(
        string $id,
        #[MapRequestPayload] WebhookDto $dto,
        Request $request
    ): JsonResponse {
        /** @var ApiKey $apiKey */
        $apiKey = $request->attributes->get('api_key');

        $webhook = $this->webhookRepository->find($id);
        if ($webhook === null || $webhook->getApiKey()->getId() !== $apiKey->getId()) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'Webhook not found',
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_NOT_FOUND);
        }

        $webhook->setUrl($dto->url);
        $webhook->setEvents($dto->events);
        if ($dto->secret !== null) {
            $webhook->setSecret($dto->secret);
        }

        // Reset failure count when updating failed webhook
        if ($webhook->isFailed()) {
            $webhook->activate();
        }

        $this->em->flush();

        return $this->json([
            'success' => true,
            'data' => $this->serializeWebhook($webhook),
            'requestId' => $request->attributes->get('request_id'),
        ]);
    }

    /**
     * Delete webhook.
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id, Request $request): JsonResponse
    {
        /** @var ApiKey $apiKey */
        $apiKey = $request->attributes->get('api_key');

        $webhook = $this->webhookRepository->find($id);
        if ($webhook === null || $webhook->getApiKey()->getId() !== $apiKey->getId()) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'Webhook not found',
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($webhook);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'requestId' => $request->attributes->get('request_id'),
        ]);
    }

    private function serializeWebhook(Webhook $webhook): array
    {
        return [
            'id' => $webhook->getId(),
            'url' => $webhook->getUrl(),
            'events' => $webhook->getEvents(),
            'secret' => $webhook->getSecret(),
            'status' => $webhook->getStatus(),
            'failureCount' => $webhook->getFailureCount(),
            'lastTriggeredAt' => $webhook->getLastTriggeredAt()?->format(\DateTimeInterface::ATOM),
            'lastSuccessAt' => $webhook->getLastSuccessAt()?->format(\DateTimeInterface::ATOM),
            'createdAt' => $webhook->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $webhook->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
