<?php

namespace App\Repository;

use App\Entity\Issue;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Issue>
 */
class IssueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Issue::class);
    }

    //    /**
    //     * @return Issue[] Returns an array of Issue objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('i.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Issue
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * @return Issue[]
     */
    public function findAllOrderedByStatus(): array
    {
        return $this->createQueryBuilder('i')
            // Añadimos una columna virtual 'is_open'.
            // Si endAt es NULL, devuelve 1. Si no, 0.
            ->addSelect('(CASE WHEN i.endAt IS NULL THEN 1 ELSE 0 END) as HIDDEN is_open')
            // Primero ordenamos por esa columna (1 arriba, 0 abajo)
            ->orderBy('is_open', 'DESC')
            // Y dentro de cada grupo, el ID más reciente arriba
            ->addOrderBy('i.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
