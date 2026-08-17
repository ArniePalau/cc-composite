<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Repository;

use ArniePalau\CcComposite\Entity\FieldReport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<FieldReport> */
final class FieldReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FieldReport::class);
    }

    /** @return list<FieldReport> */
    public function findPage(int $page, int $limit = 6): array
    {
        return $this->createQueryBuilder('report')
            ->orderBy('report.startedAt', 'DESC')
            ->addOrderBy('report.id', 'DESC')
            ->setFirstResult((max(1, $page) - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('report')
            ->select('COUNT(report.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneWithMapForWorld(string $world): ?FieldReport
    {
        return $this->createQueryBuilder('report')
            ->andWhere('LOWER(report.world) = LOWER(:world)')
            ->andWhere('report.mapPath IS NOT NULL')
            ->setParameter('world', $world)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
