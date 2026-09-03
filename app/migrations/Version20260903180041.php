<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Plusieurs champs sur une même case.
 *
 * Une case n'est pas un champ, c'est une terre : sur une grille de neuf cases
 * figurant tout un Delta, un seul champ par case plafonnait la ville à deux ou
 * trois champs quelle que soit sa population. Ils partagent la même culture et
 * le même semis ; ce qui change, ce sont les bras qu'il faut et ce qui rentre.
 *
 * **Une seule colonne, à un par défaut** : toute case déjà semée garde
 * exactement le comportement qu'elle avait.
 */
final class Version20260903180041 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Plusieurs champs par case cultivable.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE zone ADD champs INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE zone DROP champs');
    }
}
