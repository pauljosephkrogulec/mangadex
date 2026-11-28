<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261128091728 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Series : titres alternatifs, type, année, couverture, slug unique ; description renommée en synopsis';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE series RENAME COLUMN description TO synopsis');
        $this->addSql('ALTER TABLE series ADD alternative_titles JSONB DEFAULT \'[]\' NOT NULL');
        $this->addSql('ALTER TABLE series ADD year SMALLINT DEFAULT NULL');
        $this->addSql('ALTER TABLE series ADD cover_image VARCHAR(255) DEFAULT NULL');

        // Les séries existantes deviennent des mangas et reçoivent un slug provisoire unique (leur UUID) :
        // les contraintes NOT NULL ne sont posées qu'après ce remplissage.
        $this->addSql('ALTER TABLE series ADD type VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE series ADD slug VARCHAR(255) DEFAULT NULL');
        $this->addSql("UPDATE series SET type = 'manga', slug = id::text");
        $this->addSql('ALTER TABLE series ALTER type SET NOT NULL');
        $this->addSql('ALTER TABLE series ALTER slug SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX series_slug_uniq ON series (slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX series_slug_uniq');
        $this->addSql('ALTER TABLE series DROP slug');
        $this->addSql('ALTER TABLE series DROP type');
        $this->addSql('ALTER TABLE series DROP cover_image');
        $this->addSql('ALTER TABLE series DROP year');
        $this->addSql('ALTER TABLE series DROP alternative_titles');
        $this->addSql('ALTER TABLE series RENAME COLUMN synopsis TO description');
    }
}
