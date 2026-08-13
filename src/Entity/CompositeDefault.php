<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Entity;

use ArniePalau\CcComposite\Repository\CompositeDefaultRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\PerscomPlugin\Perscom\Entity\Unit;

#[ORM\Entity(repositoryClass: CompositeDefaultRepository::class)]
#[ORM\Table(name: 'cc_composite_default')]
class CompositeDefault
{
    use IdentifiableEntityTrait;

    #[ORM\Column(length: 64, unique: true)]
    private string $scopeKey = 'global';

    #[ORM\ManyToOne(targetEntity: Unit::class)]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Unit $unit = null;

    #[ORM\Column(type: Types::JSON)]
    private array $layers = [];

    public function getScopeKey(): string
    {
        return $this->scopeKey;
    }

    public function setScopeKey(string $scopeKey): void
    {
        $this->scopeKey = $scopeKey;
    }

    public function getUnit(): ?Unit
    {
        return $this->unit;
    }

    public function setUnit(?Unit $unit): void
    {
        $this->unit = $unit;
        $this->scopeKey = $unit === null ? 'global' : 'unit:' . $unit->getId();
    }

    public function getLayers(): array
    {
        return $this->layers;
    }

    public function setLayers(?array $layers): void
    {
        $this->layers = array_filter($layers ?? [], static fn (mixed $value): bool => is_string($value));
    }
}
