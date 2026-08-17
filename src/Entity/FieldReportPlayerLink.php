<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Entity;

use ArniePalau\CcComposite\Repository\FieldReportPlayerLinkRepository;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\User;

#[ORM\Entity(repositoryClass: FieldReportPlayerLinkRepository::class)]
#[ORM\Table(name: 'cc_composite_field_report_player_link')]
#[ORM\UniqueConstraint(name: 'uniq_cc_report_player_key', columns: ['player_key'])]
class FieldReportPlayerLink
{
    use IdentifiableEntityTrait;

    #[ORM\Column(length: 64)]
    private string $playerKey;

    #[ORM\Column(length: 255)]
    private string $playerName;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'forumify_user_id', nullable: false, onDelete: 'CASCADE')]
    private User $forumifyUser;

    public function getPlayerKey(): string
    {
        return $this->playerKey;
    }

    public function setPlayerKey(string $playerKey): void
    {
        $this->playerKey = $playerKey;
    }

    public function getPlayerName(): string
    {
        return $this->playerName;
    }

    public function setPlayerName(string $playerName): void
    {
        $this->playerName = $playerName;
    }

    public function getForumifyUser(): User
    {
        return $this->forumifyUser;
    }

    public function setForumifyUser(User $forumifyUser): void
    {
        $this->forumifyUser = $forumifyUser;
    }
}
