<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Entity;

use ArniePalau\CcComposite\Repository\CampaignRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;

#[ORM\Entity(repositoryClass: CampaignRepository::class)]
#[ORM\Table(name: 'cc_composite_campaign')]
class Campaign
{
    use IdentifiableEntityTrait;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 255, unique: true)]
    private string $slug = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    /** @var Collection<int, FieldReport> */
    #[ORM\OneToMany(mappedBy: 'campaign', targetEntity: FieldReport::class)]
    #[ORM\OrderBy(['startedAt' => 'DESC'])]
    private Collection $reports;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->reports = new ArrayCollection();
    }

    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = trim($name); }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): void { $this->slug = $slug; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): void { $this->description = ($description = trim((string) $description)) !== '' ? $description : null; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
    /** @return Collection<int, FieldReport> */
    public function getReports(): Collection { return $this->reports; }
}
