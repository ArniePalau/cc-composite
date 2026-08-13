<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Repository;

use ArniePalau\CcComposite\Entity\CompositeSelection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Forumify\PerscomPlugin\Perscom\Entity\PerscomUser;

/** @extends ServiceEntityRepository<CompositeSelection> */
final class CompositeSelectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompositeSelection::class);
    }

    public function findForUser(PerscomUser $user): ?CompositeSelection
    {
        return $this->findOneBy(['user' => $user]);
    }
}
