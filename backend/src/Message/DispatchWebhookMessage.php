<?php

namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

/**
 * Message to dispatch a webhook delivery.
 */
#[AsMessage]
class DispatchWebhookMessage
{
    public function __construct(
        public readonly string $deliveryId
    ) {
    }
}
