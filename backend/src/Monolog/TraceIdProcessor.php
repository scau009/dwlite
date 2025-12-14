<?php

namespace App\Monolog;

use App\Service\RequestIdService;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class TraceIdProcessor implements ProcessorInterface
{
    public function __construct(
        private RequestIdService $requestIdService,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $extra = $record->extra;
        $extra['trace_id'] = $this->requestIdService->getTraceId();
        $extra['span_id'] = $this->requestIdService->getSpanId();

        $parentSpanId = $this->requestIdService->getParentSpanId();
        if ($parentSpanId !== null) {
            $extra['parent_span_id'] = $parentSpanId;
        }

        return $record->with(extra: $extra);
    }
}
