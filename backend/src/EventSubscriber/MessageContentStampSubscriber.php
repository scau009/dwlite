<?php

namespace App\EventSubscriber;

use App\Stamp\MessageContentStamp;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Adds a MessageContentStamp containing the serialized message content
 * when a message is received by a worker, for monitoring purposes.
 */
class MessageContentStampSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SerializerInterface $serializer,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageReceivedEvent::class => ['onMessageReceived', 100],
        ];
    }

    public function onMessageReceived(WorkerMessageReceivedEvent $event): void
    {
        $envelope = $event->getEnvelope();

        // Skip if already has content stamp
        if ($envelope->last(MessageContentStamp::class)) {
            return;
        }

        $message = $envelope->getMessage();

        try {
            $content = $this->serializer->serialize($message, 'json');
        } catch (\Throwable) {
            // Fallback to basic serialization if JSON serialization fails
            $content = json_encode($this->extractMessageData($message), JSON_UNESCAPED_UNICODE);
        }

        $event->addStamps(new MessageContentStamp($content ?: '{}'));
    }

    /**
     * Extract message data using reflection as fallback.
     */
    private function extractMessageData(object $message): array
    {
        $data = ['_class' => $message::class];

        try {
            $reflection = new \ReflectionClass($message);

            foreach ($reflection->getProperties() as $property) {
                $value = $property->getValue($message);
                $data[$property->getName()] = $this->normalizeValue($value);
            }
        } catch (\Throwable) {
            // Ignore reflection errors
        }

        return $data;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }

            return '[object '.$value::class.']';
        }

        if (is_array($value)) {
            return array_map(fn ($v) => $this->normalizeValue($v), $value);
        }

        return $value;
    }
}
