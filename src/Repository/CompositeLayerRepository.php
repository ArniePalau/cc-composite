<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Repository;

use ArniePalau\CcComposite\Entity\CompositeLayer;
use ArniePalau\CcComposite\Enum\LayerCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CompositeLayer> */
class CompositeLayerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompositeLayer::class);
    }

    /** @return list<CompositeLayer> */
    public function findForCategory(LayerCategory $category): array
    {
        return $this->findBy(['category' => $category], ['filename' => 'ASC']);
    }

    public function findOneByCategoryAndFilename(LayerCategory $category, string $filename): ?CompositeLayer
    {
        return $this->findOneBy(['category' => $category, 'filename' => $filename]);
    }
}
