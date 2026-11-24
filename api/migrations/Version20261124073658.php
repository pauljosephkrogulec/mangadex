<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124073658 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table series (première entité : identifiant UUID v7, horodatage)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE TABLE series (
                  description TEXT DEFAULT NULL,
                  status VARCHAR(16) NOT NULL,
                  title VARCHAR(255) NOT NULL,
                  created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                  updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                  id UUID NOT NULL,
                  PRIMARY KEY (id)
                )
            SQL);
        $this->addSql('CREATE INDEX series_created_at_idx ON series (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE series');
    }
}
