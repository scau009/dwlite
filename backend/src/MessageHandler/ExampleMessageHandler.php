<?php

namespace App\MessageHandler;

use App\Message\ExampleMessage;
use App\Service\RequestIdService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ExampleMessageHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private RequestIdService $requestIdService,
    ) {
    }

    public function __invoke(ExampleMessage $message): void
    {
        // trace_id is automatically included in logs via TraceIdProcessor
        // Here we explicitly log it for demonstration
        $this->logger->info('Processing async message', [
            'content' => $message->content,
            'trace_id' => $this->requestIdService->getTraceId(),
            'parent_span_id' => $this->requestIdService->getParentSpanId(),
        ]);

        // Simulate time-consuming task
        sleep(2);

        $this->logger->info('Async message processed successfully');
    }
}
