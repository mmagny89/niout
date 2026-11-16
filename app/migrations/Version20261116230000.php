<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les visages connus de la lignée (`docs/plan-pnj-palier-b.md` § 7). JSON, défaut `'{}'`. Colonne de `Lignee` :
 * aucune suppression de plus, `SuppressionDeCompte` la couvre déjà.
 */
final class Version20261116230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Visages connus de la lignée';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lignee ADD visages_connus JSON DEFAULT \'{}\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lignee DROP visages_connus');
    }
}
