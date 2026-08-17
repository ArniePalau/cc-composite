<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Entity;

use ArniePalau\CcComposite\Repository\FieldReportRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;

#[ORM\Entity(repositoryClass: FieldReportRepository::class)]
#[ORM\Table(name: 'cc_composite_field_report')]
#[ORM\UniqueConstraint(name: 'uniq_cc_field_report_code', columns: ['code'])]
class FieldReport
{
    use IdentifiableEntityTrait;

    #[ORM\Column(length: 64)]
    private string $code;

    #[ORM\Column(length: 2048)]
    private string $sourceUrl;

    #[ORM\Column(length: 255)]
    private string $missionName;

    #[ORM\Column(length: 128)]
    private string $world;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $worldDisplayName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $serverName = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $startedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $endedAt = null;

    #[ORM\Column]
    private int $durationSeconds = 0;

    #[ORM\Column]
    private int $playerCount = 0;

    #[ORM\Column]
    private int $totalKills = 0;

    #[ORM\Column]
    private int $totalFriendlyKills = 0;

    #[ORM\Column]
    private int $totalShots = 0;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $payload = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mapPath = null;

    #[ORM\Column(nullable: true)]
    private ?int $mapSizeMeters = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $importedAt;

    public function getCode(): string { return $this->code; }
    public function setCode(string $code): void { $this->code = $code; }
    public function getSourceUrl(): string { return $this->sourceUrl; }
    public function setSourceUrl(string $sourceUrl): void { $this->sourceUrl = $sourceUrl; }
    public function getMissionName(): string { return $this->missionName; }
    public function setMissionName(string $missionName): void { $this->missionName = $missionName; }
    public function getWorld(): string { return $this->world; }
    public function setWorld(string $world): void { $this->world = $world; }
    public function getWorldDisplayName(): ?string { return $this->worldDisplayName; }
    public function setWorldDisplayName(?string $worldDisplayName): void { $this->worldDisplayName = $worldDisplayName; }
    public function getServerName(): ?string { return $this->serverName; }
    public function setServerName(?string $serverName): void { $this->serverName = $serverName; }
    public function getStartedAt(): DateTimeImmutable { return $this->startedAt; }
    public function setStartedAt(DateTimeImmutable $startedAt): void { $this->startedAt = $startedAt; }
    public function getEndedAt(): ?DateTimeImmutable { return $this->endedAt; }
    public function setEndedAt(?DateTimeImmutable $endedAt): void { $this->endedAt = $endedAt; }
    public function getDurationSeconds(): int { return $this->durationSeconds; }
    public function setDurationSeconds(int $durationSeconds): void { $this->durationSeconds = max(0, $durationSeconds); }
    public function getPlayerCount(): int { return $this->playerCount; }
    public function setPlayerCount(int $playerCount): void { $this->playerCount = max(0, $playerCount); }
    public function getTotalKills(): int { return $this->totalKills; }
    public function setTotalKills(int $totalKills): void { $this->totalKills = max(0, $totalKills); }
    public function getTotalFriendlyKills(): int { return $this->totalFriendlyKills; }
    public function setTotalFriendlyKills(int $totalFriendlyKills): void { $this->totalFriendlyKills = max(0, $totalFriendlyKills); }
    public function getTotalShots(): int { return $this->totalShots; }
    public function setTotalShots(int $totalShots): void { $this->totalShots = max(0, $totalShots); }
    /** @return array<string, mixed> */
    public function getPayload(): array { return $this->payload; }
    /** @param array<string, mixed> $payload */
    public function setPayload(array $payload): void { $this->payload = $payload; }
    public function getMapPath(): ?string { return $this->mapPath; }
    public function setMapPath(?string $mapPath): void { $this->mapPath = $mapPath; }
    public function getMapSizeMeters(): ?int { return $this->mapSizeMeters; }
    public function setMapSizeMeters(?int $mapSizeMeters): void { $this->mapSizeMeters = $mapSizeMeters; }
    public function getImportedAt(): DateTimeImmutable { return $this->importedAt; }
    public function setImportedAt(DateTimeImmutable $importedAt): void { $this->importedAt = $importedAt; }

    public function getDurationLabel(): string
    {
        $hours = intdiv($this->durationSeconds, 3600);
        $minutes = intdiv($this->durationSeconds % 3600, 60);
        $seconds = $this->durationSeconds % 60;

        return $hours > 0
            ? sprintf('%dh %02dmin', $hours, $minutes)
            : sprintf('%dmin %02ds', $minutes, $seconds);
    }
}
