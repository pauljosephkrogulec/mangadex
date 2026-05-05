<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260505150106 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_user ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('UPDATE app_user SET created_at = NOW() WHERE created_at IS NULL');
        $this->addSql('ALTER TABLE app_user ALTER created_at SET NOT NULL');

        $this->addSql('ALTER TABLE chapter ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('UPDATE chapter SET created_at = NOW() WHERE created_at IS NULL');
        $this->addSql('ALTER TABLE chapter ALTER created_at SET NOT NULL');

        $this->addSql('ALTER TABLE cover_art ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('UPDATE cover_art SET created_at = NOW() WHERE created_at IS NULL');
        $this->addSql('ALTER TABLE cover_art ALTER created_at SET NOT NULL');

        $this->addSql('ALTER TABLE creator ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('UPDATE creator SET created_at = NOW() WHERE created_at IS NULL');
        $this->addSql('ALTER TABLE creator ALTER created_at SET NOT NULL');

        $this->addSql('ALTER TABLE custom_list ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('UPDATE custom_list SET created_at = NOW() WHERE created_at IS NULL');
        $this->addSql('ALTER TABLE custom_list ALTER created_at SET NOT NULL');

        $this->addSql('ALTER TABLE manga ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('UPDATE manga SET created_at = NOW() WHERE created_at IS NULL');
        $this->addSql('ALTER TABLE manga ALTER created_at SET NOT NULL');

        $this->addSql('ALTER TABLE scanlation_group ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('UPDATE scanlation_group SET created_at = NOW() WHERE created_at IS NULL');
        $this->addSql('ALTER TABLE scanlation_group ALTER created_at SET NOT NULL');

        $this->addSql('ALTER TABLE tag ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('UPDATE tag SET created_at = NOW() WHERE created_at IS NULL');
        $this->addSql('ALTER TABLE tag ALTER created_at SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_user DROP created_at');
        $this->addSql('ALTER TABLE chapter DROP created_at');
        $this->addSql('ALTER TABLE cover_art DROP created_at');
        $this->addSql('ALTER TABLE creator DROP created_at');
        $this->addSql('ALTER TABLE custom_list DROP created_at');
        $this->addSql('ALTER TABLE manga DROP created_at');
        $this->addSql('ALTER TABLE scanlation_group DROP created_at');
        $this->addSql('ALTER TABLE tag DROP created_at');
    }
}
