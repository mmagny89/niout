<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le droit de passage payé aux bandes : l'échéance, sur la partie (`docs/plan-pnj-palier-b.md` § 5.3). Défaut 0.
 */
final class Version20261116220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Droit de passage payé aux bandes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_save ADD droit_de_passage_jusqua INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_save DROP droit_de_passage_jusqua');
    }
}
