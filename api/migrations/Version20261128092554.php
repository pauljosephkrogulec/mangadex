<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261128092554 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table chapter (numéro décimal unique par série et par langue)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE chapter (title VARCHAR(255) DEFAULT NULL, volume SMALLINT DEFAULT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, status VARCHAR(16) NOT NULL, number NUMERIC(8, 2) NOT NULL, language VARCHAR(8) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, series_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX chapter_published_at_idx ON chapter (published_at)');
        $this->addSql('CREATE UNIQUE INDEX chapter_series_language_number_uniq ON chapter (series_id, language, number)');
        $this->addSql('CREATE INDEX IDX_F981B52E5278319C ON chapter (series_id)');
        $this->addSql('ALTER TABLE chapter ADD CONSTRAINT FK_F981B52E5278319C FOREIGN KEY (series_id) REFERENCES series (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chapter DROP CONSTRAINT FK_F981B52E5278319C');
        $this->addSql('DROP TABLE chapter');
    }
}
