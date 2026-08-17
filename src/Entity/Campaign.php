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

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    /** @var Collection<int, FieldReport> */
    #[ORM\OneToMany(mappedBy: 'campaign', targetEntity: FieldReport::class)]
    #[ORM\OrderBy(['startedAt' => 'DESC'])]
    private Collection $reports;

    /** @var Collection<int, GalleryImage> */
    #[ORM\OneToMany(mappedBy: 'campaign', targetEntity: GalleryImage::class, cascade: ['remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $images;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->reports = new ArrayCollection();
        $this->images = new ArrayCollection();
    }

    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = trim($name); }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): void { $this->slug = $slug; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): void { $this->description = ($description = trim((string) $description)) !== '' ? $description : null; }
    public function getImagePath(): ?string { return $this->imagePath; }
    public function setImagePath(?string $imagePath): void { $this->imagePath = $imagePath; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
    /** @return Collection<int, FieldReport> */
    public function getReports(): Collection { return $this->reports; }
    /** @return Collection<int, GalleryImage> */
    public function getImages(): Collection { return $this->images; }
    public function addImage(GalleryImage $image): void
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setCampaign($this);
        }
    }
    public function removeImage(GalleryImage $image): void
    {
        if ($this->images->removeElement($image) && $image->getCampaign() === $this) {
            $image->setCampaign(null);
        }
    }
}
