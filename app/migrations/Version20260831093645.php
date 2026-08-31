<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les signes appris par énigme (lot 7.0).
 *
 * Les villes déjà en base n'en ont appris aucun : elles arrivent avec une
 * liste vide, ce qui est exactement leur état. Le défaut est retiré aussitôt —
 * c'est le code qui décide de la valeur d'une ville neuve, pas le schéma.
 */
final class Version20260831093645 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clé de lecture : les signes appris par énigme';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE city ADD symboles_appris JSON NOT NULL DEFAULT \'[]\'');
        $this->addSql('ALTER TABLE city ALTER symboles_appris DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE city DROP symboles_appris');
    }
}
