<?php

namespace App\Scheduler;

use App\Message\HandleExpiredFulfillmentsMessage;
use App\Message\ScanFailedOrderSyncMessage;
use App\Message\ScanPendingSettlementsMessage;
use App\Message\ScanPendingSyncMessage;
use App\Message\ScheduleOrderPullMessage;
use App\Message\StartProductSyncMessage;
use App\Service\ProductSync\Provider\KicksDbProvider;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule('default')]
class MainSchedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    )
    {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->with(
            // Run cleanup every minute (for demo purposes)
            // In production, use '1 hour', '1 day', or cron expressions

            // KicksDB product sync - runs daily at 02:00 UTC
                RecurringMessage::cron('40 22 * * *', new StartProductSyncMessage(
                    KicksDbProvider::PROVIDER_NAME,
                )),

                // Channel product sync compensation - scan for stale pending products every 5 minutes
                // This catches any products stuck in pending status due to message loss or processing failures
                RecurringMessage::every('5 minutes', ScanPendingSyncMessage::create()),

                // Order sync - pull orders from all active channels every 5 minutes
                RecurringMessage::every('5 minutes', ScheduleOrderPullMessage::create()),

                // Order sync compensation - scan for failed order syncs every 10 minutes
                RecurringMessage::every('10 minutes', ScanFailedOrderSyncMessage::create()),

                // Fulfillment timeout detection - check for expired fulfillments every 5 minutes
                RecurringMessage::every('5 minutes', HandleExpiredFulfillmentsMessage::create()),

                // Settlement scanning - scan for pending settlements every 1 hour
                RecurringMessage::every('1 hour', ScanPendingSettlementsMessage::create()),

            // Examples of other schedule patterns:
            // RecurringMessage::every('1 hour', new HourlyTaskMessage()),
            // RecurringMessage::every('1 day', new DailyReportMessage()),
            // RecurringMessage::cron('0 0 * * *', new MidnightTaskMessage()),  // Every day at midnight
            // RecurringMessage::cron('*/5 * * * *', new Every5MinutesMessage()), // Every 5 minutes
            )
            ->stateful($this->cache);  // Prevent duplicate runs on restart
    }
}
