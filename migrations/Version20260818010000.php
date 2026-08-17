<?php

declare(strict_types=1);

namespace CcCompositeMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260818010000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add optional campaign cover images.'; }
    public function up(Schema $schema): void { $this->addSql('ALTER TABLE cc_composite_campaign ADD image_path VARCHAR(255) DEFAULT NULL'); }
    public function down(Schema $schema): void { $this->addSql('ALTER TABLE cc_composite_campaign DROP image_path'); }
}
