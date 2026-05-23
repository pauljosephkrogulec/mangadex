<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260523002306 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE rating (id VARCHAR(36) NOT NULL, score INT NOT NULL, rated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id VARCHAR(36) NOT NULL, manga_id VARCHAR(36) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_D8892622A76ED395 ON rating (user_id)');
        $this->addSql('CREATE INDEX IDX_D88926227B6461 ON rating (manga_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D8892622A76ED3957B6461 ON rating (user_id, manga_id)');
        $this->addSql('ALTER TABLE rating ADD CONSTRAINT FK_D8892622A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rating ADD CONSTRAINT FK_D88926227B6461 FOREIGN KEY (manga_id) REFERENCES manga (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE chapter DROP CONSTRAINT fk_f981b52e7b6461');
        $this->addSql('ALTER TABLE chapter DROP CONSTRAINT fk_f981b52e26d00c4');
        $this->addSql('ALTER TABLE chapter ADD CONSTRAINT FK_F981B52E7B6461 FOREIGN KEY (manga_id) REFERENCES manga (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE chapter ADD CONSTRAINT FK_F981B52E26D00C4 FOREIGN KEY (scanlation_group_id) REFERENCES scanlation_group (id)');
        $this->addSql('ALTER TABLE cover_art DROP CONSTRAINT fk_4ea5c33d7b6461');
        $this->addSql('ALTER TABLE cover_art ADD CONSTRAINT FK_4EA5C33D7B6461 FOREIGN KEY (manga_id) REFERENCES manga (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE custom_list DROP CONSTRAINT fk_45be30e5a76ed395');
        $this->addSql('ALTER TABLE custom_list ADD CONSTRAINT FK_45BE30E5A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) NOT DEFERRABLE');
        $this->addSql('ALTER INDEX idx_9743d85b51c98d58 RENAME TO IDX_903636AA3AF77F46');
        $this->addSql('ALTER INDEX idx_9743d85b7b6461 RENAME TO IDX_903636AA7B6461');
        $this->addSql('ALTER TABLE manga_follow DROP CONSTRAINT fk_7a1c33c57b6461');
        $this->addSql('ALTER TABLE manga_follow DROP CONSTRAINT fk_7a1c33c5a76ed395');
        $this->addSql('ALTER TABLE manga_follow ADD CONSTRAINT FK_BB74E677A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE manga_follow ADD CONSTRAINT FK_BB74E6777B6461 FOREIGN KEY (manga_id) REFERENCES manga (id) NOT DEFERRABLE');
        $this->addSql('ALTER INDEX idx_7a1c33c5a76ed395 RENAME TO IDX_BB74E677A76ED395');
        $this->addSql('ALTER INDEX idx_7a1c33c57b6461 RENAME TO IDX_BB74E6777B6461');
        $this->addSql('ALTER INDEX idx_manga_follow_user_manga RENAME TO UNIQ_BB74E677A76ED3957B6461');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rating DROP CONSTRAINT FK_D8892622A76ED395');
        $this->addSql('ALTER TABLE rating DROP CONSTRAINT FK_D88926227B6461');
        $this->addSql('DROP TABLE rating');
        $this->addSql('ALTER TABLE chapter DROP CONSTRAINT FK_F981B52E7B6461');
        $this->addSql('ALTER TABLE chapter DROP CONSTRAINT FK_F981B52E26D00C4');
        $this->addSql('ALTER TABLE chapter ADD CONSTRAINT fk_f981b52e7b6461 FOREIGN KEY (manga_id) REFERENCES manga (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE chapter ADD CONSTRAINT fk_f981b52e26d00c4 FOREIGN KEY (scanlation_group_id) REFERENCES scanlation_group (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cover_art DROP CONSTRAINT FK_4EA5C33D7B6461');
        $this->addSql('ALTER TABLE cover_art ADD CONSTRAINT fk_4ea5c33d7b6461 FOREIGN KEY (manga_id) REFERENCES manga (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE custom_list DROP CONSTRAINT FK_45BE30E5A76ED395');
        $this->addSql('ALTER TABLE custom_list ADD CONSTRAINT fk_45be30e5a76ed395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX idx_903636aa3af77f46 RENAME TO idx_9743d85b51c98d58');
        $this->addSql('ALTER INDEX idx_903636aa7b6461 RENAME TO idx_9743d85b7b6461');
        $this->addSql('ALTER TABLE manga_follow DROP CONSTRAINT FK_BB74E677A76ED395');
        $this->addSql('ALTER TABLE manga_follow DROP CONSTRAINT FK_BB74E6777B6461');
        $this->addSql('ALTER TABLE manga_follow ADD CONSTRAINT fk_7a1c33c57b6461 FOREIGN KEY (manga_id) REFERENCES manga (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE manga_follow ADD CONSTRAINT fk_7a1c33c5a76ed395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX idx_bb74e677a76ed395 RENAME TO idx_7a1c33c5a76ed395');
        $this->addSql('ALTER INDEX idx_bb74e6777b6461 RENAME TO idx_7a1c33c57b6461');
        $this->addSql('ALTER INDEX uniq_bb74e677a76ed3957b6461 RENAME TO idx_manga_follow_user_manga');
    }
}
