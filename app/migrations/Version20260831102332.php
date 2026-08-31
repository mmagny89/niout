<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le retard d'une déduction erronée (lot 7.4).
 *
 * Les dossiers déjà ouverts n'ont subi aucun retard : zéro, ce qui est
 * exactement leur état.
 */
final class Version20260831102332 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enquêtes : le retard d\'une déduction erronée';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dossier_denquete ADD rejouable_au_cycle INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE dossier_denquete ALTER rejouable_au_cycle DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dossier_denquete DROP rejouable_au_cycle');
    }
}
