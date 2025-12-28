<?php

namespace App\Dto\Outbound;

use Symfony\Component\Validator\Constraints as Assert;

class CreateOutboundOrderRequest
{
    #[Assert\NotBlank(message: 'validation.warehouse_id_required')]
    public string $warehouseId = '';

    #[Assert\NotBlank(message: 'validation.receiver_name_required')]
    #[Assert\Length(max: 50, maxMessage: 'validation.receiver_name_max_50')]
    public string $receiverName = '';

    #[Assert\NotBlank(message: 'validation.receiver_phone_required')]
    #[Assert\Length(max: 30, maxMessage: 'validation.receiver_phone_max_30')]
    public string $receiverPhone = '';

    #[Assert\NotBlank(message: 'validation.receiver_address_required')]
    #[Assert\Length(max: 500, maxMessage: 'validation.receiver_address_max_500')]
    public string $receiverAddress = '';

    #[Assert\Length(max: 20, maxMessage: 'validation.receiver_postal_code_max_20')]
    public ?string $receiverPostalCode = null;

    #[Assert\Length(max: 500, maxMessage: 'validation.remark_max_500')]
    public ?string $remark = null;
}
