<?php

namespace App\Service;

use Symfony\Component\Uid\Ulid;

class RequestIdService
{
    private ?string $traceId = null;
    private ?string $spanId = null;
    private ?string $parentSpanId = null;

    public function getTraceId(): string
    {
        if ($this->traceId === null) {
            $this->traceId = (string) new Ulid();
        }
        return $this->traceId;
    }

    public function getSpanId(): string
    {
        if ($this->spanId === null) {
            $this->spanId = substr((string) new Ulid(), -16);
        }
        return $this->spanId;
    }

    public function setFromHeaders(?string $traceId, ?string $parentSpanId): void
    {
        if ($traceId !== null) {
            $this->traceId = $traceId;
        }
        $this->parentSpanId = $parentSpanId;
    }

    public function getParentSpanId(): ?string
    {
        return $this->parentSpanId;
    }

    public function reset(): void
    {
        $this->traceId = null;
        $this->spanId = null;
        $this->parentSpanId = null;
    }
}
