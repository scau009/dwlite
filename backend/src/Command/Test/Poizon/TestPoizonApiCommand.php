<?php

declare(strict_types=1);

namespace App\Command\Test\Poizon;

use App\Repository\ChannelProductRepository;
use App\Service\ChannelGateway\Provider\Poizon\PoizonApiClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

#[AsCommand(name: 'app:test_poizon_api', description: '测试Poizon API')]
class TestPoizonApiCommand extends Command
{
    private string $appKey;
    private string $appSecret;

    private PoizonApiClient $apiClient;
    private ChannelProductRepository $channelProductRepository;

    public function __construct(PoizonApiClient $apiClient, ChannelProductRepository $channelProductRepository)
    {
        parent::__construct();
        $this->apiClient = $apiClient;
        $this->channelProductRepository = $channelProductRepository;
    }

    protected function configure()
    {
        $this->addOption('appKey', '', InputOption::VALUE_REQUIRED, 'Poizon App Key')
            ->addOption('appSecret', '', InputOption::VALUE_REQUIRED, 'Poizon App Secret')
            ->addOption('method', '', InputOption::VALUE_REQUIRED, 'Poizon API Method')
            ->addOption('params', 'p', InputOption::VALUE_REQUIRED, 'Poizon API Params');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->appKey = $input->getOption('appKey');
        $this->appSecret = $input->getOption('appSecret');
        $method = $input->getOption('method');
        $params = $input->getOption('params');
        if ($params) {
            $params = json_decode($params, true);
        }
        if (method_exists($this, $method)) {
            $this->{$method}($params, $output);
        } else {
            $output->writeln(sprintf('Method %s not found', $method));
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }

    private function getBrands(array $params, OutputInterface $output): void
    {
        if (!isset($params['brandIds']) || !is_array($params['brandIds'])) {
            $output->writeln('brandIds is required');
            return;
        }
        $brands = $this->apiClient->getBrandsByIds($this->appKey, $this->appSecret, $params['brandIds']);
        $output->writeln(json_encode($brands, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * --method=getSpuInformationByBrandId
     * --params={\"brandIdList\":[2]}
     * @param array $params
     * @param OutputInterface $output
     * @return void
     */
    private function getSpuInformationByBrandId(array $params, OutputInterface $output): void
    {
        if (!isset($params['brandIdList']) || !is_array($params['brandIdList'])) {
            $output->writeln('brandIdList is required');
            return;
        }
        $response = $this->apiClient->querySpuInformationByBrandId($this->appKey, $this->appSecret, $params['brandIdList']);
        $output->writeln(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * --method=getSkuInformationByGlobalSpuId
     * --params={\"globalSpuIds\":[12003928460]}
     * @param array $params
     * @param OutputInterface $output
     * @return void
     */
    private function getSkuInformationByGlobalSpuId(array $params, OutputInterface $output): void
    {
        if (!isset($params['globalSpuIds'])) {
            $output->writeln('globalSpuIds is required');
            return;
        }
        $response = $this->apiClient->getSkuInformationByGlobalSpuId($this->appKey, $this->appSecret, $params['globalSpuIds']);
        $output->writeln(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * --method=queryLowestPrice
     * --params={\"globalSkuId\":12800767421,\"biddingType\":20}
     * @param array $params
     * @param OutputInterface $output
     * @return void
     */
    private function queryLowestPrice(array $params, OutputInterface $output): void
    {
        if (!isset($params['skuId']) && !isset($params['globalSkuId'])) {
            $output->writeln('skuId or globalSkuId is required');
            return;
        }
        if (!isset($params['biddingType'])) {
            $output->writeln('biddingType is required');
            return;
        }

        $response = $this->apiClient->queryLowestPrice($this->appKey, $this->appSecret, $params['skuId'] ?? null, $params['globalSkuId'] ?? null, $params['biddingType']);
        $output->writeln(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     *
     * --method=manualListing
     * --params={\"globalSkuId\":12800767421,\"price\":146100,\"quantity\":1,\"countryCode\":\"HK\",\"deliveryCountryCode\":\"HK\",\"currency\":\"CNY\",\"language\":\"en\",\"timeZone\":\"Asia/Shanghai\"}
     * @param array $params
     * @param OutputInterface $output
     * @return void
     */
    private function manualListing(array $params, OutputInterface $output): void
    {
        if (!isset($params['globalSkuId']) && !isset($params['skuId'])) {
            $output->writeln('globalSkuIds is required');
            return;
        }
        if (!isset($params['price'])) {
            $output->writeln('price is required');
            return;
        }
        if (isset($params['quantity'])) {
            $params['quantity'] = (int)$params['quantity'];
        }
        $response = $this->apiClient->manualListing($this->appKey, $this->appSecret,
            $params['price'],
            $params['quantity'] ?? 1,
            $params['countryCode'],
            $params['deliveryCountryCode'],
            $params['currency'],
            $params['language'] ?? 'en',
            $params['globalSkuId'] ?? null,
            $params['skuId'] ?? null);
        $output->writeln(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * --method=queryListing
     * --params={\"sellerBiddingNoList\":[151220034159712368]}
      * @param array $params
     * @param OutputInterface $output
     * @return void
     */
    private function queryListing(array $params, OutputInterface $output): void
    {
        $response = $this->apiClient->queryListingList($this->appKey, $this->appSecret, $params['sellerBiddingNoList'] ?? null, $params['skuId'] ?? null,$params['globalSkuId'] ?? null);
        $output->writeln(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * --method=updateListing
     * --params={\"sellerBiddingNo\":\"151220034162611765\",\"skuId\":683390522,\"price\":84000,\"quantity\":1,\"oldQuantity\":1,\"countryCode\":\"HK\",\"deliveryCountryCode\":\"HK\",\"currency\":\"CNY\",\"language\":\"en\",\"timeZone\":\"Asia/Shanghai\"}
     * @param array $params
     * @param OutputInterface $output
     * @return void
     */
    private function updateListing(array $params, OutputInterface $output): void
    {
        if (!isset($params['sellerBiddingNo'])) {
            $output->writeln('sellerBiddingNo is required');
            return;
        }
        $response = $this->apiClient->updateManualListing($this->appKey, $this->appSecret,
            $params['sellerBiddingNo'],
            $params['globalSkuId'] ?? null,
            $params['skuId'] ?? null,
            $params['price'],
            $params['quantity'] ?? 1,
            $params['oldQuantity'] ?? 1,
            $params['countryCode'],
            $params['deliveryCountryCode'],
            $params['currency']
        );
        $output->writeln(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * --method=cancelListing
     * --params={\"sellerBiddingNo\":\"151220034159738184\"}
     * @param array $params
     * @param OutputInterface $output
     * @return void
     */
    private function cancelListing(array $params, OutputInterface $output): void
    {
        if (!isset($params['sellerBiddingNo'])) {
            $output->writeln('sellerBiddingNo is required');
            return;
        }
        $response = $this->apiClient->cancelListing($this->appKey, $this->appSecret, $params['sellerBiddingNo']);
        $output->writeln(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * --method=queryOrderList
     * --params={\"orderStatusList\":[\"PENDING\",\"SHIPPED\",\"CANCELLED\"]}
     * @param array $params
     * @param OutputInterface $output
     * @return void
     */
    private function queryOrderList(array $params, OutputInterface $output): void
    {
        $response = $this->apiClient->queryOrders($this->appKey, $this->appSecret);
        $output->writeln(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * --method=querySkuInfoByArticleNumber
     * --params={\"articleNumber\":\"12800767421\"}
     * @param array $params
     * @param OutputInterface $output
     * @return void
     */
    private function querySkuInfoByArticleNumber(array $params, OutputInterface $output): void
    {
        $response = $this->apiClient->querySkuInfoByArticleNumber($this->appKey, $this->appSecret, $params['articleNumber']);
        $output->writeln(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
