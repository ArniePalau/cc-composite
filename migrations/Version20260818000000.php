<?php

declare(strict_types=1);

namespace CcCompositeMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260818000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create reusable campaigns and assign field reports to them.';
    }

    public function up(Schema $schema): void
    {
        $options = 'DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB';
        $this->addSql("CREATE TABLE cc_composite_campaign (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_CC_CAMPAIGN_SLUG (slug), PRIMARY KEY(id)) $options");
        $this->addSql('ALTER TABLE cc_composite_field_report ADD campaign_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE cc_composite_field_report ADD CONSTRAINT FK_CC_REPORT_CAMPAIGN FOREIGN KEY (campaign_id) REFERENCES cc_composite_campaign (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_CC_REPORT_CAMPAIGN ON cc_composite_field_report (campaign_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cc_composite_field_report DROP FOREIGN KEY FK_CC_REPORT_CAMPAIGN');
        $this->addSql('DROP INDEX IDX_CC_REPORT_CAMPAIGN ON cc_composite_field_report');
        $this->addSql('ALTER TABLE cc_composite_field_report DROP campaign_id');
        $this->addSql('DROP TABLE cc_composite_campaign');
    }
}
