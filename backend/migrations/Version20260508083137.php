<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508083137 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change all entity IDs from INT to VARCHAR(36) for UUID support';
    }

    public function up(Schema $schema): void
    {
        // ── 1. Drop all foreign key constraints ───────────────────────────────
        $this->addSql('ALTER TABLE chapter DROP CONSTRAINT FK_F981B52E7B6461');
        $this->addSql('ALTER TABLE chapter DROP CONSTRAINT FK_F981B52E26D00C4');
        $this->addSql('ALTER TABLE cover_art DROP CONSTRAINT FK_4EA5C33D7B6461');
        $this->addSql('ALTER TABLE custom_list DROP CONSTRAINT FK_45BE30E5A76ED395');
        $this->addSql('ALTER TABLE manga_creator DROP CONSTRAINT FK_22B13F627B6461');
        $this->addSql('ALTER TABLE manga_creator DROP CONSTRAINT FK_22B13F6261220EA6');
        $this->addSql('ALTER TABLE manga_tag DROP CONSTRAINT FK_52E8F5BA7B6461');
        $this->addSql('ALTER TABLE manga_tag DROP CONSTRAINT FK_52E8F5BABAD26311');
        $this->addSql('ALTER TABLE manga_follow DROP CONSTRAINT FK_7A1C33C5A76ED395');
        $this->addSql('ALTER TABLE manga_follow DROP CONSTRAINT FK_7A1C33C57B6461');
        $this->addSql('ALTER TABLE custom_list_manga DROP CONSTRAINT FK_9743D85B51C98D58');
        $this->addSql('ALTER TABLE custom_list_manga DROP CONSTRAINT FK_9743D85B7B6461');

        // ── 2. Drop primary keys and IDENTITY, then alter ID columns ──────────
        // app_user
        $this->addSql('ALTER TABLE app_user DROP CONSTRAINT app_user_pkey');
        $this->addSql('ALTER TABLE app_user ALTER id DROP IDENTITY');
        $this->addSql('ALTER TABLE app_user ALTER id TYPE VARCHAR(36) USING id::VARCHAR(36)');
        $this->addSql('ALTER TABLE app_user ADD PRIMARY KEY (id)');

        // chapter
        $this->addSql('ALTER TABLE chapter DROP CONSTRAINT chapter_pkey');
        $this->addSql('ALTER TABLE chapter ALTER id DROP IDENTITY');
        $this->addSql('ALTER TABLE chapter ALTER id TYPE VARCHAR(36) USING id::VARCHAR(36)');
        $this->addSql('ALTER TABLE chapter ADD PRIMARY KEY (id)');

        // cover_art
        $this->addSql('ALTER TABLE cover_art DROP CONSTRAINT cover_art_pkey');
        $this->addSql('ALTER TABLE cover_art ALTER id DROP IDENTITY');
        $this->addSql('ALTER TABLE cover_art ALTER id TYPE VARCHAR(36) USING id::VARCHAR(36)');
        $this->addSql('ALTER TABLE cover_art ADD PRIMARY KEY (id)');

        // creator
        $this->addSql('ALTER TABLE creator DROP CONSTRAINT creator_pkey');
        $this->addSql('ALTER TABLE creator ALTER id DROP IDENTITY');
        $this->addSql('ALTER TABLE creator ALTER id TYPE VARCHAR(36) USING id::VARCHAR(36)');
        $this->addSql('ALTER TABLE creator ADD PRIMARY KEY (id)');

        // custom_list
        $this->addSql('ALTER TABLE custom_list DROP CONSTRAINT custom_list_pkey');
        $this->addSql('ALTER TABLE custom_list ALTER id DROP IDENTITY');
        $this->addSql('ALTER TABLE custom_list ALTER id TYPE VARCHAR(36) USING id::VARCHAR(36)');
        $this->addSql('ALTER TABLE custom_list ADD PRIMARY KEY (id)');

        // manga
        $this->addSql('ALTER TABLE manga DROP CONSTRAINT manga_pkey');
        $this->addSql('ALTER TABLE manga ALTER id DROP IDENTITY');
        $this->addSql('ALTER TABLE manga ALTER id TYPE VARCHAR(36) USING id::VARCHAR(36)');
        $this->addSql('ALTER TABLE manga ADD PRIMARY KEY (id)');

        // scanlation_group
        $this->addSql('ALTER TABLE scanlation_group DROP CONSTRAINT scanlation_group_pkey');
        $this->addSql('ALTER TABLE scanlation_group ALTER id DROP IDENTITY');
        $this->addSql('ALTER TABLE scanlation_group ALTER id TYPE VARCHAR(36) USING id::VARCHAR(36)');
        $this->addSql('ALTER TABLE scanlation_group ADD PRIMARY KEY (id)');

        // tag
        $this->addSql('ALTER TABLE tag DROP CONSTRAINT tag_pkey');
        $this->addSql('ALTER TABLE tag ALTER id DROP IDENTITY');
        $this->addSql('ALTER TABLE tag ALTER id TYPE VARCHAR(36) USING id::VARCHAR(36)');
        $this->addSql('ALTER TABLE tag ADD PRIMARY KEY (id)');

        // manga_follow
        $this->addSql('ALTER TABLE manga_follow DROP CONSTRAINT manga_follow_pkey');
        $this->addSql('ALTER TABLE manga_follow ALTER id DROP IDENTITY');
        $this->addSql('ALTER TABLE manga_follow ALTER id TYPE VARCHAR(36) USING id::VARCHAR(36)');
        $this->addSql('ALTER TABLE manga_follow ADD PRIMARY KEY (id)');

        // ── 3. Alter foreign key columns (all INT → VARCHAR(36)) ─────────────
        $this->addSql('ALTER TABLE chapter ALTER manga_id TYPE VARCHAR(36) USING manga_id::VARCHAR(36)');
        $this->addSql('ALTER TABLE chapter ALTER scanlation_group_id TYPE VARCHAR(36) USING scanlation_group_id::VARCHAR(36)');
        $this->addSql('ALTER TABLE cover_art ALTER manga_id TYPE VARCHAR(36) USING manga_id::VARCHAR(36)');
        $this->addSql('ALTER TABLE custom_list ALTER user_id TYPE VARCHAR(36) USING user_id::VARCHAR(36)');
        $this->addSql('ALTER TABLE manga_creator ALTER manga_id TYPE VARCHAR(36) USING manga_id::VARCHAR(36)');
        $this->addSql('ALTER TABLE manga_creator ALTER creator_id TYPE VARCHAR(36) USING creator_id::VARCHAR(36)');
        $this->addSql('ALTER TABLE manga_tag ALTER manga_id TYPE VARCHAR(36) USING manga_id::VARCHAR(36)');
        $this->addSql('ALTER TABLE manga_tag ALTER tag_id TYPE VARCHAR(36) USING tag_id::VARCHAR(36)');
        $this->addSql('ALTER TABLE manga_follow ALTER user_id TYPE VARCHAR(36) USING user_id::VARCHAR(36)');
        $this->addSql('ALTER TABLE manga_follow ALTER manga_id TYPE VARCHAR(36) USING manga_id::VARCHAR(36)');
        $this->addSql('ALTER TABLE custom_list_manga ALTER custom_list_id TYPE VARCHAR(36) USING custom_list_id::VARCHAR(36)');
        $this->addSql('ALTER TABLE custom_list_manga ALTER manga_id TYPE VARCHAR(36) USING manga_id::VARCHAR(36)');

        // ── 4. Re-add all foreign key constraints ─────────────────────────────
        $this->addSql('ALTER TABLE chapter ADD CONSTRAINT FK_F981B52E7B6461 FOREIGN KEY (manga_id) REFERENCES manga (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chapter ADD CONSTRAINT FK_F981B52E26D00C4 FOREIGN KEY (scanlation_group_id) REFERENCES scanlation_group (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE cover_art ADD CONSTRAINT FK_4EA5C33D7B6461 FOREIGN KEY (manga_id) REFERENCES manga (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE custom_list ADD CONSTRAINT FK_45BE30E5A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE manga_creator ADD CONSTRAINT FK_22B13F627B6461 FOREIGN KEY (manga_id) REFERENCES manga (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE manga_creator ADD CONSTRAINT FK_22B13F6261220EA6 FOREIGN KEY (creator_id) REFERENCES creator (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE manga_tag ADD CONSTRAINT FK_52E8F5BA7B6461 FOREIGN KEY (manga_id) REFERENCES manga (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE manga_tag ADD CONSTRAINT FK_52E8F5BABAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE manga_follow ADD CONSTRAINT FK_7A1C33C5A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE manga_follow ADD CONSTRAINT FK_7A1C33C57B6461 FOREIGN KEY (manga_id) REFERENCES manga (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE custom_list_manga ADD CONSTRAINT FK_9743D85B51C98D58 FOREIGN KEY (custom_list_id) REFERENCES custom_list (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE custom_list_manga ADD CONSTRAINT FK_9743D85B7B6461 FOREIGN KEY (manga_id) REFERENCES manga (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // ── 1. Drop all foreign key constraints ───────────────────────────────
        $this->addSql('ALTER TABLE chapter DROP CONSTRAINT FK_F981B52E7B6461');
        $this->addSql('ALTER TABLE chapter DROP CONSTRAINT FK_F981B52E26D00C4');
        $this->addSql('ALTER TABLE cover_art DROP CONSTRAINT FK_4EA5C33D7B6461');
        $this->addSql('ALTER TABLE custom_list DROP CONSTRAINT FK_45BE30E5A76ED395');
        $this->addSql('ALTER TABLE manga_creator DROP CONSTRAINT FK_22B13F627B6461');
        $this->addSql('ALTER TABLE manga_creator DROP CONSTRAINT FK_22B13F6261220EA6');
        $this->addSql('ALTER TABLE manga_tag DROP CONSTRAINT FK_52E8F5BA7B6461');
        $this->addSql('ALTER TABLE manga_tag DROP CONSTRAINT FK_52E8F5BABAD26311');
        $this->addSql('ALTER TABLE manga_follow DROP CONSTRAINT FK_7A1C33C5A76ED395');
        $this->addSql('ALTER TABLE manga_follow DROP CONSTRAINT FK_7A1C33C57B6461');
        $this->addSql('ALTER TABLE custom_list_manga DROP CONSTRAINT FK_9743D85B51C98D58');
        $this->addSql('ALTER TABLE custom_list_manga DROP CONSTRAINT FK_9743D85B7B6461');

        // ── 2. Alter foreign key columns back (VARCHAR(36) → INT) ───────────
        $this->addSql('ALTER TABLE chapter ALTER manga_id TYPE INT USING manga_id::INTEGER');
        $this->addSql('ALTER TABLE chapter ALTER scanlation_group_id TYPE INT USING scanlation_group_id::INTEGER');
        $this->addSql('ALTER TABLE cover_art ALTER manga_id TYPE INT USING manga_id::INTEGER');
        $this->addSql('ALTER TABLE custom_list ALTER user_id TYPE INT USING user_id::INTEGER');
        $this->addSql('ALTER TABLE manga_creator ALTER manga_id TYPE INT USING manga_id::INTEGER');
        $this->addSql('ALTER TABLE manga_creator ALTER creator_id TYPE INT USING creator_id::INTEGER');
        $this->addSql('ALTER TABLE manga_tag ALTER manga_id TYPE INT USING manga_id::INTEGER');
        $this->addSql('ALTER TABLE manga_tag ALTER tag_id TYPE INT USING tag_id::INTEGER');
        $this->addSql('ALTER TABLE manga_follow ALTER user_id TYPE INT USING user_id::INTEGER');
        $this->addSql('ALTER TABLE manga_follow ALTER manga_id TYPE INT USING manga_id::INTEGER');
        $this->addSql('ALTER TABLE custom_list_manga ALTER custom_list_id TYPE INT USING custom_list_id::INTEGER');
        $this->addSql('ALTER TABLE custom_list_manga ALTER manga_id TYPE INT USING manga_id::INTEGER');

        // ── 3. Drop primary keys, alter ID columns back, re-add IDENTITY ────
        // app_user
        $this->addSql('ALTER TABLE app_user DROP CONSTRAINT app_user_pkey');
        $this->addSql('ALTER TABLE app_user ALTER id TYPE INT USING id::INTEGER');
        $this->addSql('ALTER TABLE app_user ALTER id ADD GENERATED BY DEFAULT AS IDENTITY');
        $this->addSql('ALTER TABLE app_user ADD PRIMARY KEY (id)');

        // chapter
        $this->addSql('ALTER TABLE chapter DROP CONSTRAINT chapter_pkey');
        $this->addSql('ALTER TABLE chapter ALTER id TYPE INT USING id::INTEGER');
        $this->addSql('ALTER TABLE chapter ALTER id ADD GENERATED BY DEFAULT AS IDENTITY');
        $this->addSql('ALTER TABLE chapter ADD PRIMARY KEY (id)');

        // cover_art
        $this->addSql('ALTER TABLE cover_art DROP CONSTRAINT cover_art_pkey');
        $this->addSql('ALTER TABLE cover_art ALTER id TYPE INT USING id::INTEGER');
        $this->addSql('ALTER TABLE cover_art ALTER id ADD GENERATED BY DEFAULT AS IDENTITY');
        $this->addSql('ALTER TABLE cover_art ADD PRIMARY KEY (id)');

        // creator
        $this->addSql('ALTER TABLE creator DROP CONSTRAINT creator_pkey');
        $this->addSql('ALTER TABLE creator ALTER id TYPE INT USING id::INTEGER');
        $this->addSql('ALTER TABLE creator ALTER id ADD GENERATED BY DEFAULT AS IDENTITY');
        $this->addSql('ALTER TABLE creator ADD PRIMARY KEY (id)');

        // custom_list
        $this->addSql('ALTER TABLE custom_list DROP CONSTRAINT custom_list_pkey');
        $this->addSql('ALTER TABLE custom_list ALTER id TYPE INT USING id::INTEGER');
        $this->addSql('ALTER TABLE custom_list ALTER id ADD GENERATED BY DEFAULT AS IDENTITY');
        $this->addSql('ALTER TABLE custom_list ADD PRIMARY KEY (id)');

        // manga
        $this->addSql('ALTER TABLE manga DROP CONSTRAINT manga_pkey');
        $this->addSql('ALTER TABLE manga ALTER id TYPE INT USING id::INTEGER');
        $this->addSql('ALTER TABLE manga ALTER id ADD GENERATED BY DEFAULT AS IDENTITY');
        $this->addSql('ALTER TABLE manga ADD PRIMARY KEY (id)');

        // scanlation_group
        $this->addSql('ALTER TABLE scanlation_group DROP CONSTRAINT scanlation_group_pkey');
        $this->addSql('ALTER TABLE scanlation_group ALTER id TYPE INT USING id::INTEGER');
        $this->addSql('ALTER TABLE scanlation_group ALTER id ADD GENERATED BY DEFAULT AS IDENTITY');
        $this->addSql('ALTER TABLE scanlation_group ADD PRIMARY KEY (id)');

        // tag
        $this->addSql('ALTER TABLE tag DROP CONSTRAINT tag_pkey');
        $this->addSql('ALTER TABLE tag ALTER id TYPE INT USING id::INTEGER');
        $this->addSql('ALTER TABLE tag ALTER id ADD GENERATED BY DEFAULT AS IDENTITY');
        $this->addSql('ALTER TABLE tag ADD PRIMARY KEY (id)');

        // manga_follow
        $this->addSql('ALTER TABLE manga_follow DROP CONSTRAINT manga_follow_pkey');
        $this->addSql('ALTER TABLE manga_follow ALTER id TYPE INT USING id::INTEGER');
        $this->addSql('ALTER TABLE manga_follow ALTER id ADD GENERATED BY DEFAULT AS IDENTITY');
        $this->addSql('ALTER TABLE manga_follow ADD PRIMARY KEY (id)');

        // ── 4. Re-add all foreign key constraints (original) ─────────────────
        $this->addSql('ALTER TABLE chapter ADD CONSTRAINT FK_F981B52E7B6461 FOREIGN KEY (manga_id) REFERENCES manga (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chapter ADD CONSTRAINT FK_F981B52E26D00C4 FOREIGN KEY (scanlation_group_id) REFERENCES scanlation_group (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE cover_art ADD CONSTRAINT FK_4EA5C33D7B6461 FOREIGN KEY (manga_id) REFERENCES manga (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE custom_list ADD CONSTRAINT FK_45BE30E5A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE manga_creator ADD CONSTRAINT FK_22B13F627B6461 FOREIGN KEY (manga_id) REFERENCES manga (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE manga_creator ADD CONSTRAINT FK_22B13F6261220EA6 FOREIGN KEY (creator_id) REFERENCES creator (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE manga_tag ADD CONSTRAINT FK_52E8F5BA7B6461 FOREIGN KEY (manga_id) REFERENCES manga (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE manga_tag ADD CONSTRAINT FK_52E8F5BABAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE manga_follow ADD CONSTRAINT FK_7A1C33C5A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE manga_follow ADD CONSTRAINT FK_7A1C33C57B6461 FOREIGN KEY (manga_id) REFERENCES manga (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE custom_list_manga ADD CONSTRAINT FK_9743D85B51C98D58 FOREIGN KEY (custom_list_id) REFERENCES custom_list (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE custom_list_manga ADD CONSTRAINT FK_9743D85B7B6461 FOREIGN KEY (manga_id) REFERENCES manga (id) ON DELETE CASCADE');
    }
}
