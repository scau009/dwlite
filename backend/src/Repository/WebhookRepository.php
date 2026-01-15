<?php

namespace App\Repository;

use App\Entity\ApiKey;
use App\Entity\Webhook;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Webhook>
 */
class WebhookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Webhook::class);
    }

    public function save(Webhook $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Webhook $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all webhooks by API Key.
     *
     * @return Webhook[]
     */
    public function findByApiKey(ApiKey $apiKey): array
    {
        return $this->findBy(
            ['apiKey' => $apiKey],
            ['createdAt' => 'DESC']
        );
    }

    /**
     * Find active webhooks by API Key.
     *
     * @return Webhook[]
     */
    public function findActiveByApiKey(ApiKey $apiKey): array
    {
        return $this->findBy(
            [
                'apiKey' => $apiKey,
                'status' => Webhook::STATUS_ACTIVE,
            ],
            ['createdAt' => 'DESC']
        );
    }

    /**
     * Find all active webhooks subscribed to a specific event.
     *
     * @return Webhook[]
     */
    public function findActiveByEvent(string $event): array
    {
        return $this->createQueryBuilder('w')
            ->where('w.status = :status')
            ->andWhere('JSON_CONTAINS(w.events, :event) = 1')
            ->setParameter('status', Webhook::STATUS_ACTIVE)
            ->setParameter('event', json_encode($event))
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active webhooks by API Key and event.
     *
     * @return Webhook[]
     */
    public function findActiveByApiKeyAndEvent(ApiKey $apiKey, string $event): array
    {
        return $this->createQueryBuilder('w')
            ->where('w.apiKey = :apiKey')
            ->andWhere('w.status = :status')
            ->andWhere('JSON_CONTAINS(w.events, :event) = 1')
            ->setParameter('apiKey', $apiKey)
            ->setParameter('status', Webhook::STATUS_ACTIVE)
            ->setParameter('event', json_encode($event))
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all active webhooks for merchant by event.
     *
     * @return Webhook[]
     */
    public function findActiveByMerchantAndEvent($merchant, string $event): array
    {
        return $this->createQueryBuilder('w')
            ->join('w.apiKey', 'ak')
            ->where('ak.merchant = :merchant')
            ->andWhere('w.status = :status')
            ->andWhere('JSON_CONTAINS(w.events, :event) = 1')
            ->setParameter('merchant', $merchant)
            ->setParameter('status', Webhook::STATUS_ACTIVE)
            ->setParameter('event', json_encode($event))
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all active webhooks for warehouse by event.
     *
     * @return Webhook[]
     */
    public function findActiveByWarehouseAndEvent($warehouse, string $event): array
    {
        return $this->createQueryBuilder('w')
            ->join('w.apiKey', 'ak')
            ->where('ak.warehouse = :warehouse')
            ->andWhere('w.status = :status')
            ->andWhere('JSON_CONTAINS(w.events, :event) = 1')
            ->setParameter('warehouse', $warehouse)
            ->setParameter('status', Webhook::STATUS_ACTIVE)
            ->setParameter('event', json_encode($event))
            ->getQuery()
            ->getResult();
    }

    /**
     * Find webhooks that have failed.
     *
     * @return Webhook[]
     */
    public function findFailed(): array
    {
        return $this->findBy(
            ['status' => Webhook::STATUS_FAILED],
            ['updatedAt' => 'DESC']
        );
    }

    /**
     * Paginated query for webhooks.
     *
     * @param int $page Page number (starting from 1)
     * @param int $limit Items per page
     * @param array{apiKeyId?: string, status?: string} $filters
     *
     * @return array{data: Webhook[], total: int}
     */
    public function findPaginated(int $page = 1, int $limit = 20, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('w')
            ->leftJoin('w.apiKey', 'a')
            ->addSelect('a')
            ->orderBy('w.createdAt', 'DESC');

        if (!empty($filters['apiKeyId'])) {
            $qb->andWhere('a.id = :apiKeyId')
                ->setParameter('apiKeyId', $filters['apiKeyId']);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('w.status = :status')
                ->setParameter('status', $filters['status']);
        }

        $countQb = clone $qb;
        $total = $countQb->select('COUNT(w.id)')
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
     * Count webhooks by API Key.
     */
    public function countByApiKey(ApiKey $apiKey): int
    {
        return (int) $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->where('w.apiKey = :apiKey')
            ->setParameter('apiKey', $apiKey)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
