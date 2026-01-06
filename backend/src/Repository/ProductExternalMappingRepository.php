<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductExternalMapping;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductExternalMapping>
 */
class ProductExternalMappingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductExternalMapping::class);
    }

    public function save(ProductExternalMapping $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ProductExternalMapping $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find mapping by provider and external ID.
     */
    public function findByProviderAndExternalId(string $provider, string $externalId): ?ProductExternalMapping
    {
        return $this->findOneBy([
            'provider' => $provider,
            'externalId' => $externalId,
        ]);
    }

    /**
     * Find mapping by provider and external style ID.
     */
    public function findByProviderAndStyleId(string $provider, string $externalStyleId): ?ProductExternalMapping
    {
        return $this->findOneBy([
            'provider' => $provider,
            'externalStyleId' => $externalStyleId,
        ]);
    }

    /**
     * Find mapping by product and provider.
     */
    public function findByProductAndProvider(Product $product, string $provider): ?ProductExternalMapping
    {
        return $this->findOneBy([
            'product' => $product,
            'provider' => $provider,
        ]);
    }

    /**
     * Find all mappings for a product.
     *
     * @return ProductExternalMapping[]
     */
    public function findByProduct(Product $product): array
    {
        return $this->findBy(['product' => $product]);
    }

    /**
     * Find all mappings for a provider.
     *
     * @return ProductExternalMapping[]
     */
    public function findByProvider(string $provider): array
    {
        return $this->findBy(['provider' => $provider]);
    }

    /**
     * Check if a product has any external mapping.
     */
    public function hasMapping(Product $product): bool
    {
        return $this->count(['product' => $product]) > 0;
    }

    /**
     * Check if an external product is already mapped.
     */
    public function isExternalProductMapped(string $provider, string $externalId): bool
    {
        return $this->count([
            'provider' => $provider,
            'externalId' => $externalId,
        ]) > 0;
    }

    /**
     * Find or create a mapping.
     */
    public function findOrCreate(Product $product, string $provider, string $externalId): ProductExternalMapping
    {
        $mapping = $this->findByProviderAndExternalId($provider, $externalId);

        if ($mapping === null) {
            $mapping = new ProductExternalMapping($product, $provider, $externalId);
        }

        return $mapping;
    }

    /**
     * Count mappings by provider.
     *
     * @return array<string, int>
     */
    public function countByProvider(): array
    {
        $results = $this->createQueryBuilder('m')
            ->select('m.provider, COUNT(m.id) as count')
            ->groupBy('m.provider')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $row) {
            $counts[$row['provider']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Find mappings that need to be synced (older than given date).
     *
     * @return ProductExternalMapping[]
     */
    public function findStaleByProvider(string $provider, \DateTimeImmutable $threshold, int $limit = 100): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.provider = :provider')
            ->andWhere('m.lastSyncedAt < :threshold')
            ->setParameter('provider', $provider)
            ->setParameter('threshold', $threshold)
            ->orderBy('m.lastSyncedAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
