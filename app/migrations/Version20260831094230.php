<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les inscriptions déjà lues (lot 7.1).
 *
 * Les villes déjà en base n'en ont lu aucune : liste vide, ce qui est
 * exactement leur état.
 */
final class Version20260831094230 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Déchiffrage : les inscriptions déjà lues';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE city ADD inscriptions_dechiffrees JSON NOT NULL DEFAULT \'[]\'');
        $this->addSql('ALTER TABLE city ALTER inscriptions_dechiffrees DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE city DROP inscriptions_dechiffrees');
    }
}
