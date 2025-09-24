<?php

namespace App\Repository;

use App\Entity\Employee;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Employee>
 */
class EmployeeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Employee::class);
    }

    public function save(Employee $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Employee $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByProviderAndExternalId(string $provider, string $externalId): ?Employee
    {
        return $this->findOneBy([
            'provider' => $provider,
            'externalId' => $externalId,
        ]);
    }

    public function findByProvider(string $provider): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.provider = :p')
            ->setParameter('p', $provider)
            ->orderBy('e.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingSync(int $limit = 100): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.trackTikId IS NULL OR e.updatedAt > e.createdAt')
            ->orderBy('e.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
  