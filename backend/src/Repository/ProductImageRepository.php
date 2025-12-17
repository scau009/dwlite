<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductImage>
 */
class ProductImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductImage::class);
    }

    public function save(ProductImage $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ProductImage $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByProduct(Product $product): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.product = :product')
            ->setParameter('product', $product)
            ->orderBy('i.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByCosKey(string $cosKey): ?ProductImage
    {
        return $this->findOneBy(['cosKey' => $cosKey]);
    }

    public function findPrimaryByProduct(Product $product): ?ProductImage
    {
        return $this->findOneBy([
            'product' => $product,
            'isPrimary' => true,
        ]);
    }

    public function clearPrimaryForProduct(string $productId): void
    {
        $this->createQueryBuilder('i')
            ->update()
            ->set('i.isPrimary', ':false')
            ->where('i.product = :productId')
            ->setParameter('false', false)
            ->setParameter('productId', $productId)
            ->getQuery()
            ->execute();
    }

    public function findCosKeysByProduct(Product $product): array
    {
        return $this->createQueryBuilder('i')
            ->select('i.cosKey')
            ->where('i.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->getSingleColumnResult();
    }
}
