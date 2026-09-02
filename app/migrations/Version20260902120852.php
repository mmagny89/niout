<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La leçon fondatrice de l'alphabet des scribes — écrire « Niout ».
 *
 * Un seul drapeau, et pour une seule raison : la récompense ne tombe qu'une
 * fois. L'exercice lui-même se refait autant qu'on veut.
 */
final class Version20260902120852 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Retient si la leçon fondatrice — écrire « Niout » — a déjà été réussie.';
    }

    public function up(Schema $schema): void
    {
        // Valeur par défaut le temps de peupler les parties existantes, puis
        // retirée : la colonne est toujours écrite par l'application.
        $this->addSql('ALTER TABLE city ADD niout_ecrite BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE city ALTER COLUMN niout_ecrite DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE city DROP niout_ecrite');
    }
}
