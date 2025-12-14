<?php

namespace App\Controller;

use App\Service\MetricsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MetricsController extends AbstractController
{
    public function __construct(
        private MetricsService $metricsService,
    ) {
    }

    #[Route('/metrics', name: 'metrics', methods: ['GET'])]
    public function index(): Response
    {
        return new Response(
            $this->metricsService->render(),
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain; charset=utf-8']
        );
    }
}
