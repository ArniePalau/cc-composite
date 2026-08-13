<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Repository;

use ArniePalau\CcComposite\Entity\AwardPlacement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Forumify\PerscomPlugin\Perscom\Entity\Award;

/** @extends ServiceEntityRepository<AwardPlacement> */
class AwardPlacementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AwardPlacement::class);
    }

    public function findForAward(Award $award): ?AwardPlacement
    {
        return $this->findOneBy(['award' => $award]);
    }
}
