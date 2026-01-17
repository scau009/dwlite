<?php

namespace App\Command;

use App\Service\ProductSync\ProductDataProviderRegistry;
use App\Service\ProductSync\ProductSyncService;
use App\Service\ProductSync\Provider\KicksDbProvider;
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
            ->addOption('style', 's', InputOption::VALUE_OPTIONAL, 'Style ID(s) to sync (comma-separated)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only fetch, do not save');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $page = (int) $input->getArgument('page');
        $providerName = $input->getOption('provider');
        $limit = (int) $input->getOption('limit');
        $styleIds = $input->getOption('style');
        $dryRun = $input->getOption('dry-run');

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

        // Handle style-based sync
        if ($styleIds !== null) {
            return $this->syncByStyleIds($io, $provider, $job, $styleIds, $dryRun);
        }

        // Handle page-based sync
        return $this->syncByPage($io, $provider, $job, $page, $limit, $dryRun);
    }

    /**
     * Sync products by style IDs.
     */
    private function syncByStyleIds(
        SymfonyStyle $io,
        mixed $provider,
        mixed $job,
        string $styleIds,
        bool $dryRun,
    ): int {
        $styles = array_map('trim', explode(',', $styleIds));
        $styles = array_filter($styles);

        if (empty($styles)) {
            $io->error('No valid style IDs provided');

            return Command::FAILURE;
        }

        $io->title('Testing Product Sync - By Style ID');
        $io->info('Style IDs to sync: '.implode(', ', $styles));

        if (!$provider instanceof KicksDbProvider) {
            $io->error('Style-based sync is only supported for KicksDB provider');

            return Command::FAILURE;
        }

        $products = [];
        foreach ($styles as $styleId) {
            $io->section("Searching for style: {$styleId}");

            $result = $provider->searchProducts($styleId, 1, 10);

            if ($result->getProductCount() === 0) {
                $io->warning("No products found for style: {$styleId}");
                continue;
            }

            // Find exact match or partial match (styleId may contain multiple codes like "HF2793-601 / HF2794-601")
            $matched = null;
            foreach ($result->products as $product) {
                // Exact match
                if (strcasecmp($product->styleId, $styleId) === 0) {
                    $matched = $product;
                    break;
                }
                // Partial match: check if the styleId contains the search term
                if (stripos($product->styleId, $styleId) !== false) {
                    $matched = $product;
                    break;
                }
            }

            if ($matched === null) {
                $io->warning("No match found for style: {$styleId}");
                $io->text('Found similar products:');
                foreach ($result->products as $product) {
                    $io->text("  - {$product->styleId}: {$product->title}");
                }
                continue;
            }

            $products[] = $matched;
            $io->text("Found: {$matched->styleId} - {$matched->title}");
        }

        if (empty($products)) {
            $io->error('No products found for the provided style IDs');

            return Command::FAILURE;
        }

        // Display products
        $io->section('Products to sync');
        $rows = [];
        foreach ($products as $product) {
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
        foreach ($products as $product) {
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
            "Synced: {$synced}/".count($products),
            "Created: {$job->getCreatedProducts()}",
            "Updated: {$job->getUpdatedProducts()}",
            "Skipped: {$job->getSkippedProducts()}",
            "Failed: {$job->getFailedProducts()}",
        ]);

        return Command::SUCCESS;
    }

    /**
     * Sync products by page.
     */
    private function syncByPage(
        SymfonyStyle $io,
        mixed $provider,
        mixed $job,
        int $page,
        int $limit,
        bool $dryRun,
    ): int {
        $io->title("Testing Product Sync - Page {$page}");

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
