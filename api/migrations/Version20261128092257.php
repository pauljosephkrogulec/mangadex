<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261128092257 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tables author, genre, tag et leurs relations N-N avec series (series_author, series_artist, series_genre, series_tag)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE author (name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX author_slug_uniq ON author (slug)');
        $this->addSql('CREATE TABLE genre (name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX genre_slug_uniq ON genre (slug)');
        $this->addSql('CREATE TABLE series_author (series_id UUID NOT NULL, author_id UUID NOT NULL, PRIMARY KEY (series_id, author_id))');
        $this->addSql('CREATE INDEX IDX_DEDC3BF55278319C ON series_author (series_id)');
        $this->addSql('CREATE INDEX IDX_DEDC3BF5F675F31B ON series_author (author_id)');
        $this->addSql('CREATE TABLE series_artist (series_id UUID NOT NULL, author_id UUID NOT NULL, PRIMARY KEY (series_id, author_id))');
        $this->addSql('CREATE INDEX IDX_622A75BA5278319C ON series_artist (series_id)');
        $this->addSql('CREATE INDEX IDX_622A75BAF675F31B ON series_artist (author_id)');
        $this->addSql('CREATE TABLE series_genre (series_id UUID NOT NULL, genre_id UUID NOT NULL, PRIMARY KEY (series_id, genre_id))');
        $this->addSql('CREATE INDEX IDX_F6DFD7E55278319C ON series_genre (series_id)');
        $this->addSql('CREATE INDEX IDX_F6DFD7E54296D31F ON series_genre (genre_id)');
        $this->addSql('CREATE TABLE series_tag (series_id UUID NOT NULL, tag_id UUID NOT NULL, PRIMARY KEY (series_id, tag_id))');
        $this->addSql('CREATE INDEX IDX_91B6784E5278319C ON series_tag (series_id)');
        $this->addSql('CREATE INDEX IDX_91B6784EBAD26311 ON series_tag (tag_id)');
        $this->addSql('CREATE TABLE tag (name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX tag_slug_uniq ON tag (slug)');
        $this->addSql('ALTER TABLE series_author ADD CONSTRAINT FK_DEDC3BF55278319C FOREIGN KEY (series_id) REFERENCES series (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE series_author ADD CONSTRAINT FK_DEDC3BF5F675F31B FOREIGN KEY (author_id) REFERENCES author (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE series_artist ADD CONSTRAINT FK_622A75BA5278319C FOREIGN KEY (series_id) REFERENCES series (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE series_artist ADD CONSTRAINT FK_622A75BAF675F31B FOREIGN KEY (author_id) REFERENCES author (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE series_genre ADD CONSTRAINT FK_F6DFD7E55278319C FOREIGN KEY (series_id) REFERENCES series (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE series_genre ADD CONSTRAINT FK_F6DFD7E54296D31F FOREIGN KEY (genre_id) REFERENCES genre (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE series_tag ADD CONSTRAINT FK_91B6784E5278319C FOREIGN KEY (series_id) REFERENCES series (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE series_tag ADD CONSTRAINT FK_91B6784EBAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE series_author DROP CONSTRAINT FK_DEDC3BF55278319C');
        $this->addSql('ALTER TABLE series_author DROP CONSTRAINT FK_DEDC3BF5F675F31B');
        $this->addSql('ALTER TABLE series_artist DROP CONSTRAINT FK_622A75BA5278319C');
        $this->addSql('ALTER TABLE series_artist DROP CONSTRAINT FK_622A75BAF675F31B');
        $this->addSql('ALTER TABLE series_genre DROP CONSTRAINT FK_F6DFD7E55278319C');
        $this->addSql('ALTER TABLE series_genre DROP CONSTRAINT FK_F6DFD7E54296D31F');
        $this->addSql('ALTER TABLE series_tag DROP CONSTRAINT FK_91B6784E5278319C');
        $this->addSql('ALTER TABLE series_tag DROP CONSTRAINT FK_91B6784EBAD26311');
        $this->addSql('DROP TABLE author');
        $this->addSql('DROP TABLE genre');
        $this->addSql('DROP TABLE series_author');
        $this->addSql('DROP TABLE series_artist');
        $this->addSql('DROP TABLE series_genre');
        $this->addSql('DROP TABLE series_tag');
        $this->addSql('DROP TABLE tag');
    }
}
