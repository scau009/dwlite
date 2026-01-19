<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ChannelProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:debug-channel-product-stock',
    description: 'Debug channel product stock calculation'
)]
class DebugChannelProductStockCommand extends Command
{
    public function __construct(
        private ChannelProductRepository $channelProductRepo,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('channelProductId', InputArgument::REQUIRED, 'Channel Product ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $channelProductId = $input->getArgument('channelProductId');

        $channelProduct = $this->channelProductRepo->find($channelProductId);
        if ($channelProduct === null) {
            $io->error("ChannelProduct not found: {$channelProductId}");

            return Command::FAILURE;
        }

        $io->title('Channel Product Stock Debug');

        // Show current state
        $io->section('Current State');
        $io->table(
            ['Field', 'Value'],
            [
                ['ID', $channelProduct->getId()],
                ['Stock Mode', $channelProduct->getStockMode()],
                ['Current Stock', $channelProduct->getStockQuantity()],
                ['Platform Price', $channelProduct->getPlatformPrice()],
                ['Safety Buffer', $channelProduct->getSafetyBuffer()],
                ['Status', $channelProduct->getStatus()],
            ]
        );

        // Show all sources
        $io->section('All Sources');
        $sources = $channelProduct->getSources();
        $sourceData = [];
        foreach ($sources as $source) {
            $listing = $source->getInventoryListing();
            $sourceData[] = [
                $source->getId(),
                $source->isActive() ? 'Yes' : 'No',
                $listing->getStatus(),
                $listing->getPrice(),
                $listing->getAvailableQuantity(),
                $listing->getMerchantInventory()->getMerchant()->getName(),
            ];
        }
        $io->table(
            ['Source ID', 'Is Active', 'Listing Status', 'Price', 'Available Qty', 'Merchant'],
            $sourceData
        );

        // Show active sources only
        $io->section('Active Sources (used for calculation)');
        $activeSources = $channelProduct->getActiveSources();
        $activeSourceData = [];
        $lowestPrice = null;
        foreach ($activeSources as $source) {
            $listing = $source->getInventoryListing();
            $price = $listing->getPrice();
            if ($lowestPrice === null || bccomp($price, $lowestPrice, 2) < 0) {
                $lowestPrice = $price;
            }
            $activeSourceData[] = [
                $source->getId(),
                $listing->getPrice(),
                $listing->getAvailableQuantity(),
                $listing->getMerchantInventory()->getMerchant()->getName(),
            ];
        }
        $io->table(
            ['Source ID', 'Price', 'Available Qty', 'Merchant'],
            $activeSourceData
        );

        if ($lowestPrice !== null) {
            $io->info("Lowest Price: {$lowestPrice}");

            // Calculate stock for lowest price
            $totalStock = 0;
            foreach ($activeSources as $source) {
                $price = $source->getInventoryListing()->getPrice();
                if (bccomp($price, $lowestPrice, 2) === 0) {
                    $qty = $source->getInventoryListing()->getAvailableQuantity();
                    $io->writeln("  - Source price {$price} matches lowest price, adding qty: {$qty}");
                    $totalStock += $qty;
                }
            }
            $io->info("Total Stock at Lowest Price: {$totalStock}");
        }

        // Recalculate and save
        $io->section('Recalculating Stock');
        $oldStock = $channelProduct->getStockQuantity();
        $channelProduct->recalculateStock();
        $newStock = $channelProduct->getStockQuantity();

        $io->table(
            ['Field', 'Before', 'After'],
            [
                ['Stock Quantity', $oldStock, $newStock],
            ]
        );

        if ($oldStock !== $newStock) {
            $this->entityManager->flush();
            $io->success("Stock updated from {$oldStock} to {$newStock}");
        } else {
            $io->info('Stock unchanged');
        }

        return Command::SUCCESS;
    }
}
