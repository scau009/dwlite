<?php

namespace App\Service\ChannelGateway\Provider\Poizon;

enum PoizonBiddingTypeEnum: int
{
    case ShipToVerify = 20;
    case Consignment = 25;

    /**
     * @return string
     */
    public function getName(): string
    {
        return match ($this) {
            self::ShipToVerify => 'ShipToVerify',
            self::Consignment => 'Consignment',
        };
    }
}
