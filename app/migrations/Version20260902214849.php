<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ce que vaut l'arme d'un Medjaÿ (doc 03, doc 01, lot 10.3).
 *
 * En centièmes, figée à la remise de l'arme : monter la Forge n'améliore pas
 * rétroactivement ce qu'on a déjà donné. Le défaut vaut
 * `Equipement::QUALITE_SANS_ARME` — un homme jamais armé part quand même, moins
 * bien, et rien ne bloque une expédition.
 *
 * `DEFAULT` le temps de l'ajout puis retiré, comme partout : une colonne
 * `NOT NULL` sans défaut refuse de s'ajouter à une table peuplée, et c'est
 * l'entité qui fait foi ensuite.
 */
final class Version20260902214849 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la qualité de l\'arme portée par un Medjaÿ.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE medjay ADD qualite_de_lequipement INT NOT NULL DEFAULT 70');
        $this->addSql('ALTER TABLE medjay ALTER qualite_de_lequipement DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE medjay DROP qualite_de_lequipement');
    }
}
