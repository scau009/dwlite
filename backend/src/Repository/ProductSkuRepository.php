<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductSku;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductSku>
 */
class ProductSkuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductSku::class);
    }

    public function save(ProductSku $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ProductSku $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByProduct(Product $product): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.product = :product')
            ->setParameter('product', $product)
            ->orderBy('s.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveByProduct(Product $product): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.product = :product')
            ->andWhere('s.isActive = :active')
            ->setParameter('product', $product)
            ->setParameter('active', true)
            ->orderBy('s.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByProductAndSize(Product $product, \App\Enum\SizeUnit $sizeUnit, string $sizeValue): ?ProductSku
    {
        return $this->createQueryBuilder('s')
            ->where('s.product = :product')
            ->andWhere('s.sizeUnit = :sizeUnit')
            ->andWhere('s.sizeValue = :sizeValue')
            ->setParameter('product', $product)
            ->setParameter('sizeUnit', $sizeUnit)
            ->setParameter('sizeValue', $sizeValue)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
