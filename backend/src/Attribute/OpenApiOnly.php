<?php

namespace App\Attribute;

/**
 * Marks a controller or action as Open API only.
 * Requires API Key authentication.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class OpenApiOnly
{
    public function __construct(
        public readonly ?string $permission = null
    ) {
    }
}
