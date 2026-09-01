<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le débouché du Marché : ce que la place a déjà écoulé dans la quinzaine.
 *
 * La colonne naît à zéro sur les parties existantes — une quinzaine entamée
 * n'a rien vendu au sens du nouveau plafond, et il serait injuste de la
 * fermer d'emblée.
 */
final class Version20260901205008 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute à la ville le compteur de ce que le Marché a écoulé dans la quinzaine.';
    }

    public function up(Schema $schema): void
    {
        // Valeur par défaut le temps de peupler les lignes existantes, puis
        // retirée : la colonne est toujours écrite par l'application.
        $this->addSql('ALTER TABLE city ADD vendu_au_marche INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE city ALTER COLUMN vendu_au_marche DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE city DROP vendu_au_marche');
    }
}
