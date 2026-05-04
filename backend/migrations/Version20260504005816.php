<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260504005816 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX IDX_F981B52ED4DB71B5 ON chapter (language)');
        $this->addSql('CREATE INDEX IDX_F981B52ECDCEF8DF ON chapter (chapter_number)');
        $this->addSql('CREATE INDEX IDX_BC06EA638CDE5729 ON creator (type)');
        $this->addSql('CREATE INDEX IDX_765A9E037B00651C ON manga (status)');
        $this->addSql('CREATE INDEX IDX_765A9E03BB827337 ON manga (year)');
        $this->addSql('CREATE INDEX IDX_765A9E03B0BF568E ON manga (content_rating)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX IDX_F981B52ED4DB71B5');
        $this->addSql('DROP INDEX IDX_F981B52ECDCEF8DF');
        $this->addSql('DROP INDEX IDX_BC06EA638CDE5729');
        $this->addSql('DROP INDEX IDX_765A9E037B00651C');
        $this->addSql('DROP INDEX IDX_765A9E03BB827337');
        $this->addSql('DROP INDEX IDX_765A9E03B0BF568E');
    }
}
