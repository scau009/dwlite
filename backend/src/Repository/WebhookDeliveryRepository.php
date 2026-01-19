<?php

namespace App\Repository;

use App\Entity\Webhook;
use App\Entity\WebhookDelivery;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WebhookDelivery>
 */
class WebhookDeliveryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebhookDelivery::class);
    }

    public function save(WebhookDelivery $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find deliveries by webhook with pagination.
     *
     * @param int $page Page number (starting from 1)
     * @param int $limit Items per page
     *
     * @return array{data: WebhookDelivery[], total: int}
     */
    public function findByWebhookPaginated(Webhook $webhook, int $page = 1, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('d')
            ->where('d.webhook = :webhook')
            ->setParameter('webhook', $webhook)
            ->orderBy('d.createdAt', 'DESC');

        $countQb = clone $qb;
        $total = $countQb->select('COUNT(d.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $offset = ($page - 1) * $limit;
        $data = $qb->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'data' => $data,
            'total' => (int) $total,
        ];
    }

    /**
     * Find delivery by event ID.
     */
    public function findByEventId(string $eventId): ?WebhookDelivery
    {
        return $this->findOneBy(['eventId' => $eventId]);
    }

    /**
     * Find pending deliveries that are due for retry.
     *
     * @return WebhookDelivery[]
     */
    public function findPendingForRetry(int $limit = 100): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $this->createQueryBuilder('d')
            ->join('d.webhook', 'w')
            ->where('d.status = :status')
            ->andWhere('d.nextRetryAt IS NOT NULL')
            ->andWhere('d.nextRetryAt <= :now')
            ->andWhere('w.status = :webhookStatus')
            ->setParameter('status', WebhookDelivery::STATUS_PENDING)
            ->setParameter('now', $now)
            ->setParameter('webhookStatus', Webhook::STATUS_ACTIVE)
            ->orderBy('d.nextRetryAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find failed deliveries.
     *
     * @return WebhookDelivery[]
     */
    public function findFailed(int $limit = 100): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.status = :status')
            ->setParameter('status', WebhookDelivery::STATUS_FAILED)
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get delivery statistics for a webhook.
     *
     * @return array{total: int, delivered: int, pending: int, failed: int}
     */
    public function getStatsByWebhook(Webhook $webhook, ?\DateTimeImmutable $since = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->select(
                'COUNT(d.id) as total',
                "SUM(CASE WHEN d.status = 'delivered' THEN 1 ELSE 0 END) as delivered",
                "SUM(CASE WHEN d.status = 'pending' THEN 1 ELSE 0 END) as pending",
                "SUM(CASE WHEN d.status = 'failed' THEN 1 ELSE 0 END) as failed"
            )
            ->where('d.webhook = :webhook')
            ->setParameter('webhook', $webhook);

        if ($since !== null) {
            $qb->andWhere('d.createdAt >= :since')
                ->setParameter('since', $since);
        }

        $result = $qb->getQuery()->getSingleResult();

        return [
            'total' => (int) ($result['total'] ?? 0),
            'delivered' => (int) ($result['delivered'] ?? 0),
            'pending' => (int) ($result['pending'] ?? 0),
            'failed' => (int) ($result['failed'] ?? 0),
        ];
    }

    /**
     * Delete old deliveries (for cleanup).
     *
     * @param int $daysToKeep Number of days to keep deliveries
     *
     * @return int Number of deleted records
     */
    public function deleteOldDeliveries(int $daysToKeep = 30): int
    {
        $threshold = new \DateTimeImmutable(
            sprintf('-%d days', $daysToKeep),
            new \DateTimeZone('UTC')
        );

        return $this->createQueryBuilder('d')
            ->delete()
            ->where('d.createdAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();
    }

    /**
     * Count pending deliveries.
     */
    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.status = :status')
            ->setParameter('status', WebhookDelivery::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
