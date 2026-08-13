<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Repository;

use ArniePalau\CcComposite\Entity\CompositeDefault;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Forumify\PerscomPlugin\Perscom\Entity\Unit;

/** @extends ServiceEntityRepository<CompositeDefault> */
final class CompositeDefaultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompositeDefault::class);
    }

    public function findGlobal(): ?CompositeDefault
    {
        return $this->findOneBy(['scopeKey' => 'global']);
    }

    public function findForUnit(?Unit $unit): ?CompositeDefault
    {
        if ($unit === null) {
            return null;
        }

        return $this->findOneBy(['scopeKey' => 'unit:' . $unit->getId()]);
    }
}
