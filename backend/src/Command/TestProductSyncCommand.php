<?php

namespace App\Command;

use App\Service\ProductSync\ProductDataProviderRegistry;
use App\Service\ProductSync\ProductSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-product-sync',
    description: 'Test sync a single page of products from external provider'
)]
class TestProductSyncCommand extends Command
{
    public function __construct(
        private ProductDataProviderRegistry $providerRegistry,
        private ProductSyncService $syncService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('page', InputArgument::OPTIONAL, 'Page number to sync', '1')
            ->addOption('provider', 'p', InputOption::VALUE_OPTIONAL, 'Provider name', 'kicksdb')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Products per page', 10)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only fetch, do not save');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $page = (int) $input->getArgument('page');
        $providerName = $input->getOption('provider');
        $limit = (int) $input->getOption('limit');
        $dryRun = $input->getOption('dry-run');

        $io->title("Testing Product Sync - Page {$page}");

        // Get provider
        if (!$this->providerRegistry->has($providerName)) {
            $io->error("Unknown provider: {$providerName}");

            return Command::FAILURE;
        }

        $provider = $this->providerRegistry->get($providerName);

        // Create sync job (only if not dry-run)
        $job = null;
        if (!$dryRun) {
            $job = $this->syncService->createSyncJob($providerName);
            $io->info("Created sync job: {$job->getId()}");
        }

        // Fetch page
        $io->section("Fetching page {$page} with limit {$limit}...");
        $result = $provider->fetchProducts($page, $limit);

        $io->info([
            "Total products: {$result->totalCount}",
            "Total pages: {$result->getTotalPages()}",
            "Products on this page: {$result->getProductCount()}",
            'Has next page: '.($result->hasNextPage ? 'Yes' : 'No'),
        ]);

        // Display products
        $io->section('Products');
        $rows = [];
        foreach ($result->products as $product) {
            $rows[] = [
                $product->externalId,
                $product->styleId,
                mb_substr($product->title, 0, 40),
                $product->brand ?? 'N/A',
                count($product->skus).' SKUs',
            ];
        }
        $io->table(['External ID', 'Style ID', 'Title', 'Brand', 'SKUs'], $rows);

        if ($dryRun) {
            $io->warning('Dry run mode - not saving to database');

            return Command::SUCCESS;
        }

        // Sync products
        $io->section('Syncing products...');
        $synced = 0;
        foreach ($result->products as $product) {
            $productId = $this->syncService->syncProduct($job, $product);
            if ($productId) {
                $io->writeln("  ✓ Synced: {$product->styleId} -> {$productId}");
                ++$synced;
            } else {
                $io->writeln("  ✗ Skipped: {$product->styleId}");
            }
        }

        $this->syncService->flush();

        $io->success([
            'Sync complete!',
            "Synced: {$synced}",
            "Created: {$job->getCreatedProducts()}",
            "Updated: {$job->getUpdatedProducts()}",
            "Skipped: {$job->getSkippedProducts()}",
            "Failed: {$job->getFailedProducts()}",
        ]);

        return Command::SUCCESS;
    }
}
