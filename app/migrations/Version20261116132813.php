<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Un prénom pour chaque chef et chaque Medjaÿ (lot N, `docs/plan-pnj-ville-vivante.md`).
 *
 * **Nullable, sans valeur par défaut à inventer** : les chefs et les Medjaÿ déjà là
 * n'en ont pas, et `Game\Prenoms::completer()` leur en donne un au passage de
 * cycle — une migration ne saurait pas appliquer la règle d'unicité.
 */
final class Version20261116132813 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prénom des chefs et des Medjaÿ';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE employee ADD prenom VARCHAR(40) DEFAULT NULL');
        $this->addSql('ALTER TABLE medjay ADD prenom VARCHAR(40) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE employee DROP prenom');
        $this->addSql('ALTER TABLE medjay DROP prenom');
    }
}
