<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le danger sur la carte (doc 02, doc 03, lot 10.1).
 *
 * Ce qu'une bande oppose sur une case, zéro valant « aucune bande ». Le danger
 * est un **attribut** qui se superpose au contenu de la zone, pas un contenu de
 * plus : une case peut porter un gisement et des bandits à la fois.
 *
 * **Les parties en cours restent sans danger.** La colonne naît à zéro partout,
 * et rien ne la remplit rétroactivement : les bandes se posent à la génération
 * de la carte, qui a déjà eu lieu pour ces parties. Les peupler après coup
 * rendrait imprenables des cases déjà exploitées.
 *
 * `DEFAULT 0` le temps de l'ajout, puis retiré — une colonne `NOT NULL` sans
 * défaut refuse de s'ajouter à une table peuplée, et le défaut ne doit pas
 * rester en base : c'est l'entité qui fait foi.
 */
final class Version20260902213526 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le danger des bandits sur une case de la carte.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE zone ADD defense_des_bandits INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE zone ALTER defense_des_bandits DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE zone DROP defense_des_bandits');
    }
}
