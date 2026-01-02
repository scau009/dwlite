<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * 通用选项接口.
 */
#[Route('/api/common')]
#[IsGranted('ROLE_USER')]
class CommonController extends AbstractController
{
    /**
     * 物流公司列表.
     */
    private const CARRIERS = [
        ['code' => 'SF', 'name' => '顺丰速运'],
        ['code' => 'YTO', 'name' => '圆通速递'],
        ['code' => 'ZTO', 'name' => '中通快递'],
        ['code' => 'STO', 'name' => '申通快递'],
        ['code' => 'YD', 'name' => '韵达速递'],
        ['code' => 'JTSD', 'name' => '极兔速递'],
        ['code' => 'EMS', 'name' => '邮政EMS'],
        ['code' => 'DBKD', 'name' => '德邦快递'],
        ['code' => 'JD', 'name' => '京东物流'],
        ['code' => 'FEDEX', 'name' => 'FedEx联邦快递'],
        ['code' => 'UPS', 'name' => 'UPS'],
        ['code' => 'DHL', 'name' => 'DHL'],
        ['code' => 'TNT', 'name' => 'TNT'],
        ['code' => 'OTHER', 'name' => '其他'],
    ];

    /**
     * 获取物流公司选项列表.
     */
    #[Route('/carriers', name: 'common_carrier_options', methods: ['GET'])]
    public function getCarrierOptions(): JsonResponse
    {
        return $this->json([
            'data' => self::CARRIERS,
        ]);
    }
}
