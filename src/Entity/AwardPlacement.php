<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Entity;

use ArniePalau\CcComposite\Enum\AwardCategory;
use ArniePalau\CcComposite\Repository\AwardPlacementRepository;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\PerscomPlugin\Perscom\Entity\Award;

#[ORM\Entity(repositoryClass: AwardPlacementRepository::class)]
#[ORM\Table(name: 'cc_composite_award_placement')]
class AwardPlacement
{
    use IdentifiableEntityTrait;

    #[ORM\OneToOne(targetEntity: Award::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Award $award;

    #[ORM\Column(length: 32, enumType: AwardCategory::class)]
    private AwardCategory $category;

    public function getAward(): Award
    {
        return $this->award;
    }

    public function setAward(Award $award): void
    {
        $this->award = $award;
    }

    public function getCategory(): AwardCategory
    {
        return $this->category;
    }

    public function setCategory(AwardCategory $category): void
    {
        $this->category = $category;
    }
}
