<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260522204316 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add comment table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE comment (id VARCHAR(36) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, content TEXT NOT NULL, manga_id VARCHAR(36) NOT NULL, user_id VARCHAR(36) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_9474526C7B6461 ON comment (manga_id)');
        $this->addSql('CREATE INDEX IDX_9474526CA76ED395 ON comment (user_id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C7B6461 FOREIGN KEY (manga_id) REFERENCES manga (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE comment DROP CONSTRAINT FK_9474526C7B6461');
        $this->addSql('ALTER TABLE comment DROP CONSTRAINT FK_9474526CA76ED395');
        $this->addSql('DROP TABLE comment');
    }
}
