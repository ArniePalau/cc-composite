<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Repository;

use ArniePalau\CcComposite\Entity\Campaign;
use ArniePalau\CcComposite\Entity\FieldReport;
use ArniePalau\CcComposite\Entity\GalleryImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GalleryImage>
 */
class GalleryImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GalleryImage::class);
    }

    /**
     * @return GalleryImage[]
     */
    public function findByCampaign(Campaign $campaign): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.fieldReport', 'r')
            ->where('g.campaign = :campaign OR r.campaign = :campaign')
            ->setParameter('campaign', $campaign)
            ->orderBy('g.position', 'ASC')
            ->addOrderBy('g.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return GalleryImage[]
     */
    public function findByFieldReport(FieldReport $fieldReport): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.fieldReport = :report')
            ->setParameter('report', $fieldReport)
            ->orderBy('g.position', 'ASC')
            ->addOrderBy('g.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return GalleryImage[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.campaign', 'c')
            ->leftJoin('g.fieldReport', 'r')
            ->addSelect('c', 'r')
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('g.position', 'ASC')
            ->addOrderBy('g.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
