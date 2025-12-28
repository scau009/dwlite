<?php

namespace App\Scheduler;

use App\Message\CleanupMessage;
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
    ) {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->with(
                // Run cleanup every minute (for demo purposes)
                // In production, use '1 hour', '1 day', or cron expressions
                RecurringMessage::every('1 minute', new CleanupMessage(new \DateTimeImmutable('now', new \DateTimeZone('UTC')))),

                // Examples of other schedule patterns:
                // RecurringMessage::every('1 hour', new HourlyTaskMessage()),
                // RecurringMessage::every('1 day', new DailyReportMessage()),
                // RecurringMessage::cron('0 0 * * *', new MidnightTaskMessage()),  // Every day at midnight
                // RecurringMessage::cron('*/5 * * * *', new Every5MinutesMessage()), // Every 5 minutes
            )
            ->stateful($this->cache);  // Prevent duplicate runs on restart
    }
}
