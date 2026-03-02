<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\SalesChannel;
use App\Repository\SalesChannelRepository;
use App\Service\ChannelGateway\ChannelGatewayContext;
use App\Service\ChannelGateway\Provider\Poizon\PoizonApiClient;
use App\Service\ChannelGateway\Provider\Poizon\PoizonGateway;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:poizon:test-connection',
    description: 'Test Poizon (得物) API connectivity and authentication'
)]
class TestPoizonConnectionCommand extends Command
{
    public function __construct(
        private readonly PoizonGateway $gateway,
        private readonly PoizonApiClient $apiClient,
        private readonly SalesChannelRepository $salesChannelRepo,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('app-key', null, InputOption::VALUE_REQUIRED, 'Poizon App Key (overrides DB config)')
            ->addOption('app-secret', null, InputOption::VALUE_REQUIRED, 'Poizon App Secret (overrides DB config)')
            ->addOption('brand-ids', null, InputOption::VALUE_OPTIONAL, 'Comma-separated brand IDs to query', '1')
            ->addOption('language', null, InputOption::VALUE_OPTIONAL, 'Language for brand query', 'en');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Poizon (得物) Connection Test');

        [$appKey, $appSecret] = $this->resolveCredentials($io, $input);

        if ($appKey === null || $appSecret === null) {
            return Command::FAILURE;
        }

        $io->info([
            'App Key: '.$appKey,
            'App Secret: '.str_repeat('*', max(0, strlen($appSecret) - 4)).substr($appSecret, -4),
        ]);

        // ---- Step 1: test sign generation ----
        $io->section('Step 1: Signature Generation');
        $testParams = ['app_key' => $appKey, 'language' => 'en'];
        $sign = $this->apiClient->createSign($testParams, $appSecret);
        $io->success('Sign generated: '.$sign);

        // ---- Step 2: real API call via testConnection() ----
        $io->section('Step 2: API Connectivity (testConnection)');

        $salesChannel = $this->buildInMemorySalesChannel($appKey, $appSecret);
        $context = new ChannelGatewayContext($salesChannel);

        $connected = $this->gateway->testConnection($context);

        if (!$connected) {
            $io->error('testConnection() returned false — check credentials or network.');

            return Command::FAILURE;
        }

        $io->success('testConnection() passed.');

        // ---- Step 3: raw brand query ----
        $io->section('Step 3: Raw Brand Query (getBrandsByIds)');

        $brandIds = array_map(
            'intval',
            explode(',', (string) $input->getOption('brand-ids'))
        );
        $language = (string) $input->getOption('language');

        try {
            $response = $this->apiClient->getBrandsByIds($appKey, $appSecret, $brandIds, $language);

            $io->success('Response received (HTTP 200)');
            $io->writeln(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            if ($io->isVerbose()) {
                $io->section('Response code: '.($response['code'] ?? 'n/a'));
            }
        } catch (\Throwable $e) {
            $io->error('Brand query failed: '.$e->getMessage());

            if ($io->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }

            return Command::FAILURE;
        }

        $io->success('All checks passed — Poizon API is reachable and credentials are valid.');

        return Command::SUCCESS;
    }

    /**
     * Resolve credentials from --app-key/--app-secret options first,
     * then fall back to the POIZON SalesChannel record in DB.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveCredentials(SymfonyStyle $io, InputInterface $input): array
    {
        $appKey = $input->getOption('app-key');
        $appSecret = $input->getOption('app-secret');

        if ($appKey !== null && $appSecret !== null) {
            $io->note('Using credentials from command-line options.');

            return [$appKey, $appSecret];
        }

        // Fall back to DB
        $salesChannel = $this->salesChannelRepo->findOneBy(['code' => 'POIZON']);

        if ($salesChannel === null) {
            $io->error([
                'No credentials provided and no POIZON SalesChannel found in DB.',
                'Pass --app-key and --app-secret, or create a SalesChannel record with code=POIZON.',
            ]);

            return [null, null];
        }

        $appKey = $salesChannel->getConfigValue('app_key');
        $appSecret = $salesChannel->getConfigValue('app_secret');

        if (empty($appKey) || empty($appSecret)) {
            $io->error('POIZON SalesChannel found in DB but app_key or app_secret is missing from its config.');

            return [null, null];
        }

        $io->note('Using credentials from DB (SalesChannel code=POIZON).');

        return [(string) $appKey, (string) $appSecret];
    }

    /**
     * Build a transient in-memory SalesChannel to avoid a DB dependency
     * when credentials are passed directly on the CLI.
     */
    private function buildInMemorySalesChannel(string $appKey, string $appSecret): SalesChannel
    {
        $salesChannel = new SalesChannel();
        $salesChannel->setCode('POIZON');
        $salesChannel->setName('Poizon (得物)');
        $salesChannel->setCurrency('CNY');
        $salesChannel->setStatus('active');
        $salesChannel->setConfig([
            'app_key' => $appKey,
            'app_secret' => $appSecret,
        ]);

        return $salesChannel;
    }
}
