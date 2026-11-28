<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261128092738 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table page (position unique par chapitre, empreinte SHA-256, segments verticaux)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE page (segments JSONB DEFAULT \'[]\' NOT NULL, position SMALLINT NOT NULL, image VARCHAR(255) NOT NULL, width INT NOT NULL, height INT NOT NULL, hash VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, chapter_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX page_hash_idx ON page (hash)');
        $this->addSql('CREATE UNIQUE INDEX page_chapter_position_uniq ON page (chapter_id, position)');
        $this->addSql('CREATE INDEX IDX_140AB620579F4768 ON page (chapter_id)');
        $this->addSql('ALTER TABLE page ADD CONSTRAINT FK_140AB620579F4768 FOREIGN KEY (chapter_id) REFERENCES chapter (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE page DROP CONSTRAINT FK_140AB620579F4768');
        $this->addSql('DROP TABLE page');
    }
}
