<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Entity;

use ArniePalau\CcComposite\Enum\LayerCategory;
use ArniePalau\CcComposite\Repository\CompositeLayerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\PerscomPlugin\Perscom\Entity\PerscomUser;
use Forumify\PerscomPlugin\Perscom\Entity\Rank;
use Forumify\PerscomPlugin\Perscom\Entity\Unit;

#[ORM\Entity(repositoryClass: CompositeLayerRepository::class)]
#[ORM\Table(name: 'cc_composite_layer')]
#[ORM\UniqueConstraint(name: 'uniq_cc_layer_file', columns: ['category', 'filename'])]
class CompositeLayer
{
    use IdentifiableEntityTrait;

    #[ORM\Column(length: 255)]
    private string $filename;

    #[ORM\Column(length: 32, enumType: LayerCategory::class)]
    private LayerCategory $category;

    /** @var Collection<int, Rank> */
    #[ORM\ManyToMany(targetEntity: Rank::class)]
    #[ORM\JoinTable(name: 'cc_composite_layer_rank')]
    private Collection $allowedRanks;

    /** @var Collection<int, Unit> */
    #[ORM\ManyToMany(targetEntity: Unit::class)]
    #[ORM\JoinTable(name: 'cc_composite_layer_unit')]
    private Collection $allowedUnits;

    /** @var Collection<int, PerscomUser> */
    #[ORM\ManyToMany(targetEntity: PerscomUser::class)]
    #[ORM\JoinTable(name: 'cc_composite_layer_user')]
    private Collection $allowedUsers;

    public function __construct()
    {
        $this->allowedRanks = new ArrayCollection();
        $this->allowedUnits = new ArrayCollection();
        $this->allowedUsers = new ArrayCollection();
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): void
    {
        $this->filename = $filename;
    }

    public function getCategory(): LayerCategory
    {
        return $this->category;
    }

    public function setCategory(LayerCategory $category): void
    {
        $this->category = $category;
    }

    public function getAssetPath(): string
    {
        return sprintf('layers/%s/%s', $this->category->directory(), $this->filename);
    }

    /** @return Collection<int, Rank> */
    public function getAllowedRanks(): Collection
    {
        return $this->allowedRanks;
    }

    public function addAllowedRank(Rank $rank): void
    {
        if (!$this->allowedRanks->contains($rank)) {
            $this->allowedRanks->add($rank);
        }
    }

    public function removeAllowedRank(Rank $rank): void
    {
        $this->allowedRanks->removeElement($rank);
    }

    /** @return Collection<int, Unit> */
    public function getAllowedUnits(): Collection
    {
        return $this->allowedUnits;
    }

    public function addAllowedUnit(Unit $unit): void
    {
        if (!$this->allowedUnits->contains($unit)) {
            $this->allowedUnits->add($unit);
        }
    }

    public function removeAllowedUnit(Unit $unit): void
    {
        $this->allowedUnits->removeElement($unit);
    }

    /** @return Collection<int, PerscomUser> */
    public function getAllowedUsers(): Collection
    {
        return $this->allowedUsers;
    }

    public function addAllowedUser(PerscomUser $user): void
    {
        if (!$this->allowedUsers->contains($user)) {
            $this->allowedUsers->add($user);
        }
    }

    public function removeAllowedUser(PerscomUser $user): void
    {
        $this->allowedUsers->removeElement($user);
    }

    public function isAllowedFor(PerscomUser $user): bool
    {
        if ($this->allowedRanks->isEmpty() && $this->allowedUnits->isEmpty() && $this->allowedUsers->isEmpty()) {
            return true;
        }

        return ($user->getRank() !== null && $this->allowedRanks->contains($user->getRank()))
            || ($user->getUnit() !== null && $this->allowedUnits->contains($user->getUnit()))
            || $this->allowedUsers->contains($user);
    }
}
