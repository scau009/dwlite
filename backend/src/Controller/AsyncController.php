<?php

namespace App\Controller;

use App\Message\ExampleMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

class AsyncController extends AbstractController
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    #[Route('/async/dispatch', name: 'async_dispatch', methods: ['POST'])]
    public function dispatch(Request $request): JsonResponse
    {
        $content = $request->request->get('content', 'Hello from async task!');

        $this->bus->dispatch(new ExampleMessage($content));

        return $this->json([
            'status' => 'dispatched',
            'message' => 'Message has been dispatched to async queue',
        ]);
    }
}
