<?php

declare(strict_types=1);

namespace CcCompositeMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260819010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link automatically generated PERSCOM combat records to qualifying field reports.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE cc_composite_field_report_combat_record (id INT AUTO_INCREMENT NOT NULL, field_report_id INT NOT NULL, perscom_user_id INT NOT NULL, combat_record_id INT NOT NULL, UNIQUE INDEX uniq_cc_report_combat_user (field_report_id, perscom_user_id), UNIQUE INDEX UNIQ_CC_REPORT_COMBAT_RECORD (combat_record_id), INDEX IDX_CC_REPORT_COMBAT_REPORT (field_report_id), INDEX IDX_CC_REPORT_COMBAT_USER (perscom_user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE cc_composite_field_report_combat_record ADD CONSTRAINT FK_CC_REPORT_COMBAT_REPORT FOREIGN KEY (field_report_id) REFERENCES cc_composite_field_report (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cc_composite_field_report_combat_record ADD CONSTRAINT FK_CC_REPORT_COMBAT_USER FOREIGN KEY (perscom_user_id) REFERENCES perscom_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cc_composite_field_report_combat_record ADD CONSTRAINT FK_CC_REPORT_COMBAT_RECORD FOREIGN KEY (combat_record_id) REFERENCES perscom_record_combat (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cc_composite_field_report_combat_record');
    }
}
