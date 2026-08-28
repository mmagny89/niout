<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Statut de partie et compteur de famine (`Subsistance`, amorce de la
 * Phase 4) : les parties déjà en cours reprennent en cours, sans famine.
 */
final class Version20260828082502 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le statut de partie et le compteur de famine sur game_save.';
    }

    public function up(Schema $schema): void
    {
        // Défaut pour les parties déjà en cours, qui n'ont jamais connu la
        // famine : elles reprennent avec un compteur à zéro.
        $this->addSql("ALTER TABLE game_save ADD statut VARCHAR(255) NOT NULL DEFAULT 'en_cours'");
        $this->addSql('ALTER TABLE game_save ADD quinzaines_de_famine INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE game_save ALTER statut DROP DEFAULT');
        $this->addSql('ALTER TABLE game_save ALTER quinzaines_de_famine DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_save DROP statut');
        $this->addSql('ALTER TABLE game_save DROP quinzaines_de_famine');
    }
}
