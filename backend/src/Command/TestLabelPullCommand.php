<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\OrderRepository;
use App\Service\ChannelGateway\ChannelGatewayRegistry;
use App\Service\ChannelGateway\Provider\KicksCrew\KicksCrewGateway;
use App\Service\CosService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Ulid;

#[AsCommand(
    name: 'app:test-label-pull',
    description: 'Test shipping label pull from a sales channel'
)]
class TestLabelPullCommand extends Command
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly ChannelGatewayRegistry $gatewayRegistry,
        private readonly CosService $cosService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('order-id', InputArgument::REQUIRED, 'Order ID (internal ULID or external order ID)')
            ->addOption('external', null, InputOption::VALUE_NONE, 'Treat order-id as external order ID from the channel')
            ->addOption('save-local', null, InputOption::VALUE_NONE, 'Save PDF to local filesystem (var/labels/)')
            ->addOption('save-cos', null, InputOption::VALUE_NONE, 'Upload PDF to COS and update order label field')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Custom output path for local save')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only fetch label but do not save anywhere');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $orderId = $input->getArgument('order-id');
        $isExternal = $input->getOption('external');
        $saveLocal = $input->getOption('save-local');
        $saveCos = $input->getOption('save-cos');
        $outputPath = $input->getOption('output');
        $dryRun = $input->getOption('dry-run');

        $io->title('Test Shipping Label Pull');

        // Find order
        $order = $this->findOrder($orderId, $isExternal);
        if ($order === null) {
            $io->error(sprintf(
                'Order not found: %s (searched by %s)',
                $orderId,
                $isExternal ? 'external order ID' : 'internal ID'
            ));

            return Command::FAILURE;
        }

        $io->success('Order found');
        $io->table(
            ['Field', 'Value'],
            [
                ['Internal ID', $order->getId()],
                ['Order No', $order->getOrderNo()],
                ['External Order ID', $order->getExternalOrderId()],
                ['External Order No', $order->getExternalOrderNo() ?? '-'],
                ['Status', $order->getStatus()],
                ['Channel', $order->getSalesChannel()->getName()],
                ['Current Label', $order->getLabel() ?? '(none)'],
            ]
        );

        // Get sales channel and validate gateway
        $salesChannel = $order->getSalesChannel();
        $channelCode = $salesChannel->getCode();

        if (!$this->gatewayRegistry->has($channelCode)) {
            $io->error("No gateway registered for channel: {$channelCode}");

            return Command::FAILURE;
        }

        $gateway = $this->gatewayRegistry->get($channelCode);

        // Currently only KicksCrew supports label pull
        if (!$gateway instanceof KicksCrewGateway) {
            $io->error('Label pull is only supported for KICKSCREW channel currently');

            return Command::FAILURE;
        }

        // Check API key
        $apiKey = $salesChannel->getConfigValue('api_key');
        if ($apiKey === null) {
            $io->error('No API key configured for the sales channel');

            return Command::FAILURE;
        }

        $io->info('Fetching shipping label from API...');

        try {
            $apiClient = $gateway->getApiClient();
            $pdfContent = $apiClient->getOrderLabel($apiKey, (int) $order->getExternalOrderId());

            $fileSize = strlen($pdfContent);
            $io->success(sprintf('Label fetched successfully! Size: %s bytes (%.2f KB)', $fileSize, $fileSize / 1024));

            if ($dryRun) {
                $io->note('Dry run mode - label not saved');

                return Command::SUCCESS;
            }

            // Handle save options
            $savedLocal = false;
            $savedCos = false;
            $localPath = null;
            $cosUrl = null;

            // Default: if no save option specified, save locally
            if (!$saveLocal && !$saveCos) {
                $saveLocal = true;
            }

            // Save to local filesystem
            if ($saveLocal) {
                $localPath = $this->saveToLocal($pdfContent, $order->getId(), $outputPath);
                if ($localPath !== null) {
                    $savedLocal = true;
                    $io->success("Saved to local: {$localPath}");
                } else {
                    $io->warning('Failed to save locally');
                }
            }

            // Upload to COS and update order
            if ($saveCos) {
                $cosUrl = $this->uploadToCos($pdfContent, $order->getId());
                if ($cosUrl !== null) {
                    $savedCos = true;
                    $order->setLabel($cosUrl);
                    $this->entityManager->flush();
                    $io->success("Uploaded to COS: {$cosUrl}");
                    $io->success('Order label field updated');
                } else {
                    $io->warning('Failed to upload to COS');
                }
            }

            // Summary
            $io->section('Summary');
            $io->table(
                ['Action', 'Result'],
                [
                    ['Label Size', sprintf('%s bytes (%.2f KB)', $fileSize, $fileSize / 1024)],
                    ['Saved Locally', $savedLocal ? $localPath : 'No'],
                    ['Uploaded to COS', $savedCos ? $cosUrl : 'No'],
                    ['Order Updated', $savedCos ? 'Yes' : 'No'],
                ]
            );

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error([
                'Failed to fetch label:',
                $e->getMessage(),
            ]);

            if ($io->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    private function findOrder(string $orderId, bool $isExternal): ?\App\Entity\Order
    {
        if ($isExternal) {
            // Search by external order ID - need to check all channels
            return $this->entityManager->createQueryBuilder()
                ->select('o')
                ->from(\App\Entity\Order::class, 'o')
                ->where('o.externalOrderId = :externalId')
                ->setParameter('externalId', $orderId)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        }

        // Search by internal ID
        return $this->orderRepository->find($orderId);
    }

    private function saveToLocal(string $pdfContent, string $orderId, ?string $customPath): ?string
    {
        try {
            if ($customPath !== null) {
                $filePath = $customPath;
                $dir = dirname($filePath);
            } else {
                $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
                $dir = sprintf(
                    '%s/var/labels/%s/%s',
                    dirname(__DIR__, 2),
                    $now->format('Y'),
                    $now->format('m')
                );
                $filePath = sprintf('%s/%s_%s.pdf', $dir, $orderId, $now->format('Ymd_His'));
            }

            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                return null;
            }

            if (file_put_contents($filePath, $pdfContent) === false) {
                return null;
            }

            return $filePath;
        } catch (\Throwable) {
            return null;
        }
    }

    private function uploadToCos(string $pdfContent, string $orderId): ?string
    {
        try {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $cosKey = sprintf(
                'labels/orders/%s/%s/%s_%s.pdf',
                $now->format('Y'),
                $now->format('m'),
                $orderId,
                (string) new Ulid()
            );

            // Create temp file for upload
            $tempFile = tempnam(sys_get_temp_dir(), 'label_');
            if ($tempFile === false) {
                return null;
            }

            try {
                if (file_put_contents($tempFile, $pdfContent) === false) {
                    return null;
                }

                // Use reflection to access the private client for direct upload
                // Since CosService.uploadFile expects UploadedFile, we need to use the client directly
                $reflection = new \ReflectionClass($this->cosService);

                $clientProp = $reflection->getProperty('client');
                $client = $clientProp->getValue($this->cosService);

                $bucketProp = $reflection->getProperty('bucket');
                $bucket = $bucketProp->getValue($this->cosService);

                $client->upload($bucket, $cosKey, fopen($tempFile, 'rb'));

                return $this->cosService->getUrl($cosKey);
            } finally {
                if (file_exists($tempFile)) {
                    @unlink($tempFile);
                }
            }
        } catch (\Throwable) {
            return null;
        }
    }
}
