<?php

namespace App\Message;

/**
 * Message to dispatch a webhook delivery.
 */
class DispatchWebhookMessage
{
    public function __construct(
        public readonly string $deliveryId
    ) {
    }
}
