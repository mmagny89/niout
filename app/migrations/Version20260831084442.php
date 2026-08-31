<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * L'état d'épidémie d'une ville (lot 6.6).
 *
 * Rien à reprendre sur les parties existantes : zéro quinzaine de fièvre est
 * exactement l'état d'une ville que rien n'a frappée.
 */
final class Version20260831084442 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Épidémies : quinzaines de fièvre et part des bras couchés';
    }

    public function up(Schema $schema): void
    {
        // Les villes déjà en base n'ont jamais eu la fièvre : elles arrivent
        // à zéro. Le défaut est retiré aussitôt — c'est le code qui décide de
        // la valeur d'une ville neuve, pas le schéma.
        $this->addSql('ALTER TABLE city ADD part_de_malades_en_centiemes INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE city ADD quinzaines_depidemie INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE city ALTER part_de_malades_en_centiemes DROP DEFAULT');
        $this->addSql('ALTER TABLE city ALTER quinzaines_depidemie DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE city DROP part_de_malades_en_centiemes');
        $this->addSql('ALTER TABLE city DROP quinzaines_depidemie');
    }
}
