<?php

namespace App\Controller\OpenApi\Warehouse;

use App\Attribute\OpenApiOnly;
use App\Entity\ApiKey;
use App\Entity\Webhook;
use App\Repository\WebhookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Warehouse Webhook API Controller.
 */
#[Route('/api/v1/open/warehouse/webhooks', name: 'open_api_warehouse_webhooks_')]
#[OpenApiOnly(permission: 'webhook:manage')]
class WebhookController extends AbstractController
{
    public function __construct(
        private readonly WebhookRepository $webhookRepository,
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator
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

        $webhooks = $this->webhookRepository->findBy(
            ['apiKey' => $apiKey],
            ['createdAt' => 'DESC']
        );

        return $this->json([
            'success' => true,
            'data' => array_map(fn(Webhook $webhook) => $this->serializeWebhook($webhook), $webhooks),
            'requestId' => $request->attributes->get('request_id'),
        ]);
    }

    /**
     * Create webhook.
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var ApiKey $apiKey */
        $apiKey = $request->attributes->get('api_key');

        // Parse and validate request
        $data = json_decode($request->getContent(), true);

        $constraints = new Assert\Collection([
            'url' => [new Assert\NotBlank(), new Assert\Url()],
            'events' => [new Assert\NotBlank(), new Assert\Type('array'), new Assert\Count(['min' => 1])],
            'secret' => [new Assert\Optional([new Assert\Length(['min' => 16, 'max' => 64])])],
        ]);

        $violations = $this->validator->validate($data, $constraints);
        if (count($violations) > 0) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_REQUEST',
                    'message' => 'Validation failed',
                    'details' => array_map(fn($v) => $v->getMessage(), iterator_to_array($violations)),
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_BAD_REQUEST);
        }

        // Create webhook
        $webhook = new Webhook();
        $webhook->setApiKey($apiKey);
        $webhook->setUrl($data['url']);
        $webhook->setEvents($data['events']);

        if (isset($data['secret']) && $data['secret'] !== '') {
            $webhook->setSecret($data['secret']);
        } else {
            // Generate a random secret if not provided
            $webhook->setSecret(bin2hex(random_bytes(32)));
        }

        $this->em->persist($webhook);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'data' => $this->serializeWebhook($webhook),
            'requestId' => $request->attributes->get('request_id'),
        ], Response::HTTP_CREATED);
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
     * Update webhook.
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
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

        // Parse and validate request
        $data = json_decode($request->getContent(), true);

        $constraints = new Assert\Collection([
            'url' => [new Assert\Optional([new Assert\Url()])],
            'events' => [new Assert\Optional([new Assert\Type('array'), new Assert\Count(['min' => 1])])],
            'secret' => [new Assert\Optional([new Assert\Length(['min' => 16, 'max' => 64])])],
        ]);

        $violations = $this->validator->validate($data, $constraints);
        if (count($violations) > 0) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_REQUEST',
                    'message' => 'Validation failed',
                    'details' => array_map(fn($v) => $v->getMessage(), iterator_to_array($violations)),
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_BAD_REQUEST);
        }

        // Update webhook
        if (isset($data['url'])) {
            $webhook->setUrl($data['url']);
        }
        if (isset($data['events'])) {
            $webhook->setEvents($data['events']);
        }
        if (isset($data['secret'])) {
            $webhook->setSecret($data['secret']);
        }

        // Reset failure count if webhook is being updated
        if ($webhook->getStatus() === Webhook::STATUS_FAILED) {
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
            'message' => 'Webhook deleted successfully',
            'requestId' => $request->attributes->get('request_id'),
        ]);
    }

    /**
     * Serialize webhook for response.
     */
    private function serializeWebhook(Webhook $webhook): array
    {
        return [
            'id' => $webhook->getId(),
            'url' => $webhook->getUrl(),
            'events' => $webhook->getEvents(),
            'status' => $webhook->getStatus(),
            'failureCount' => $webhook->getFailureCount(),
            'lastTriggeredAt' => $webhook->getLastTriggeredAt()?->format(\DateTimeInterface::ATOM),
            'createdAt' => $webhook->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $webhook->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
