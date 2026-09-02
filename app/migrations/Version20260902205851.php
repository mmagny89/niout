<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le plafond de contribution des affaires de l'esprit (doc 13, lot 9.2).
 *
 * Compte ce que les énigmes et les enquêtes résolues ont déjà rapporté sur la
 * mission en cours, pour que ces deux sources ne remplissent pas à elles seules
 * une jauge qui se lit sur cent points.
 *
 * `DEFAULT 0` le temps de l'ajout, puis retiré : une colonne `NOT NULL` sans
 * défaut refuse de s'ajouter à une table déjà peuplée, et le défaut ne doit pas
 * rester en base — c'est le constructeur de l'entité qui fait foi.
 */
final class Version20260902205851 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le plafond de renommée des énigmes et enquêtes de la mission.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE family ADD renommee_des_affaires INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE family ALTER renommee_des_affaires DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE family DROP renommee_des_affaires');
    }
}
