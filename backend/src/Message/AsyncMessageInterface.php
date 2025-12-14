<?php

namespace App\Message;

/**
 * Marker interface for messages that should be handled asynchronously.
 * All messages implementing this interface will be routed to the async transport.
 */
interface AsyncMessageInterface
{
}
