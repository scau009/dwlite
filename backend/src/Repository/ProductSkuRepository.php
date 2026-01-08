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

    public function flush(): void
    {
        $this->getEntityManager()->flush();
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

    /**
     * 根据 SKU 编码查找 SKU（格式：styleNumber-sizeValue，如 ABC123-42）.
     */
    public function findOneBySkuCode(string $skuCode): ?ProductSku
    {
        // SKU 编码格式：styleNumber-sizeValue
        $parts = explode('-', $skuCode, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$styleNumber, $sizeValue] = $parts;

        return $this->createQueryBuilder('s')
            ->join('s.product', 'p')
            ->where('p.styleNumber = :styleNumber')
            ->andWhere('s.sizeValue = :sizeValue')
            ->setParameter('styleNumber', $styleNumber)
            ->setParameter('sizeValue', $sizeValue)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
