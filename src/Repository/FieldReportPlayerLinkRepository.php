<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Repository;

use ArniePalau\CcComposite\Entity\FieldReportPlayerLink;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<FieldReportPlayerLink> */
final class FieldReportPlayerLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FieldReportPlayerLink::class);
    }

    /** @return array<string, FieldReportPlayerLink> */
    public function findIndexedByPlayerKey(): array
    {
        $indexed = [];
        foreach ($this->findBy([], ['playerName' => 'ASC']) as $link) {
            $indexed[$link->getPlayerKey()] = $link;
        }

        return $indexed;
    }
}
