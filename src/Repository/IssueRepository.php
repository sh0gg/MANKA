<?php

namespace App\Repository;

use App\Entity\Issue;
use App\Entity\User;
use App\Enum\IssueStatus;
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

    /**
     * Listado completo (ROLE_TECHNICIAN / ROLE_ADMIN): abertas primeiro, despois as máis recentes.
     *
     * @return Issue[]
     */
    public function findAllOrderedByStatus(): array
    {
        return $this->createQueryBuilder('i')
            ->addSelect('(CASE WHEN i.status = :open THEN 0 ELSE 1 END) as HIDDEN status_order')
            ->setParameter('open', IssueStatus::OPEN)
            ->orderBy('status_order', 'ASC')
            ->addOrderBy('i.startAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Listado dun traballador de planta: só as incidencias que el mesmo abriu.
     *
     * @return Issue[]
     */
    public function findCreatedBy(User $user): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.createdBy = :user')
            ->setParameter('user', $user)
            ->addSelect('(CASE WHEN i.status = :open THEN 0 ELSE 1 END) as HIDDEN status_order')
            ->setParameter('open', IssueStatus::OPEN)
            ->orderBy('status_order', 'ASC')
            ->addOrderBy('i.startAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Filtros usados tanto pola API REST como por futuras búsquedas na web.
     *
     * @param array{from?: ?\DateTimeInterface, to?: ?\DateTimeInterface, device?: ?int, category?: ?int, status?: ?IssueStatus} $filters
     *
     * @return Issue[]
     */
    public function search(array $filters): array
    {
        $qb = $this->createQueryBuilder('i')->orderBy('i.startAt', 'DESC');

        if (!empty($filters['from'])) {
            $qb->andWhere('i.startAt >= :from')->setParameter('from', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $qb->andWhere('i.startAt <= :to')->setParameter('to', $filters['to']);
        }
        if (!empty($filters['device'])) {
            $qb->andWhere('i.device = :device')->setParameter('device', $filters['device']);
        }
        if (!empty($filters['category'])) {
            $qb->andWhere('i.category = :category')->setParameter('category', $filters['category']);
        }
        if (!empty($filters['status'])) {
            $qb->andWhere('i.status = :status')->setParameter('status', $filters['status']);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByStatus(IssueStatus $status): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Tempo medio de resolución (en horas) das incidencias xa pechadas.
     *
     * Usa SQL nativo porque DQL non ofrece unha función estándar para restar
     * dúas datas; EXTRACT(EPOCH FROM ...) é sintaxe propia de PostgreSQL.
     */
    public function averageResolutionHours(): ?float
    {
        $sql = 'SELECT AVG(EXTRACT(EPOCH FROM (end_at - start_at)) / 3600) AS avg_hours
                FROM issue
                WHERE status = :closed AND end_at IS NOT NULL';

        $result = $this->getEntityManager()->getConnection()->fetchOne($sql, [
            'closed' => IssueStatus::CLOSED->value,
        ]);

        return null !== $result ? (float) $result : null;
    }
}
