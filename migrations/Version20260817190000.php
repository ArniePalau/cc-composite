<?php

declare(strict_types=1);

namespace CcCompositeMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260817190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create locally cached mission field reports.';
    }

    public function up(Schema $schema): void
    {
        $options = 'DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB';
        $this->addSql("CREATE TABLE cc_composite_field_report (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(64) NOT NULL, source_url VARCHAR(2048) NOT NULL, mission_name VARCHAR(255) NOT NULL, world VARCHAR(128) NOT NULL, world_display_name VARCHAR(255) DEFAULT NULL, server_name VARCHAR(255) DEFAULT NULL, started_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', ended_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', duration_seconds INT NOT NULL, player_count INT NOT NULL, total_kills INT NOT NULL, total_friendly_kills INT NOT NULL, total_shots INT NOT NULL, payload JSON NOT NULL, map_path VARCHAR(255) DEFAULT NULL, map_size_meters INT DEFAULT NULL, imported_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX uniq_cc_field_report_code (code), INDEX idx_cc_field_report_started (started_at), INDEX idx_cc_field_report_world (world), PRIMARY KEY(id)) $options");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cc_composite_field_report');
    }
}
