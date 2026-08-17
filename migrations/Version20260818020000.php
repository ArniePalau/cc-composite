<?php

declare(strict_types=1);

namespace CcCompositeMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260818020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create cc_composite_gallery_image table for campaign and mission photo gallery.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE cc_composite_gallery_image (
                id INT AUTO_INCREMENT NOT NULL,
                campaign_id INT DEFAULT NULL,
                field_report_id INT DEFAULT NULL,
                title VARCHAR(255) DEFAULT NULL,
                image_path VARCHAR(255) NOT NULL,
                position INT DEFAULT 0 NOT NULL,
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                INDEX idx_cc_gallery_campaign (campaign_id),
                INDEX idx_cc_gallery_field_report (field_report_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_CC_GALLERY_CAMPAIGN FOREIGN KEY (campaign_id) REFERENCES cc_composite_campaign (id) ON DELETE CASCADE,
                CONSTRAINT FK_CC_GALLERY_FIELD_REPORT FOREIGN KEY (field_report_id) REFERENCES cc_composite_field_report (id) ON DELETE SET NULL
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cc_composite_gallery_image');
    }
}
