<?php

declare(strict_types=1);

namespace CcCompositeMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260819000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add admin-controlled field report visibility.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cc_composite_field_report ADD visible TINYINT(1) DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cc_composite_field_report DROP visible');
    }
}
