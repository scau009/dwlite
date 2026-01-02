<?php

namespace App\Repository;

use App\Entity\Merchant;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Merchant>
 */
class MerchantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Merchant::class);
    }

    public function save(Merchant $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Merchant $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByUser(User $user): ?Merchant
    {
        return $this->findOneBy(['user' => $user]);
    }

    public function findByStatus(string $status): array
    {
        return $this->findBy(['status' => $status], ['createdAt' => 'DESC']);
    }

    public function findPending(): array
    {
        return $this->findByStatus(Merchant::STATUS_PENDING);
    }

    public function findApproved(): array
    {
        return $this->findByStatus(Merchant::STATUS_APPROVED);
    }

    /**
     * 分页查询商户列表.
     *
     * @param int $page 页码（从1开始）
     * @param int $limit 每页数量
     * @param array $filters 筛选条件 ['status' => string, 'name' => string, 'email' => string]
     *
     * @return array ['data' => Merchant[], 'total' => int]
     */
    public function findPaginated(int $page = 1, int $limit = 20, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.user', 'u')
            ->addSelect('u')
            ->orderBy('m.createdAt', 'DESC');

        // 状态筛选
        if (!empty($filters['status'])) {
            $qb->andWhere('m.status = :status')
                ->setParameter('status', $filters['status']);
        }

        // 名称筛选（模糊匹配）
        if (!empty($filters['name'])) {
            $qb->andWhere('m.name LIKE :name')
                ->setParameter('name', '%'.$filters['name'].'%');
        }

        // 邮箱筛选（模糊匹配）
        if (!empty($filters['email'])) {
            $qb->andWhere('u.email LIKE :email')
                ->setParameter('email', '%'.$filters['email'].'%');
        }

        // 获取总数
        $countQb = clone $qb;
        $total = $countQb->select('COUNT(m.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // 分页
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
}
