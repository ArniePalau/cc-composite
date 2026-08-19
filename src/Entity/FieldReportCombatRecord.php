<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Entity;

use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\PerscomPlugin\Perscom\Entity\PerscomUser;
use Forumify\PerscomPlugin\Perscom\Entity\Record\CombatRecord;

#[ORM\Entity]
#[ORM\Table(name: 'cc_composite_field_report_combat_record')]
#[ORM\UniqueConstraint(name: 'uniq_cc_report_combat_user', columns: ['field_report_id', 'perscom_user_id'])]
class FieldReportCombatRecord
{
    use IdentifiableEntityTrait;

    #[ORM\ManyToOne(targetEntity: FieldReport::class)]
    #[ORM\JoinColumn(name: 'field_report_id', nullable: false, onDelete: 'CASCADE')]
    private FieldReport $fieldReport;

    #[ORM\ManyToOne(targetEntity: PerscomUser::class)]
    #[ORM\JoinColumn(name: 'perscom_user_id', nullable: false, onDelete: 'CASCADE')]
    private PerscomUser $perscomUser;

    #[ORM\OneToOne(targetEntity: CombatRecord::class)]
    #[ORM\JoinColumn(name: 'combat_record_id', nullable: false, unique: true, onDelete: 'CASCADE')]
    private CombatRecord $combatRecord;

    public function getFieldReport(): FieldReport { return $this->fieldReport; }
    public function setFieldReport(FieldReport $fieldReport): void { $this->fieldReport = $fieldReport; }
    public function getPerscomUser(): PerscomUser { return $this->perscomUser; }
    public function setPerscomUser(PerscomUser $perscomUser): void { $this->perscomUser = $perscomUser; }
    public function getCombatRecord(): CombatRecord { return $this->combatRecord; }
    public function setCombatRecord(CombatRecord $combatRecord): void { $this->combatRecord = $combatRecord; }
}
