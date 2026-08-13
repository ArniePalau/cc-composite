<?php

declare(strict_types=1);

namespace CcCompositeMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260813170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create CC Composite layers, permissions, defaults, selections and award placement tables.';
    }

    public function up(Schema $schema): void
    {
        $options = 'DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB';
        $this->addSql("CREATE TABLE cc_composite_layer (id INT AUTO_INCREMENT NOT NULL, filename VARCHAR(255) NOT NULL, category VARCHAR(32) NOT NULL, UNIQUE INDEX uniq_cc_layer_file (category, filename), PRIMARY KEY(id)) $options");
        $this->addSql("CREATE TABLE cc_composite_selection (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, layers JSON NOT NULL, generated_path VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_CC_SELECTION_USER (user_id), PRIMARY KEY(id)) $options");
        $this->addSql("CREATE TABLE cc_composite_default (id INT AUTO_INCREMENT NOT NULL, unit_id INT DEFAULT NULL, scope_key VARCHAR(64) NOT NULL, layers JSON NOT NULL, UNIQUE INDEX UNIQ_CC_DEFAULT_SCOPE (scope_key), INDEX IDX_CC_DEFAULT_UNIT (unit_id), PRIMARY KEY(id)) $options");
        $this->addSql("CREATE TABLE cc_composite_award_placement (id INT AUTO_INCREMENT NOT NULL, award_id INT NOT NULL, category VARCHAR(32) NOT NULL, UNIQUE INDEX UNIQ_CC_AWARD (award_id), PRIMARY KEY(id)) $options");

        $this->addSql("CREATE TABLE cc_composite_layer_rank (composite_layer_id INT NOT NULL, rank_id INT NOT NULL, INDEX IDX_CC_LAYER_RANK_LAYER (composite_layer_id), INDEX IDX_CC_LAYER_RANK_RANK (rank_id), PRIMARY KEY(composite_layer_id, rank_id)) $options");
        $this->addSql("CREATE TABLE cc_composite_layer_unit (composite_layer_id INT NOT NULL, unit_id INT NOT NULL, INDEX IDX_CC_LAYER_UNIT_LAYER (composite_layer_id), INDEX IDX_CC_LAYER_UNIT_UNIT (unit_id), PRIMARY KEY(composite_layer_id, unit_id)) $options");
        $this->addSql("CREATE TABLE cc_composite_layer_user (composite_layer_id INT NOT NULL, perscom_user_id INT NOT NULL, INDEX IDX_CC_LAYER_USER_LAYER (composite_layer_id), INDEX IDX_CC_LAYER_USER_USER (perscom_user_id), PRIMARY KEY(composite_layer_id, perscom_user_id)) $options");

        $this->addSql('ALTER TABLE cc_composite_selection ADD CONSTRAINT FK_CC_SELECTION_USER FOREIGN KEY (user_id) REFERENCES perscom_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cc_composite_default ADD CONSTRAINT FK_CC_DEFAULT_UNIT FOREIGN KEY (unit_id) REFERENCES perscom_unit (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cc_composite_award_placement ADD CONSTRAINT FK_CC_AWARD FOREIGN KEY (award_id) REFERENCES perscom_award (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cc_composite_layer_rank ADD CONSTRAINT FK_CC_LAYER_RANK_LAYER FOREIGN KEY (composite_layer_id) REFERENCES cc_composite_layer (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cc_composite_layer_rank ADD CONSTRAINT FK_CC_LAYER_RANK_RANK FOREIGN KEY (rank_id) REFERENCES perscom_rank (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cc_composite_layer_unit ADD CONSTRAINT FK_CC_LAYER_UNIT_LAYER FOREIGN KEY (composite_layer_id) REFERENCES cc_composite_layer (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cc_composite_layer_unit ADD CONSTRAINT FK_CC_LAYER_UNIT_UNIT FOREIGN KEY (unit_id) REFERENCES perscom_unit (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cc_composite_layer_user ADD CONSTRAINT FK_CC_LAYER_USER_LAYER FOREIGN KEY (composite_layer_id) REFERENCES cc_composite_layer (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cc_composite_layer_user ADD CONSTRAINT FK_CC_LAYER_USER_USER FOREIGN KEY (perscom_user_id) REFERENCES perscom_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cc_composite_layer_rank');
        $this->addSql('DROP TABLE cc_composite_layer_unit');
        $this->addSql('DROP TABLE cc_composite_layer_user');
        $this->addSql('DROP TABLE cc_composite_selection');
        $this->addSql('DROP TABLE cc_composite_default');
        $this->addSql('DROP TABLE cc_composite_award_placement');
        $this->addSql('DROP TABLE cc_composite_layer');
    }
}
