<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Entity;

use ArniePalau\CcComposite\Repository\CompositeSelectionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\TimestampableEntityTrait;
use Forumify\PerscomPlugin\Perscom\Entity\PerscomUser;

#[ORM\Entity(repositoryClass: CompositeSelectionRepository::class)]
#[ORM\Table(name: 'cc_composite_selection')]
class CompositeSelection
{
    use IdentifiableEntityTrait;
    use TimestampableEntityTrait;

    #[ORM\OneToOne(targetEntity: PerscomUser::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PerscomUser $user;

    #[ORM\Column(type: Types::JSON)]
    private array $layers = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $generatedPath = null;

    public function getUser(): PerscomUser
    {
        return $this->user;
    }

    public function setUser(PerscomUser $user): void
    {
        $this->user = $user;
    }

    public function getLayers(): array
    {
        return $this->layers;
    }

    public function setLayers(?array $layers): void
    {
        $this->layers = array_filter($layers ?? [], static fn (mixed $value): bool => is_string($value) && $value !== '');
    }

    public function getGeneratedPath(): ?string
    {
        return $this->generatedPath;
    }

    public function setGeneratedPath(?string $generatedPath): void
    {
        $this->generatedPath = $generatedPath;
    }
}
