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
            ->andWhere('report.visible = true')
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
            ->andWhere('report.visible = true')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<FieldReport> */
    public function findForCampaign(Campaign $campaign): array
    {
        return $this->findBy(['campaign' => $campaign, 'visible' => true], ['startedAt' => 'DESC']);
    }

    /**
     * Returns the newest visible report for each recently active campaign.
     *
     * @return list<FieldReport>
     */
    public function findRecentCampaignReports(int $limit = 3): array
    {
        $limit = max(1, $limit);
        $reports = $this->createQueryBuilder('report')
            ->addSelect('campaign')
            ->innerJoin('report.campaign', 'campaign')
            ->andWhere('report.visible = true')
            ->orderBy('report.startedAt', 'DESC')
            ->addOrderBy('report.id', 'DESC')
            ->getQuery()
            ->getResult();

        $recent = [];
        $seenCampaigns = [];

        foreach ($reports as $report) {
            $campaignId = $report->getCampaign()?->getId();
            if ($campaignId === null || isset($seenCampaigns[$campaignId])) {
                continue;
            }

            $seenCampaigns[$campaignId] = true;
            $recent[] = $report;

            if (count($recent) >= $limit) {
                break;
            }
        }

        return $recent;
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
