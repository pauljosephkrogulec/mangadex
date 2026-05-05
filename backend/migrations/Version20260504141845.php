<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260504141845 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tag_manga DROP CONSTRAINT fk_54b68705bad26311');
        $this->addSql('ALTER TABLE tag_manga DROP CONSTRAINT fk_54b687057b6461');
        $this->addSql('DROP TABLE tag_manga');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE tag_manga (tag_id INT NOT NULL, manga_id INT NOT NULL, PRIMARY KEY (tag_id, manga_id))');
        $this->addSql('CREATE INDEX idx_54b68705bad26311 ON tag_manga (tag_id)');
        $this->addSql('CREATE INDEX idx_54b687057b6461 ON tag_manga (manga_id)');
        $this->addSql('ALTER TABLE tag_manga ADD CONSTRAINT fk_54b68705bad26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tag_manga ADD CONSTRAINT fk_54b687057b6461 FOREIGN KEY (manga_id) REFERENCES manga (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
