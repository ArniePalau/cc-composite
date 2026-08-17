<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Entity;

use ArniePalau\CcComposite\Repository\GalleryImageRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;

#[ORM\Entity(repositoryClass: GalleryImageRepository::class)]
#[ORM\Table(name: 'cc_composite_gallery_image')]
#[ORM\Index(name: 'idx_cc_gallery_campaign', columns: ['campaign_id'])]
#[ORM\Index(name: 'idx_cc_gallery_field_report', columns: ['field_report_id'])]
class GalleryImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Campaign::class, inversedBy: 'images')]
    #[ORM\JoinColumn(name: 'campaign_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Campaign $campaign = null;

    #[ORM\ManyToOne(targetEntity: FieldReport::class, inversedBy: 'images')]
    #[ORM\JoinColumn(name: 'field_report_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?FieldReport $fieldReport = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    private string $imagePath = '';

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCampaign(): ?Campaign
    {
        return $this->campaign;
    }

    public function setCampaign(?Campaign $campaign): void
    {
        $this->campaign = $campaign;
    }

    public function getFieldReport(): ?FieldReport
    {
        return $this->fieldReport;
    }

    public function setFieldReport(?FieldReport $fieldReport): void
    {
        $this->fieldReport = $fieldReport;
        if ($fieldReport !== null && $fieldReport->getCampaign() !== null && $this->campaign === null) {
            $this->campaign = $fieldReport->getCampaign();
        }
    }

    /**
     * Resolves effective campaign (directly set or inferred from mission).
     */
    public function getEffectiveCampaign(): ?Campaign
    {
        return $this->campaign ?? $this->fieldReport?->getCampaign();
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = ($title = trim((string) $title)) !== '' ? $title : null;
    }

    public function getImagePath(): string
    {
        return $this->imagePath;
    }

    public function setImagePath(string $imagePath): void
    {
        $this->imagePath = $imagePath;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
