<?php

declare(strict_types=1);

namespace CcCompositeMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260817230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link field-report player identities to Forumify users.';
    }

    public function up(Schema $schema): void
    {
        $options = 'DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB';
        $this->addSql("CREATE TABLE cc_composite_field_report_player_link (id INT AUTO_INCREMENT NOT NULL, forumify_user_id INT NOT NULL, player_key VARCHAR(64) NOT NULL, player_name VARCHAR(255) NOT NULL, INDEX IDX_CC_REPORT_PLAYER_USER (forumify_user_id), UNIQUE INDEX uniq_cc_report_player_key (player_key), PRIMARY KEY(id), CONSTRAINT FK_CC_REPORT_PLAYER_USER FOREIGN KEY (forumify_user_id) REFERENCES user (id) ON DELETE CASCADE) $options");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cc_composite_field_report_player_link');
    }
}
