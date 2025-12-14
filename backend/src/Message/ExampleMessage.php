<?php

namespace App\Message;

class ExampleMessage implements AsyncMessageInterface
{
    public function __construct(
        public readonly string $content,
    ) {
    }
}
