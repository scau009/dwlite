<?php

namespace App\Scheduler;

use App\Message\ExpireReservationsMessage;
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
        private string $environment,
    ) {
    }

    public function getSchedule(): Schedule
    {
        $schedule = new Schedule();

        // Only register scheduled tasks in production environment
        if ($this->environment === 'prod') {
            $schedule->add(
                // KicksDB product sync - runs daily at 22:40 UTC
//                RecurringMessage::cron('40 22 * * *', new StartProductSyncMessage(
//                    KicksDbProvider::PROVIDER_NAME,
//                )),

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

                // Inventory reservation expiration - expire overdue reservations every 1 minute
                RecurringMessage::every('1 minute', ExpireReservationsMessage::create()),
            );
        } else {
            $schedule->add(
                RecurringMessage::cron('40 22 * * *', new StartProductSyncMessage(
                    KicksDbProvider::PROVIDER_NAME,
                ))
            );
        }

        return $schedule->stateful($this->cache);  // Prevent duplicate runs on restart
    }
}
