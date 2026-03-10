<?php

namespace App\Service\ChannelGateway\Provider\Poizon\Exception;

class PoizonApiException extends \Exception
{
    public function isNoNeedModifyException(): bool
    {
        return $this->code == 20900016;
    }
}
