<?php

namespace App\Entity;

use App\Stamp\MessageContentStamp;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Messenger\Envelope;
use Zenstruck\Messenger\Monitor\History\Model\ProcessedMessage as BaseProcessedMessage;
use Zenstruck\Messenger\Monitor\History\Model\Results;

#[ORM\Entity(readOnly: true)]
#[ORM\Table('processed_messages')]
class ProcessedMessage extends BaseProcessedMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $messageContent = null;

    public function __construct(Envelope $envelope, Results $results, ?\Throwable $exception = null)
    {
        parent::__construct($envelope, $results, $exception);

        // Extract message content from stamp if available
        if ($stamp = $envelope->last(MessageContentStamp::class)) {
            $this->messageContent = $stamp->getContent();
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function getMessageContent(): ?string
    {
        return $this->messageContent;
    }

    /**
     * Returns the message content as a decoded array/object.
     */
    public function getMessageContentDecoded(): mixed
    {
        if ($this->messageContent === null) {
            return null;
        }

        return json_decode($this->messageContent, true);
    }
}
