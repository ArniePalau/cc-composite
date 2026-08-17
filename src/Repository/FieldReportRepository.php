<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Repository;

use ArniePalau\CcComposite\Entity\FieldReport;
use ArniePalau\CcComposite\Entity\Campaign;
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

    /** @return list<FieldReport> */
    public function findForCampaign(Campaign $campaign): array
    {
        return $this->findBy(['campaign' => $campaign], ['startedAt' => 'DESC']);
    }

    public function findOneWithMapForWorld(string $world): ?FieldReport
    {
        $reports = $this->createQueryBuilder('report')
            ->andWhere('LOWER(report.world) = LOWER(:world)')
            ->andWhere('report.mapSizeMeters IS NOT NULL')
            ->setParameter('world', $world)
            ->orderBy('report.importedAt', 'DESC')
            ->getQuery()
            ->getResult();

        foreach ($reports as $report) {
            if (is_array($report->getPayload()['_ccMap'] ?? null)) {
                return $report;
            }
        }

        return null;
    }
}
